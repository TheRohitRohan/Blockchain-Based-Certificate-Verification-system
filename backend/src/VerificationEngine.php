<?php

namespace App;

use PDO;

class VerificationEngine {
    private $db;
    private $blockchain;
    private $pdfService;
    private $signatureService;
    private $metadataService;
    private $comparisonEngine;
    private $cache;
    private $config;
    
    private const BLOCKCHAIN_CACHE_TTL = 300; // 5 minutes - blockchain state changes infrequently
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->blockchain = new Blockchain();
        $this->pdfService = new PDFService();
        $this->signatureService = new SignatureService();
        $this->metadataService = new MetadataService();
        $this->comparisonEngine = new ComparisonEngine();
        $this->cache = Cache::getInstance();
        $this->config = require __DIR__ . '/../config.php';
    }
    
    /**
     * Complete verification flow for uploaded PDF
     */
    public function verifyUploadedPDF(string $pdfPath): array
    {
        try {
            // Step 1: Extract metadata from PDF to get certificate_id
            $extractedMetadata = $this->pdfService->extractMetadata($pdfPath);

            if (!$extractedMetadata) {
                // Fallback: try to find cert ID from visible text
                $text              = $this->pdfService->extractText($pdfPath);
                $extractedMetadata = $this->parseMetadataFromText($text);
            }

            if (!$extractedMetadata || !isset($extractedMetadata['certificate_id'])) {
                return [
                    'valid'     => false,
                    'status'    => 'invalid',
                    'message'   => 'Could not extract certificate metadata from PDF. '
                                 . 'The PDF may not have been issued by this system.',
                    'signature' => [
                        'signed'  => false,
                        'valid'   => false,
                        'signer'  => '',
                        'message' => 'Cannot verify signature: certificate not found in database',
                    ],
                ];
            }

            $certificateId = $extractedMetadata['certificate_id'];

            // NOTE: No cache read here — every uploaded PDF must be freshly verified.
            // Caching here would allow a tampered PDF to return a stale "valid" result.

            // Step 2: Fetch database record (to get onchain_hash for signature verification)
            $dbRecord = $this->getCertificateRecord($certificateId);

            if (!$dbRecord) {
                $result = [
                    'valid'              => false,
                    'status'             => 'not_found',
                    'message'            => 'Certificate not found in database',
                    'signature'          => [
                        'signed'  => false,
                        'valid'   => false,
                        'signer'  => '',
                        'message' => 'Cannot verify signature: certificate not found in database',
                    ],
                    'extracted_metadata' => $extractedMetadata,
                ];
                $this->logVerification($certificateId, 'not_found', 'upload', $result);
                return $result;
            }

            // Step 3: Verify digital signature
            // FIXED: Pass the stored onchain_hash from DB record as required second parameter
            // Signature is verified against the stable onchain hash, not PDF binary
            $signatureResult = $this->signatureService->verifySignature($pdfPath, $dbRecord['onchain_hash']);

            // Step 4: Check revocation
            $isRevoked = ($dbRecord['status'] === 'revoked' || ($dbRecord['is_revoked'] ?? false));
            if ($isRevoked) {
                $result = [
                    'valid'     => false,
                    'status'    => 'revoked',
                    'message'   => 'Certificate has been revoked',
                    'signature' => $signatureResult,
                    'certificate' => $this->sanitizeDbRecord($dbRecord),
                ];
                $this->logVerification($certificateId, 'revoked', 'upload', $result);
                return $result;
            }

            // Step 5: Compare metadata field by field
            $dbMetadata = $this->metadataService->buildMetadata([
                'certificate_id'  => $dbRecord['certificate_id'],
                'student_id'      => $dbRecord['student_id']      ?? '',
                'student_name'    => $dbRecord['student_name']    ?? '',
                'course_name'     => $dbRecord['course_name'],
                'degree_type'     => $dbRecord['degree_type']     ?? '',
                'issue_date'      => $dbRecord['issue_date'],
                'university_code' => $dbRecord['university_code'] ?? '',
                'university_name' => $dbRecord['university_name'] ?? '',
            ]);
            $comparison = $this->metadataService->compareMetadata($dbMetadata, $extractedMetadata);

            // Step 6: PDF binary hash check
            $pdfHash      = $this->pdfService->calculatePDFHash($pdfPath);
            $pdfHashMatch = ($pdfHash === $dbRecord['pdf_hash']);

            // Step 7: Blockchain verification (cached, but don't cache failures)
            $blockchainValid = null; // null = not checked, true = verified, false = failed
            $blockchainConnected = $this->blockchain->isConnected();
            if (!empty($dbRecord['onchain_hash']) && $blockchainConnected) {
                $blockchainValid = $this->verifyBlockchainCached($certificateId, $dbRecord['onchain_hash']);
            } elseif (!$blockchainConnected) {
                $blockchainValid = null; // blockchain unavailable, don't treat as failure
            }

            // Step 8: Determine overall validity
            // Signature is required if the DB record says it was signed (signature_status = 1).
            // If the certificate was created without signing (signature_status = 0), we do not
            // gate validity on it — otherwise all legacy/unsigned certs would always fail.
            $signatureRequired = (bool)($dbRecord['signature_status'] ?? false);
            $signatureOk       = $signatureResult['valid'] ?? false;

            // Core validity: DB checks (not revoked, metadata match, PDF hash match)
            // Blockchain is reported separately — don't fail the whole verification
            // just because blockchain is temporarily unavailable
            $coreValid = !$isRevoked
                    && $comparison['matches']
                    && $pdfHashMatch
                    && (!$signatureRequired || $signatureOk);
            
            // Full validity includes blockchain if it was checked
            $isValid = $coreValid && ($blockchainValid === true || $blockchainValid === null);

            $status = $isValid ? 'valid' : 'invalid';

            $result = [
                'valid'                => $isValid,
                'status'               => $status,
                'message'              => $this->getVerificationMessage(
                                            $isValid, false, $comparison,
                                            $pdfHashMatch, $blockchainValid ?? false
                                          ),
                'checks'               => [
                    'metadata_match'   => $comparison['matches'],
                    'pdf_hash_match'   => $pdfHashMatch,
                    'blockchain_valid' => $blockchainValid,
                    'blockchain_connected' => $blockchainConnected,
                    'signature_valid'  => $signatureOk,
                    'signature_required' => $signatureRequired,
                    'not_revoked'      => true,
                ],
                'metadata_differences' => $comparison['differences'] ?? [],
                'signature'            => $signatureResult,
                'pdf_hash_uploaded'    => $pdfHash,
                'pdf_hash_stored'      => $dbRecord['pdf_hash'],
                'blockchain_valid'     => $blockchainValid,
                'certificate'          => $this->sanitizeDbRecord($dbRecord),
                'extracted_metadata'   => $extractedMetadata,
            ];

            // Cache the RESULT (not bypassed) so ID-only verification can reuse it
            $this->cache->set("verify:{$certificateId}", $result, 3600);
            $this->logVerification($certificateId, $status, 'upload', $result);

            return $result;

        } catch (\Exception $e) {
            error_log("Verification failed: " . $e->getMessage());
            return [
                'valid'   => false,
                'status'  => 'error',
                'message' => 'Verification error: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Verify certificate by ID
     */
    public function verifyByCertificateId(string $certificateId, ?string $providedHash = null): array {
        // Check cache
        $cacheKey = "verify:{$certificateId}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && $providedHash === null) {
            return $cached;
        }
        
        // Use lighter query for quick verification (when no detailed info needed)
        if ($providedHash === null) {
            return $this->quickVerifyByCertificateId($certificateId);
        }
        
        $dbRecord = $this->getCertificateRecord($certificateId);
        
        if (!$dbRecord) {
            $result = [
                'valid' => false,
                'status' => 'not_found',
                'message' => 'Certificate not found'
            ];
            $this->logVerification($certificateId, 'not_found', 'certificate_id', $result);
            return $result;
        }
        
        // Check revocation
        if ($dbRecord['status'] === 'revoked' || ($dbRecord['is_revoked'] ?? false)) {
            $result = [
                'valid' => false,
                'status' => 'revoked',
                'message' => 'Certificate has been revoked',
                'certificate' => $dbRecord
            ];
            $this->logVerification($certificateId, 'revoked', 'certificate_id', $result);
            return $result;
        }
        
        // Verify hash if provided
        $hashMatch = true;
        if ($providedHash) {
            $hashMatch = ($providedHash === $dbRecord['certificate_hash'] || 
                        $providedHash === $dbRecord['onchain_hash']);
        }
        
        // Verify blockchain with caching — don't fail if blockchain unavailable
        $blockchainValid = null;
        $blockchainConnected = $this->blockchain->isConnected();
        if (!empty($dbRecord['onchain_hash']) && $blockchainConnected) {
            $blockchainValid = $this->verifyBlockchainCached(
                $certificateId,
                $dbRecord['onchain_hash']
            );
        }
        
        $isValid = $hashMatch && ($blockchainValid === true || $blockchainValid === null);
        
        $result = [
            'valid' => $isValid,
            'status' => $isValid ? 'valid' : 'invalid',
            'message' => $isValid ? 'Certificate is valid' : 'Certificate verification failed',
            'hash_match' => $hashMatch,
            'blockchain_valid' => $blockchainValid,
            'blockchain_connected' => $blockchainConnected,
            'certificate' => $dbRecord
        ];
        
        $this->cache->set($cacheKey, $result, $this->config['cache']['verification_ttl'] ?? 3600);
        $this->logVerification($certificateId, $result['status'], 'certificate_id', $result);
        
        return $result;
    }
    
    /**
     * Quick verification using lighter query - optimized for fast ID-based lookups
     */
    private function quickVerifyByCertificateId(string $certificateId): array {
        $cacheKey = "cert_light:{$certificateId}";
        
        // Check cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Use lighter query
        $dbRecord = $this->getCertificateRecordLight($certificateId);
        
        if (!$dbRecord) {
            $result = [
                'valid' => false,
                'status' => 'not_found',
                'message' => 'Certificate not found'
            ];
            $this->logVerification($certificateId, 'not_found', 'certificate_id', $result);
            return $result;
        }
        
        // Check revocation using boolean flag (fast)
        if ($dbRecord['status'] === 'revoked' || $dbRecord['is_revoked']) {
            $result = [
                'valid' => false,
                'status' => 'revoked',
                'message' => 'Certificate has been revoked',
                'revoked_at' => $dbRecord['revoked_at']
            ];
            $this->cache->set($cacheKey, $result, $this->config['cache']['verification_ttl'] ?? 3600);
            $this->logVerification($certificateId, 'revoked', 'certificate_id', $result);
            return $result;
        }
        
        // Verify blockchain with caching — don't fail if blockchain unavailable
        $blockchainValid = null;
        $blockchainConnected = $this->blockchain->isConnected();
        if (!empty($dbRecord['onchain_hash']) && $blockchainConnected) {
            $blockchainValid = $this->verifyBlockchainCached(
                $certificateId,
                $dbRecord['onchain_hash']
            );
        }
        
        // Certificate is valid from DB perspective if it's active and not revoked
        // Blockchain verification is reported separately
        $isValid = ($blockchainValid === true || $blockchainValid === null);
        
        $result = [
            'valid' => $isValid,
            'status' => $isValid ? 'valid' : 'invalid',
            'message' => $isValid ? 'Certificate is valid' : 'Certificate verification failed',
            'blockchain_valid' => $blockchainValid,
            'blockchain_connected' => $blockchainConnected
        ];
        
        // Only cache positive results — don't cache when blockchain was unavailable
        if ($blockchainValid !== null) {
            $this->cache->set($cacheKey, $result, $this->config['cache']['verification_ttl'] ?? 3600);
        }
        $this->logVerification($certificateId, $result['status'], 'certificate_id', $result);
        
        return $result;
    }
    
    /**
     * Strip internal fields before returning DB record to public callers.
     * Removes raw hashes, internal IDs, and other fields not needed in API responses.
     */
    private function sanitizeDbRecord(array $record): array
    {
        $remove = ['metadata_json', 'pdf_hash', 'metadata_hash', 'onchain_hash',
                   'block_number', 'chain_id', 'schema_version', 'revoked_by',
                   'original_is_revoked', 'id'];
        foreach ($remove as $key) {
            unset($record[$key]);
        }
        return $record;
    }
    
    /**
     * Get certificate record from database
     */
    private function getCertificateRecord(string $certificateId): ?array {
        $stmt = $this->db->prepare("
            SELECT 
                c.id,
                c.certificate_id,
                c.student_id,
                c.university_id,
                c.course_name,
                c.degree_type,
                c.issue_date,
                c.certificate_hash,
                c.blockchain_tx_hash,
                c.pdf_path,
                c.qr_code_path,
                c.status,
                c.revoked_at,
                c.revoked_by,
                c.created_at,
                c.updated_at,
                c.metadata_hash,
                c.pdf_hash,
                c.onchain_hash,
                c.metadata_json,
                c.signature_status,
                c.block_number,
                c.chain_id,
                c.schema_version,
                c.is_revoked as original_is_revoked,
                CASE WHEN c.status = 'revoked' OR c.is_revoked = 1 THEN 1 ELSE 0 END as is_revoked
            FROM certificates c
            JOIN students s ON c.student_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN universities un ON c.university_id = un.id
            WHERE c.certificate_id = ?
        ");
        $stmt->execute([$certificateId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }
    
    /**
     * Lightweight verification - fetches only essential fields for quick ID-based verification
     */
    private function getCertificateRecordLight(string $certificateId): ?array {
        $stmt = $this->db->prepare("
            SELECT 
                c.certificate_id,
                c.status,
                c.is_revoked,
                c.onchain_hash,
                c.certificate_hash,
                c.revoked_at
            FROM certificates c
            WHERE c.certificate_id = ?
        ");
        $stmt->execute([$certificateId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }
    
    /**
     * Verify blockchain with caching - uses shorter TTL since blockchain state rarely changes
     */
    private function verifyBlockchainCached(string $certificateId, string $onchainHash): bool {
        $cacheKey = "blockchain_verify:{$certificateId}:{$onchainHash}";
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $result = $this->blockchain->verifyCertificate($certificateId, $onchainHash);
        
        // Only cache positive results (true) — don't cache failures
        // This prevents stale "invalid" results when blockchain was temporarily down
        if ($result === true) {
            $this->cache->set($cacheKey, $result, self::BLOCKCHAIN_CACHE_TTL);
        }
        
        return $result;
    }
    
    /**
     * Invalidate blockchain cache for a certificate
     * FIX 10: Must delete all related cache keys properly
     */
    public function invalidateBlockchainCache(string $certificateId, ?string $onchainHash = null): void {
        // Delete known exact keys
        $cacheKeysToDelete = [
            "verify:{$certificateId}",
            "cert_light:{$certificateId}"
        ];
        
        // If we have the onchain hash, delete the exact blockchain verify key
        if ($onchainHash) {
            $cacheKeysToDelete[] = "blockchain_verify:{$certificateId}:{$onchainHash}";
        }
        
        foreach ($cacheKeysToDelete as $key) {
            $this->cache->delete($key);
        }
        
        // NOTE: Pattern-based deletion like "blockchain_verify:{id}:*" requires 
        // Redis SCAN or file glob - not supported by simple delete().
        // The exact key with onchainHash should be passed when available.
    }
    
    /**
     * Parse metadata from extracted text (fallback)
     */
    private function parseMetadataFromText(string $text): ?array {
        // Try to extract certificate ID
        if (preg_match('/CERT[_-]?([A-Z0-9]+)/i', $text, $matches)) {
            return [
                'certificate_id' => 'CERT-' . strtoupper($matches[1])
            ];
        }
        return null;
    }
    
    /**
     * Get verification message
     */
    private function getVerificationMessage(bool $isValid, bool $isRevoked, array $comparison, bool $pdfHashMatch, bool $blockchainValid): string {
        if ($isRevoked) {
            return 'Certificate has been revoked';
        }
        
        if (!$isValid) {
            $issues = [];
            if (!$comparison['matches']) {
                $issues[] = 'metadata mismatch';
            }
            if (!$pdfHashMatch) {
                $issues[] = 'PDF hash mismatch';
            }
            if (!$blockchainValid) {
                $issues[] = 'blockchain verification failed';
            }
            return 'Verification failed: ' . implode(', ', $issues);
        }
        
        return 'Certificate is valid and verified';
    }
    
    /**
     * Log verification attempt
     */
    private function logVerification(string $certificateId, string $result, string $method, array $details = []): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO verification_logs 
                (certificate_id, verifier_ip, verification_method, verification_result, verification_details)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $certificateId,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $method,
                $result,
                json_encode($details)
            ]);
        } catch (\Exception $e) {
            error_log("Failed to log verification: " . $e->getMessage());
        }
    }
}
