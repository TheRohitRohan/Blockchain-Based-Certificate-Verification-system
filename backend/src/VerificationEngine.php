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
    public function verifyUploadedPDF(string $pdfPath): array {
        try {
            // Step 1: Verify digital signature
            $signatureResult = $this->signatureService->verifySignature($pdfPath);
            
            // Step 2: Extract metadata
            $extractedMetadata = $this->pdfService->extractMetadata($pdfPath);
            
            if (!$extractedMetadata) {
                // Fallback: Extract text and try to parse
                $text = $this->pdfService->extractText($pdfPath);
                $extractedMetadata = $this->parseMetadataFromText($text);
            }
            
            if (!$extractedMetadata || !isset($extractedMetadata['certificate_id'])) {
                return [
                    'valid' => false,
                    'status' => 'invalid',
                    'message' => 'Could not extract certificate metadata from PDF',
                    'signature' => $signatureResult
                ];
            }
            
            $certificateId = $extractedMetadata['certificate_id'];
            
            // Check cache first
            $cacheKey = "verify:{$certificateId}";
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
            
            // Step 3: Fetch database record
            $dbRecord = $this->getCertificateRecord($certificateId);
            
            if (!$dbRecord) {
                $result = [
                    'valid' => false,
                    'status' => 'not_found',
                    'message' => 'Certificate not found in database',
                    'signature' => $signatureResult,
                    'extracted_metadata' => $extractedMetadata
                ];
                $this->cache->set($cacheKey, $result, $this->config['cache']['verification_ttl'] ?? 3600);
                $this->logVerification($certificateId, 'not_found', 'upload', $result);
                return $result;
            }
            
            // Step 4: Compare metadata
            $dbMetadata = $this->metadataService->buildMetadata([
                'certificate_id' => $dbRecord['certificate_id'],
                'student_id' => $dbRecord['student_id'] ?? '',
                'student_name' => $dbRecord['student_name'] ?? '',
                'course_name' => $dbRecord['course_name'],
                'degree_type' => $dbRecord['degree_type'] ?? '',
                'issue_date' => $dbRecord['issue_date'],
                'university_code' => $dbRecord['university_code'] ?? '',
                'university_name' => $dbRecord['university_name'] ?? ''
            ]);
            
            $comparison = $this->metadataService->compareMetadata($dbMetadata, $extractedMetadata);
            
            // Step 5: Calculate PDF hash and compare
            $pdfHash = $this->pdfService->calculatePDFHash($pdfPath);
            $pdfHashMatch = ($pdfHash === $dbRecord['pdf_hash']);
            
            // Step 6: Verify blockchain with caching
            $blockchainValid = false;
            if (!empty($dbRecord['onchain_hash'])) {
                $blockchainValid = $this->verifyBlockchainCached(
                    $certificateId,
                    $dbRecord['onchain_hash']
                );
            }
            
            // Step 7: Check revocation status
            $isRevoked = ($dbRecord['status'] === 'revoked' || $dbRecord['is_revoked'] ?? false);
            
            // Determine final result
            $isValid = !$isRevoked && 
                      $comparison['matches'] && 
                      $pdfHashMatch && 
                      $blockchainValid &&
                      $signatureResult['signed'];
            
            $result = [
                'valid' => $isValid,
                'status' => $isRevoked ? 'revoked' : ($isValid ? 'valid' : 'invalid'),
                'message' => $this->getVerificationMessage($isValid, $isRevoked, $comparison, $pdfHashMatch, $blockchainValid),
                'signature' => $signatureResult,
                'metadata_match' => $comparison['matches'],
                'metadata_differences' => $comparison['differences'] ?? [],
                'pdf_hash_match' => $pdfHashMatch,
                'blockchain_valid' => $blockchainValid,
                'certificate' => $dbRecord,
                'extracted_metadata' => $extractedMetadata
            ];
            
            // Cache result
            $this->cache->set($cacheKey, $result, 3600);
            
            // Log verification
            $this->logVerification($certificateId, $result['status'], 'upload', $result);
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Verification failed: " . $e->getMessage());
            return [
                'valid' => false,
                'status' => 'error',
                'message' => 'Verification error: ' . $e->getMessage()
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
        
        // Verify blockchain with caching
        $blockchainValid = false;
        if (!empty($dbRecord['onchain_hash'])) {
            $blockchainValid = $this->verifyBlockchainCached(
                $certificateId,
                $dbRecord['onchain_hash']
            );
        }
        
        $isValid = $hashMatch && $blockchainValid;
        
        $result = [
            'valid' => $isValid,
            'status' => $isValid ? 'valid' : 'invalid',
            'message' => $isValid ? 'Certificate is valid' : 'Certificate verification failed',
            'hash_match' => $hashMatch,
            'blockchain_valid' => $blockchainValid,
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
        
        // Verify blockchain with caching
        $blockchainValid = false;
        if (!empty($dbRecord['onchain_hash'])) {
            $blockchainValid = $this->verifyBlockchainCached(
                $certificateId,
                $dbRecord['onchain_hash']
            );
        }
        
        $isValid = $blockchainValid;
        
        $result = [
            'valid' => $isValid,
            'status' => $isValid ? 'valid' : 'invalid',
            'message' => $isValid ? 'Certificate is valid' : 'Certificate verification failed',
            'blockchain_valid' => $blockchainValid
        ];
        
        $this->cache->set($cacheKey, $result, $this->config['cache']['verification_ttl'] ?? 3600);
        $this->logVerification($certificateId, $result['status'], 'certificate_id', $result);
        
        return $result;
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
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
        
        // Cache with shorter TTL (5 minutes)
        $this->cache->set($cacheKey, $result, self::BLOCKCHAIN_CACHE_TTL);
        
        return $result;
    }
    
    /**
     * Invalidate blockchain cache for a certificate
     */
    public function invalidateBlockchainCache(string $certificateId): void {
        // Delete generic blockchain verification cache patterns
        $cacheKeysToDelete = [
            "blockchain_verify:{$certificateId}:",
            "verify:{$certificateId}",
            "cert_light:{$certificateId}"
        ];
        
        foreach ($cacheKeysToDelete as $key) {
            $this->cache->delete($key);
        }
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
