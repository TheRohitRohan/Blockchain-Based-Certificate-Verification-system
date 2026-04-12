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
        $cnfPath = $this->resolveOpenSslConfigPath();
        $ossl    = $cnfPath !== null ? ['config' => $cnfPath] : [];
        // Windows PHP often ignores the `config` array and only honours OPENSSL_CONF.
        $prevOpenSslConf = getenv('OPENSSL_CONF');
        if ($cnfPath !== null) {
            putenv('OPENSSL_CONF=' . $cnfPath);
        }

        try {
            try {
                $certsDir = __DIR__ . '/../certs/';
                if (!is_dir($certsDir)) {
                    mkdir($certsDir, 0700, true);
                }

                // Generate 2048-bit RSA key pair
                $keyResource = openssl_pkey_new(array_merge([
                    'digest_alg'       => 'sha256',
                    'private_key_bits' => 2048,
                    'private_key_type' => OPENSSL_KEYTYPE_RSA,
                ], $ossl));
                if (!$keyResource) {
                    $err = '';
                    while (($msg = openssl_error_string()) !== false) {
                        $err .= $msg . '; ';
                    }
                    throw new \Exception('Failed to generate key: ' . ($err !== '' ? $err : 'unknown OpenSSL error'));
                }

                // Export private key (PEM)
                $privateKeyPem = '';
                openssl_pkey_export($keyResource, $privateKeyPem, null, $ossl);

                // Generate self-signed certificate
                $dn = [
                    'commonName'       => $universityName,
                    'organizationName' => 'Certificate Verification System',
                    'countryName'      => 'IN',
                ];
                $csr  = openssl_csr_new($dn, $keyResource, array_merge(['digest_alg' => 'sha256'], $ossl));
                $cert = openssl_csr_sign($csr, null, $keyResource, 3650, array_merge(['digest_alg' => 'sha256'], $ossl));

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

                $encryptedKey = $this->encryptPrivateKey($privateKeyPem);

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
                error_log('Key generation failed: ' . $e->getMessage());
                return null;
            }
        } finally {
            if ($cnfPath !== null) {
                if ($prevOpenSslConf === false || $prevOpenSslConf === '') {
                    putenv('OPENSSL_CONF');
                } else {
                    putenv('OPENSSL_CONF=' . $prevOpenSslConf);
                }
            }
        }
    }

    // =========================================================================
    //  PRIVATE HELPERS
    // =========================================================================

    /**
     * First readable openssl.cnf: env OPENSSL_CONF, then bundled backend/certs/openssl.cnf,
     * then common Windows locations (Git for Windows).
     */
    private function resolveOpenSslConfigPath(): ?string
    {
        $candidates = [];

        $fromConfig = $this->config['signing']['openssl_config'] ?? '';
        if (is_string($fromConfig) && $fromConfig !== '') {
            $candidates[] = $fromConfig;
        }

        $candidates[] = __DIR__ . '/../certs/openssl.cnf';

        if (\defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows') {
            $candidates[] = 'C:\\Program Files\\Git\\usr\\ssl\\openssl.cnf';
            $candidates[] = 'C:\\Program Files (x86)\\Git\\usr\\ssl\\openssl.cnf';
        }

        foreach ($candidates as $p) {
            if ($p === '' || $p === null) {
                continue;
            }
            if (@is_readable($p)) {
                $real = @realpath($p);
                return ($real !== false) ? $real : $p;
            }
        }

        return null;
    }

    /**
     * Derive a 32-byte AES key from the configured secret.
     * If the secret is shorter than 32 chars, SHA256 is used to expand it.
     * This method is the single source of truth for key derivation so encrypt
     * and decrypt always use the exact same bytes.
     */
    private function getEncryptionSecret(): string
    {
        $secret = $this->config['signing']['key_encryption_secret'] ?? '';
        if (strlen($secret) < 32) {
            return hash('sha256', $secret, true); // 32 raw bytes
        }
        return substr($secret, 0, 32);
    }

    /**
     * Encrypt private key using AES-256-CBC with a random IV.
     *
     * Storage format: base64(IV) . '.' . base64(ciphertext)
     *
     * Both halves are individually base64-encoded before joining with '.'.
     * Because base64 output only uses [A-Za-z0-9+/=], the '.' delimiter
     * can never appear inside either half, making the split unambiguous
     * regardless of what the raw IV bytes contain.
     *
     * This replaces the old format base64_encode(rawIV . '::' . rawCiphertext)
     * where '::' could accidentally appear inside the raw binary IV, causing
     * explode() to split at the wrong position.
     */
    private function encryptPrivateKey(string $privateKeyPem): string
    {
        $secret = $this->getEncryptionSecret();
        $iv     = openssl_random_pseudo_bytes(16);

        $encrypted = openssl_encrypt(
            $privateKeyPem,
            'AES-256-CBC',
            $secret,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \Exception("Failed to encrypt private key: " . openssl_error_string());
        }

        // Store as: base64(iv) + '.' + base64(ciphertext)
        return base64_encode($iv) . '.' . base64_encode($encrypted);
    }

    /**
     * Decrypt private key stored by encryptPrivateKey().
     *
     * Handles three formats in order:
     *   1. New format  — base64(iv) . '.' . base64(ciphertext)  [current]
     *   2. Old format  — base64_encode(rawIV . '::' . rawCiphertext) [previous]
     *   3. Legacy PEM  — plain base64_encode(privateKeyPem) [very old]
     *
     * After detecting the old or legacy format a warning is logged so the
     * key can be regenerated with the new format.
     */
    private function decryptPrivateKey(string $storedValue): ?string
    {
        try {
            $secret = $this->getEncryptionSecret();

            // ── Strategy 1: New format — base64(iv) . '.' . base64(ciphertext) ──
            // Count dots to distinguish from base64 strings which never contain '.'
            if (substr_count($storedValue, '.') === 1) {
                [$ivB64, $ciphertextB64] = explode('.', $storedValue, 2);

                $iv         = base64_decode($ivB64, true);
                $ciphertext = base64_decode($ciphertextB64, true);

                if ($iv !== false && $ciphertext !== false && strlen($iv) === 16) {
                    $decrypted = openssl_decrypt(
                        $ciphertext,
                        'AES-256-CBC',
                        $secret,
                        OPENSSL_RAW_DATA,
                        $iv
                    );

                    if ($decrypted !== false) {
                        return $decrypted;
                    }
                }
                // Fall through to other strategies if this didn't work
            }

            // ── Strategy 2: Old format — base64_encode(rawIV . '::' . rawCiphertext) ──
            $raw = base64_decode($storedValue, true);
            if ($raw !== false && strpos($raw, '::') !== false) {
                // Find the FIRST occurrence of '::' and treat everything before it
                // as the IV. Only accept it if that gives exactly 16 bytes.
                $separatorPos = strpos($raw, '::');
                if ($separatorPos === 16) {
                    $iv         = substr($raw, 0, 16);
                    $ciphertext = substr($raw, 18); // skip 16 bytes IV + 2 bytes '::'

                    $decrypted = openssl_decrypt(
                        $ciphertext,
                        'AES-256-CBC',
                        $secret,
                        OPENSSL_RAW_DATA,
                        $iv
                    );

                    if ($decrypted !== false) {
                        error_log("Warning: University key uses old binary-separator format — regenerate it");
                        return $decrypted;
                    }
                }
            }

            // ── Strategy 3: Legacy plain base64(PEM) ──
            if ($raw !== false) {
                $trimmed = trim($raw);
                if (str_starts_with($trimmed, '-----BEGIN')) {
                    error_log("Warning: University key is stored as unencrypted PEM — regenerate it");
                    return $trimmed;
                }
            }

            throw new \Exception("All decryption strategies failed — key may be corrupt or wrong secret");

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