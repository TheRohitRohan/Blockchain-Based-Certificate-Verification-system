<?php

namespace App;

use PDO;

/**
 * SignatureService — Cryptographic PDF signing using OpenSSL.
 *
 * FIXED STRATEGY:
 * Instead of hashing the PDF binary (which changes after signature embedding),
 * we sign the stable onchainHash that is stored in the database.
 *
 * 1. During issuance: PDF is created → metadata hash is computed → PDF hash is computed →
 *    onchain hash (keccak256 of metadata_hash + pdf_hash) is generated → sign the onchain hash
 *    → embed signature into XMP → store everything.
 *
 * 2. During verification: Extract onchain_hash from either DB record (Flow 2) or PDF XMP (Flow 1)
 *    → extract signature from XMP → verify signature using onchain_hash as payload.
 *
 * This ensures the signature never needs to be re-computed because the payload (onchainHash)
 * is completely stable and independent of PDF content modifications.
 */
class SignatureService
{
    private $config;
    private $db;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config.php';
        $this->db     = Database::getInstance()->getConnection();
    }

    // =========================================================================
    //  PUBLIC API
    // =========================================================================

    /**
     * Sign a PDF file using the onchain hash.
     *
     * FIXED: Now accepts onchainHash as a parameter instead of hashing the PDF binary.
     * This ensures the signature remains valid even if the PDF XMP is modified
     * (e.g., when embedding the signature itself).
     *
     * @param string $pdfPath      Full path to the PDF (will have signature embedded into XMP)
     * @param int    $universityId University whose key to use
     * @param string $onchainHash  The stable hash to sign (keccak256 of metadata+pdf hashes)
     * @return bool  True on success
     */
    public function signPDF(string $pdfPath, int $universityId, string $onchainHash): bool
    {
        try {
            if (!file_exists($pdfPath)) {
                throw new \Exception("PDF not found: {$pdfPath}");
            }

            // Load university private key
            $keyData = $this->getUniversityPrivateKey($universityId);
            if (!$keyData) {
                error_log("No signing key found for university {$universityId} — skipping signature");
                return false;
            }

            // FIXED: Sign the onchainHash directly (hex string), not the PDF binary
            // Convert hex to binary for signing
            // FIXED: Strip 0x prefix if present (generateCombinedHash may include it)
            $hashToSign = ltrim($onchainHash, '0x');
            $dataToSign = hex2bin($hashToSign);
            if ($dataToSign === false) {
                throw new \Exception("Invalid onchain hash format (expected 64-char hex, got: {$onchainHash})");
            }

            // Sign with private key
            $signature = '';
            $result = openssl_sign($dataToSign, $signature, $keyData['private_key'], OPENSSL_ALGO_SHA256);
            if (!$result) {
                throw new \Exception("openssl_sign failed: " . openssl_error_string());
            }

            $signatureB64   = base64_encode($signature);
            $signerFingerprint = $keyData['fingerprint'];

            // Embed signature into XMP (XMP structure unchanged)
            return $this->embedSignatureInXMP($pdfPath, $signatureB64, $signerFingerprint);

        } catch (\Exception $e) {
            error_log("PDF signing failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify the digital signature of a PDF.
     *
     * FIXED: Now requires onchainHash parameter from database.
     * The onchain hash is the stable payload that was signed during PDF issuance.
     * This is ALWAYS required because it comes from the certificate record.
     *
     * Returns array with keys:
     *   signed        (bool)   — signature field present in XMP
     *   valid         (bool)   — signature cryptographically valid
     *   signer        (string) — fingerprint of the key that signed
     *   message       (string) — human-readable result
     *
     * @param string $pdfPath      Path to the PDF file
     * @param string $onchainHash  The onchain hash from database (required)
     * @return array Verification result
     */
    public function verifySignature(string $pdfPath, string $onchainHash): array
    {
        if (!file_exists($pdfPath)) {
            return $this->sigResult(false, false, '', 'PDF file not found');
        }

        try {
            // Step 1: Extract signature fields from XMP
            $xmpData = $this->extractSignatureFromXMP($pdfPath);

            if (!$xmpData || empty($xmpData['signature'])) {
                return $this->sigResult(false, false, '', 'No digital signature found in PDF');
            }

            $signatureB64      = $xmpData['signature'];
            $signerFingerprint = $xmpData['signer'] ?? '';

            // Step 2: Convert onchain hash to binary for openssl_verify
            // FIXED: Strip 0x prefix if present
            $hashToVerify = ltrim($onchainHash, '0x');
            $dataToVerify = hex2bin($hashToVerify);
            if ($dataToVerify === false) {
                return $this->sigResult(true, false, $signerFingerprint,
                    'Invalid onchain hash format (expected 64-char hex, got: ' . $onchainHash . ')');
            }

            // Step 3: Fetch public key by fingerprint
            $publicKey = $this->getPublicKeyByFingerprint($signerFingerprint);
            if (!$publicKey) {
                return $this->sigResult(true, false, $signerFingerprint,
                    "Signature present but signer key not found (fingerprint: {$signerFingerprint})");
            }

            // Step 4: Verify the signature
            $signature = base64_decode($signatureB64);
            $verify    = openssl_verify($dataToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA256);

            if ($verify === 1) {
                return $this->sigResult(true, true, $signerFingerprint,
                    'Digital signature is valid');
            } elseif ($verify === 0) {
                return $this->sigResult(true, false, $signerFingerprint,
                    'Digital signature is invalid — PDF may have been tampered with');
            } else {
                throw new \Exception("openssl_verify error: " . openssl_error_string());
            }

        } catch (\Exception $e) {
            error_log("Signature verification error: " . $e->getMessage());
            return $this->sigResult(false, false, '', 'Signature verification error: ' . $e->getMessage());
        }
    }

    /**
     * Generate a self-signed RSA key pair for a university.
     * Saves private key + certificate to the certs/ directory.
     * Stores public key fingerprint + encrypted private key in university_keys table.
     *
     * Call once per university setup. Returns the fingerprint.
     */
    public function generateUniversityKeyPair(int $universityId, string $universityName): ?array
    {
        try {
            $certsDir = __DIR__ . '/../certs/';
            if (!is_dir($certsDir)) {
                mkdir($certsDir, 0700, true);
            }

            // Generate 2048-bit RSA key pair
            $keyResource = openssl_pkey_new([
                'digest_alg'       => 'sha256',
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);
            if (!$keyResource) {
                throw new \Exception("Failed to generate key: " . openssl_error_string());
            }

            // Export private key (PEM)
            $privateKeyPem = '';
            openssl_pkey_export($keyResource, $privateKeyPem);

            // Generate self-signed certificate
            $dn = [
                'commonName'           => $universityName,
                'organizationName'     => 'Certificate Verification System',
                'countryName'          => 'IN',
            ];
            $csr  = openssl_csr_new($dn, $keyResource, ['digest_alg' => 'sha256']);
            $cert = openssl_csr_sign($csr, null, $keyResource, 3650, ['digest_alg' => 'sha256']);

            // Export certificate (PEM)
            $certPem = '';
            openssl_x509_export($cert, $certPem);

            // Get public key details for fingerprint
            $pubKeyDetails = openssl_pkey_get_details($keyResource);
            $publicKeyPem  = $pubKeyDetails['key'];
            $fingerprint   = hash('sha256', $publicKeyPem);

            // Save to files
            $keyPath  = $certsDir . "university_{$universityId}.key.pem";
            $certPath = $certsDir . "university_{$universityId}.cert.pem";
            file_put_contents($keyPath, $privateKeyPem);
            file_put_contents($certPath, $certPem);
            chmod($keyPath, 0600); // private key readable only by web server user

            // FIXED: Encrypt private key using AES-256-CBC before storage
            $encryptedKey = $this->encryptPrivateKey($privateKeyPem);

            // Upsert into university_keys
            $stmt = $this->db->prepare("
                INSERT INTO university_keys
                    (university_id, certificate_path, certificate_password, public_key_pem, key_fingerprint, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    certificate_path = VALUES(certificate_path),
                    certificate_password = VALUES(certificate_password),
                    public_key_pem = VALUES(public_key_pem),
                    key_fingerprint = VALUES(key_fingerprint),
                    is_active = 1,
                    updated_at = NOW()
            ");
            $stmt->execute([
                $universityId,
                $certPath,
                $encryptedKey,
                $publicKeyPem,
                $fingerprint,
            ]);

            return [
                'fingerprint'    => $fingerprint,
                'key_path'       => $keyPath,
                'cert_path'      => $certPath,
                'public_key_pem' => $publicKeyPem,
            ];

        } catch (\Exception $e) {
            error_log("Key generation failed: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    //  PRIVATE HELPERS
    // =========================================================================

    /**
     * FIXED: Encrypt private key using AES-256-CBC with random IV.
     * Format: base64_encode(IV || '::' || encryptedPem)
     * where IV is 16 bytes (128-bit).
     */
    private function encryptPrivateKey(string $privateKeyPem): string
    {
        $secret = $this->config['signing']['key_encryption_secret'] ?? '';
        if (strlen($secret) < 32) {
            // Fallback: use SHA256 of secret to get 32 bytes
            $secret = hash('sha256', $secret, true);
        } else {
            $secret = substr($secret, 0, 32);
        }

        // Generate random 16-byte IV
        $iv = openssl_random_pseudo_bytes(16);

        // Encrypt with AES-256-CBC
        $encrypted = openssl_encrypt(
            $privateKeyPem,
            'AES-256-CBC',
            $secret,
            OPENSSL_RAW_DATA,  // No base64 yet; we'll do it ourselves
            $iv
        );

        if ($encrypted === false) {
            throw new \Exception("Failed to encrypt private key: " . openssl_error_string());
        }

        // Format: base64_encode(IV || '::' || encrypted)
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * FIXED: Decrypt private key using AES-256-CBC.
     * Expects format: base64_encode(IV || '::' || encryptedPem)
     * 
     * FIXED: Also handles old-format plain base64 keys (legacy support with warning).
     */
    private function decryptPrivateKey(string $encryptedKeyB64): ?string
    {
        try {
            $data = base64_decode($encryptedKeyB64);
            if ($data === false) {
                throw new \Exception("Invalid base64 format");
            }

            // Extract IV and encrypted data
            $parts = explode('::', $data, 2);
            if (count($parts) !== 2) {
                // Possibly old-format base64-only key — try legacy decode
                // Old keys stored as plain PEM without encryption
                $privateKey = openssl_pkey_get_private($data);
                if ($privateKey) {
                    error_log("Warning: University key is using old unencrypted format — regenerate it for security");
                    return $data; // Return the PEM directly
                }
                throw new \Exception("Invalid encrypted key format (missing separator) and not valid PEM");
            }

            list($iv, $encrypted) = $parts;

            if (strlen($iv) !== 16) {
                throw new \Exception("Invalid IV length (expected 16 bytes)");
            }

            $secret = $this->config['signing']['key_encryption_secret'] ?? '';
            if (strlen($secret) < 32) {
                $secret = hash('sha256', $secret, true);
            } else {
                $secret = substr($secret, 0, 32);
            }

            // Decrypt
            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $secret,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                throw new \Exception("Decryption failed: " . openssl_error_string());
            }

            return $decrypted;

        } catch (\Exception $e) {
            error_log("Private key decryption error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * FIXED: Updated to decrypt private key before use.
     */
    private function getUniversityPrivateKey(int $universityId): ?array
    {
        // Try DB first
        $stmt = $this->db->prepare("
            SELECT certificate_password, public_key_pem, key_fingerprint
            FROM university_keys
            WHERE university_id = ? AND is_active = 1
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$universityId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row && !empty($row['certificate_password'])) {
            // FIXED: Decrypt the private key
            $privateKeyPem = $this->decryptPrivateKey($row['certificate_password']);
            if ($privateKeyPem) {
                $privateKey = openssl_pkey_get_private($privateKeyPem);
                if ($privateKey) {
                    return [
                        'private_key' => $privateKey,
                        'fingerprint' => $row['key_fingerprint'] ?? hash('sha256', $row['public_key_pem'] ?? ''),
                    ];
                }
            }
        }

        // Fallback to config default key
        $defaultKeyPath = $this->config['signing']['default_cert_path'] ?? '';
        if (file_exists($defaultKeyPath)) {
            $pem        = file_get_contents($defaultKeyPath);
            $privateKey = openssl_pkey_get_private($pem);
            if ($privateKey) {
                $details     = openssl_pkey_get_details($privateKey);
                $fingerprint = hash('sha256', $details['key'] ?? $pem);
                return ['private_key' => $privateKey, 'fingerprint' => $fingerprint];
            }
        }

        return null;
    }

    private function getPublicKeyByFingerprint(string $fingerprint): mixed
    {
        if (empty($fingerprint)) {
            return null;
        }

        // Try DB
        $stmt = $this->db->prepare("
            SELECT public_key_pem FROM university_keys
            WHERE key_fingerprint = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$fingerprint]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row && !empty($row['public_key_pem'])) {
            return openssl_pkey_get_public($row['public_key_pem']);
        }

        // Fallback: try default cert
        $defaultKeyPath = $this->config['signing']['default_cert_path'] ?? '';
        if (file_exists($defaultKeyPath)) {
            $pem        = file_get_contents($defaultKeyPath);
            $privateKey = openssl_pkey_get_private($pem);
            if ($privateKey) {
                $details = openssl_pkey_get_details($privateKey);
                $fp      = hash('sha256', $details['key'] ?? $pem);
                if ($fp === $fingerprint) {
                    return openssl_pkey_get_public($details['key']);
                }
            }
        }

        return null;
    }

    /**
     * Embed cert:signature and cert:signer into the XMP block already in the PDF.
     * The XMP block must already exist (written by mPDF or embedMetadataIntoPDF).
     * 
     * NOTE: This method is unchanged from the original.
     */
    private function embedSignatureInXMP(string $pdfPath, string $signatureB64, string $fingerprint): bool
    {
        $binary = file_get_contents($pdfPath);

        $sigXml = '<cert:signature>' . htmlspecialchars($signatureB64, ENT_XML1) . '</cert:signature>'
                . '<cert:signer>'    . htmlspecialchars($fingerprint, ENT_XML1)   . '</cert:signer>';

        // Insert inside the existing cert: rdf:Description block
        if (preg_match('/(xmlns:cert="http:\/\/certificate\.system\/metadata\/"[^>]*>)(.*?)(<\/rdf:Description>)/s',
                $binary, $matches, PREG_OFFSET_CAPTURE)) {

            $blockStart  = $matches[0][1];
            $blockLength = strlen($matches[0][0]);

            $newBlock = $matches[1][0]
                      . $matches[2][0]          // existing content (cert:metadata CDATA)
                      . $sigXml                  // append signature fields
                      . $matches[3][0];          // closing tag

            $binary = substr($binary, 0, $blockStart)
                    . $newBlock
                    . substr($binary, $blockStart + $blockLength);

        } else {
            // XMP block not found — cannot embed signature
            error_log("embedSignatureInXMP: cert namespace block not found in {$pdfPath}");
            return false;
        }

        return file_put_contents($pdfPath, $binary) !== false;
    }

    /**
     * Extract cert:signature and cert:signer from XMP block.
     * 
     * NOTE: This method is unchanged from the original.
     */
    private function extractSignatureFromXMP(string $pdfPath): ?array
    {
        $binary = file_get_contents($pdfPath);

        $sig    = null;
        $signer = null;

        // Require exactly one signature tag to prevent injection attacks
        $sigMatches = [];
        $sigCount = preg_match_all('/<cert:signature>(.*?)<\/cert:signature>/s', $binary, $sigMatches);
        
        if ($sigCount === 0) {
            return null;
        }
        
        if ($sigCount > 1) {
            error_log("Multiple signature tags found in PDF - potential tampering detected");
            return null; // Reject PDFs with multiple signature tags
        }
        
        $sig = html_entity_decode(trim($sigMatches[1][0]), ENT_XML1, 'UTF-8');
        
        if (preg_match('/<cert:signer>(.*?)<\/cert:signer>/s', $binary, $m)) {
            $signer = html_entity_decode(trim($m[1]), ENT_XML1, 'UTF-8');
        }

        return ['signature' => $sig, 'signer' => $signer];
    }

    /**
     * FIXED: New helper method to extract onchain_hash from PDF XMP cert:metadata JSON.
     * 
     * Reads the CDATA block inside cert:metadata, JSON-decodes it, extracts onchain_hash.
     */
    private function extractOnchainHashFromPDF(string $pdfPath): ?string
    {
        try {
            $binary = file_get_contents($pdfPath);

            // Extract cert:metadata CDATA block
            if (!preg_match('/<cert:metadata>(.*?)<\/cert:metadata>/s', $binary, $matches)) {
                return null;
            }

            $metadataXml = $matches[1];

            // Extract CDATA content
            if (!preg_match('/\<\!\[CDATA\[(.*?)\]\]\>/s', $metadataXml, $cdataMatches)) {
                return null;
            }

            $jsonStr = $cdataMatches[1];
            $metadata = json_decode($jsonStr, true);

            if (!is_array($metadata) || !isset($metadata['onchain_hash'])) {
                return null;
            }

            return $metadata['onchain_hash'];

        } catch (\Exception $e) {
            error_log("Error extracting onchain hash from PDF: " . $e->getMessage());
            return null;
        }
    }

    private function sigResult(bool $signed, bool $valid, string $signer, string $message): array
    {
        return [
            'signed'  => $signed,
            'valid'   => $valid,
            'signer'  => $signer,
            'message' => $message,
        ];
    }
}