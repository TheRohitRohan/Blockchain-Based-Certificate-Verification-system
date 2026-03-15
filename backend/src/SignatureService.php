<?php

namespace App;

use PDO;

/**
 * SignatureService — Cryptographic PDF signing using OpenSSL.
 *
 * Strategy: Instead of embedding a binary PKCS#7 signature inside the PDF
 * structure (which requires complex ByteRange manipulation), we:
 *
 * 1. Compute SHA256 of the PDF binary (after XMP metadata is embedded but
 *    before the signature field itself is written).
 * 2. Sign the hash with the university's RSA private key using openssl_sign().
 * 3. Store the base64-encoded signature + public key fingerprint inside the
 *    PDF's XMP block under cert:signature and cert:signer.
 *
 * Verification reads the signature + signer fingerprint from XMP, fetches
 * the university's public key from the DB/config, and calls openssl_verify().
 *
 * This is a self-contained, verifiable signature that does not require
 * Adobe Acrobat or a third-party CA. The trust anchor is the university's
 * key stored in university_keys or config.php.
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
     * Sign a PDF file.
     *
     * Signs the PDF binary with the university's private key and embeds the
     * base64 signature + public key fingerprint into the XMP metadata block.
     *
     * IMPORTANT: Call this AFTER embedMetadata/SetAdditionalXmpRdf but BEFORE
     * calculatePDFHash. The stored pdf_hash must be the hash of the signed PDF.
     *
     * @param string $pdfPath      Full path to the PDF to sign (modified in-place)
     * @param int    $universityId University whose key to use
     * @return bool  True on success
     */
    public function signPDF(string $pdfPath, int $universityId): bool
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

            // Compute SHA256 of the current PDF binary
            $pdfBinary = file_get_contents($pdfPath);
            $dataToSign = hash('sha256', $pdfBinary, true); // raw binary

            // Sign with private key
            $signature = '';
            $result = openssl_sign($dataToSign, $signature, $keyData['private_key'], OPENSSL_ALGO_SHA256);
            if (!$result) {
                throw new \Exception("openssl_sign failed: " . openssl_error_string());
            }

            $signatureB64   = base64_encode($signature);
            $signerFingerprint = $keyData['fingerprint'];

            // Embed signature into XMP
            return $this->embedSignatureInXMP($pdfPath, $signatureB64, $signerFingerprint);

        } catch (\Exception $e) {
            error_log("PDF signing failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify the digital signature of a PDF.
     *
     * Extracts cert:signature and cert:signer from XMP, fetches the matching
     * public key from DB/config, and calls openssl_verify().
     *
     * Returns array with keys:
     *   signed        (bool)   — signature field present in XMP
     *   valid         (bool)   — signature cryptographically valid
     *   signer        (string) — fingerprint of the key that signed
     *   message       (string) — human-readable result
     */
    public function verifySignature(string $pdfPath): array
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

            // Step 2: Reconstruct the data that was signed
            // We need the PDF binary WITHOUT the cert:signature field — i.e. the
            // state of the binary before signPDF() appended the signature to XMP.
            $pdfBinary = file_get_contents($pdfPath);
            $binaryForVerification = $this->stripSignatureFromXMP($pdfBinary);
            $dataToVerify = hash('sha256', $binaryForVerification, true);

            // Step 3: Fetch public key
            $publicKey = $this->getPublicKeyByFingerprint($signerFingerprint);
            if (!$publicKey) {
                return $this->sigResult(true, false, $signerFingerprint,
                    "Signature present but signer key not found (fingerprint: {$signerFingerprint})");
            }

            // Step 4: Verify
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
     * Stores public key fingerprint + cert path in university_keys table.
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

            // Encrypt private key password for storage (use a real KMS in production)
            $encryptedKey = base64_encode($privateKeyPem); // TODO: encrypt with APP_KEY

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
            $privateKeyPem = base64_decode($row['certificate_password']); // TODO: decrypt properly
            $privateKey = openssl_pkey_get_private($privateKeyPem);
            if ($privateKey) {
                return [
                    'private_key' => $privateKey,
                    'fingerprint' => $row['key_fingerprint'] ?? hash('sha256', $row['public_key_pem'] ?? ''),
                ];
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
     */
    private function extractSignatureFromXMP(string $pdfPath): ?array
    {
        $binary = file_get_contents($pdfPath);

        $sig    = null;
        $signer = null;

        if (preg_match('/<cert:signature>(.*?)<\/cert:signature>/s', $binary, $m)) {
            $sig = html_entity_decode(trim($m[1]), ENT_XML1, 'UTF-8');
        }
        if (preg_match('/<cert:signer>(.*?)<\/cert:signer>/s', $binary, $m)) {
            $signer = html_entity_decode(trim($m[1]), ENT_XML1, 'UTF-8');
        }

        if ($sig === null) {
            return null;
        }

        return ['signature' => $sig, 'signer' => $signer];
    }

    /**
     * Strip cert:signature and cert:signer from PDF binary.
     * Used to reconstruct the exact binary state that existed when the PDF was signed
     * (before the signature fields were appended to XMP).
     */
    private function stripSignatureFromXMP(string $binary): string
    {
        $binary = preg_replace('/<cert:signature>.*?<\/cert:signature>/s', '', $binary);
        $binary = preg_replace('/<cert:signer>.*?<\/cert:signer>/s', '', $binary);
        return $binary;
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
