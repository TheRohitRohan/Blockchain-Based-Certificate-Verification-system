<?php

namespace App;

use PDO;

class CertificateService {
    private $db;
    private $blockchain;
    private $pdfService;
    private $signatureService;
    private $metadataService;
    private $verificationEngine;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->blockchain = new Blockchain();
        $this->pdfService = new PDFService();
        $this->signatureService = new SignatureService();
        $this->metadataService = new MetadataService();
        $this->verificationEngine = new VerificationEngine();
    }
    
    /**
     * Create certificate (Mode 1: Generate Certificate)
     * Complete pipeline: metadata → PDF → embed → QR → sign → hash → DB → blockchain
     */
    public function createCertificate($data) {
        try {
            $this->db->beginTransaction();
            
            // Get student info
            $stmt = $this->db->prepare("
                SELECT s.id, s.student_id, u.full_name, un.name as university_name, un.code as university_code
                FROM students s 
                JOIN users u ON s.user_id = u.id 
                JOIN universities un ON s.university_id = un.id
                WHERE s.id = ?
            ");
            $stmt->execute([$data['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student) {
                throw new \Exception("Student not found");
            }
            
            // Generate certificate ID
            $certificateId = 'CERT-' . strtoupper(uniqid());
            
            // Step 1: Build metadata
            $metadata = $this->metadataService->buildMetadata([
                'certificate_id' => $certificateId,
                'student_id' => $student['student_id'],
                'student_name' => $student['full_name'],
                'course_name' => $data['course_name'],
                'degree_type' => $data['degree_type'] ?? null,
                'issue_date' => $data['issue_date'],
                'university_code' => $student['university_code'],
                'university_name' => $student['university_name']
            ]);
            
            $metadataJson = $this->metadataService->generateMetadataJson($metadata);
            $metadataHash = $this->metadataService->generateMetadataHash($metadata);
            
            // Step 2: Generate PDF
            $certificateData = array_merge($student, [
                'certificate_id' => $certificateId,
                'student_name' => $student['full_name'], // Map full_name to student_name
                'course_name' => $data['course_name'],
                'degree_type' => $data['degree_type'] ?? null,
                'issue_date' => $data['issue_date']
            ]);
            
            $pdfPath = $this->pdfService->generateCertificatePDF($certificateId, $certificateData);
            
            // Step 3: Embed metadata
            $this->pdfService->embedMetadata($pdfPath, $metadata);

            // Step 4: QR code is embedded during PDF generation via HTML template
            // No separate insertion needed as it's part of the PDF template

            // Step 5: Digitally sign PDF (skip for now)
            // $signatureStatus = $this->signatureService->signPDF($pdfPath, $data['university_id']);
            $signatureStatus = false;
            
            // Step 6: Calculate PDF hash
            $pdfHash = $this->pdfService->calculatePDFHash($pdfPath);
            
            // Step 7: Generate combined hash for blockchain
            $onchainHash = $this->blockchain->generateCombinedHash($metadataHash, $pdfHash);
            
            // Step 8: Store on blockchain
            $blockchainResult = $this->blockchain->issueCertificate([
                'certificate_id' => $certificateId,
                'student_name' => $student['full_name'],
                'university_name' => $student['university_name'],
                'course_name' => $data['course_name'],
                'issue_date' => $data['issue_date']
            ]);
            
            // Get block number
            $blockNumber = $this->blockchain->getCurrentBlock();
            $chainId = $this->getConfig()['blockchain']['chain_id'] ?? 1337;
            
            // Step 9: Store in database
            $stmt = $this->db->prepare("
                INSERT INTO certificates 
                (certificate_id, student_id, university_id, course_name, degree_type, issue_date, 
                 certificate_hash, blockchain_tx_hash, pdf_path, qr_code_path, status,
                 metadata_hash, pdf_hash, onchain_hash, metadata_json, signature_status,
                 block_number, chain_id, schema_version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $pdfFilename = basename($pdfPath);
            $qrCodePath = null;
            
            $stmt->execute([
                $certificateId,
                $student['id'],
                $data['university_id'],
                $data['course_name'],
                $data['degree_type'] ?? null,
                $data['issue_date'],
                $onchainHash, // Store combined hash as certificate_hash for backward compatibility
                $blockchainResult['tx_hash'] ?? null,
                $pdfFilename,
                $qrCodePath,
                $metadataHash,
                $pdfHash,
                $onchainHash,
                $metadataJson,
                $signatureStatus ? 1 : 0,
                $blockNumber,
                $chainId,
                $this->metadataService->getSchemaVersion()
            ]);
            
            $this->db->commit();
            
            // Warm up cache for newly created certificate to avoid cold-start delays
            $this->warmupCertificateCache($certificateId, $onchainHash);
            
            return [
                'success' => true,
                'certificate_id' => $certificateId,
                'certificate_hash' => $onchainHash,
                'metadata_hash' => $metadataHash,
                'pdf_hash' => $pdfHash,
                'tx_hash' => $blockchainResult['tx_hash'] ?? 'pending',
                'signature_status' => $signatureStatus,
                'pdf_path' => $pdfFilename
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Certificate creation failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Upload and process certificate (Mode 2: Upload Certificate)
     */
    public function uploadCertificate($uploadedFile, $universityId) {
        try {
            if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
                throw new \Exception("Invalid file upload");
            }
            
            // Validate file type
            $fileInfo = pathinfo($uploadedFile['name']);
            if (strtolower($fileInfo['extension']) !== 'pdf') {
                throw new \Exception("Only PDF files are allowed");
            }
            
            // Move uploaded file to temp location
            $tempPath = $this->getConfig()['storage']['pdf_path'] . 'temp_' . uniqid() . '.pdf';
            move_uploaded_file($uploadedFile['tmp_name'], $tempPath);
            
            // Extract text and validate
            $text = $this->pdfService->extractText($tempPath);
            
            // Try to extract metadata
            $metadata = $this->pdfService->extractMetadata($tempPath);
            
            if (!$metadata) {
                // Parse from text (simplified)
                $metadata = $this->parseMetadataFromText($text);
            }
            
            if (!$metadata || !isset($metadata['certificate_id'])) {
                @unlink($tempPath);
                throw new \Exception("Could not extract certificate information from PDF");
            }
            
            // Check if certificate already exists
            $existing = $this->getCertificate($metadata['certificate_id']);
            if ($existing) {
                @unlink($tempPath);
                throw new \Exception("Certificate already exists");
            }
            
            $this->db->beginTransaction();
            
            // Build complete metadata
            $fullMetadata = $this->metadataService->buildMetadata($metadata);
            $metadataJson = $this->metadataService->generateMetadataJson($fullMetadata);
            $metadataHash = $this->metadataService->generateMetadataHash($fullMetadata);
            
            // Embed metadata if not present
            if (!$this->pdfService->extractMetadata($tempPath)) {
                $this->pdfService->embedMetadata($tempPath, $fullMetadata);
            }
            
            // Sign PDF
            $signatureStatus = $this->signatureService->signPDF($tempPath, $universityId);
            
            // Calculate hashes
            $pdfHash = $this->pdfService->calculatePDFHash($tempPath);
            $onchainHash = $this->blockchain->generateCombinedHash($metadataHash, $pdfHash);
            
            // Store on blockchain
            $blockchainResult = $this->blockchain->issueCertificate([
                'certificate_id' => $metadata['certificate_id'],
                'student_name' => $metadata['student_name'] ?? '',
                'university_name' => $metadata['university_name'] ?? '',
                'course_name' => $metadata['course_name'] ?? '',
                'issue_date' => $metadata['issue_date'] ?? date('Y-m-d')
            ]);
            
            $blockNumber = $this->blockchain->getCurrentBlock();
            $chainId = $this->getConfig()['blockchain']['chain_id'] ?? 1337;
            
            // Move to final location
            $finalPath = $this->getConfig()['storage']['pdf_path'] . basename($tempPath);
            rename($tempPath, $finalPath);
            
            // Store in database
            $stmt = $this->db->prepare("
                INSERT INTO certificates 
                (certificate_id, student_id, university_id, course_name, degree_type, issue_date,
                 certificate_hash, blockchain_tx_hash, pdf_path, status,
                 metadata_hash, pdf_hash, onchain_hash, metadata_json, signature_status,
                 block_number, chain_id, schema_version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Note: This assumes student_id and other fields are in metadata
            // In production, you'd validate and fetch these from database
            $stmt->execute([
                $metadata['certificate_id'],
                null, // Would need to resolve from student_id in metadata
                $universityId,
                $metadata['course_name'] ?? '',
                $metadata['degree_type'] ?? null,
                $metadata['issue_date'] ?? date('Y-m-d'),
                $onchainHash,
                $blockchainResult['tx_hash'] ?? null,
                basename($finalPath),
                $metadataHash,
                $pdfHash,
                $onchainHash,
                $metadataJson,
                $signatureStatus ? 1 : 0,
                $blockNumber,
                $chainId,
                $this->metadataService->getSchemaVersion()
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'certificate_id' => $metadata['certificate_id'],
                'message' => 'Certificate uploaded and processed successfully'
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }
            error_log("Certificate upload failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Verify certificate (uses VerificationEngine)
     */
    public function verifyCertificate($certificateId, $certificateHash = null) {
        if ($certificateHash) {
            // Legacy verification
            return $this->verificationEngine->verifyByCertificateId($certificateId, $certificateHash);
        } else {
            return $this->verificationEngine->verifyByCertificateId($certificateId);
        }
    }
    
    /**
     * Verify uploaded PDF
     */
    public function verifyUploadedPDF($uploadedFile) {
        try {
            if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
                throw new \Exception("Invalid file upload");
            }
            
            $tempPath = $this->getConfig()['storage']['pdf_path'] . 'verify_' . uniqid() . '.pdf';
            move_uploaded_file($uploadedFile['tmp_name'], $tempPath);
            
            $result = $this->verificationEngine->verifyUploadedPDF($tempPath);
            
            @unlink($tempPath);
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("PDF verification failed: " . $e->getMessage());
            return [
                'valid' => false,
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function getCertificate($certificateId) {
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
                s.student_id,
                u.full_name as student_name,
                un.name as university_name,
                un.code as university_code,
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
    
    public function revokeCertificate($certificateId, $revokedBy) {
        try {
            $this->db->beginTransaction();
            
            // Update database
            $stmt = $this->db->prepare("
                UPDATE certificates 
                SET status = 'revoked', revoked_at = NOW(), revoked_by = ?, is_revoked = 1
                WHERE certificate_id = ?
            ");
            $stmt->execute([$revokedBy, $certificateId]);
            
            // Revoke on blockchain
            $this->blockchain->revokeCertificate($certificateId);
            
            // Clear all verification caches (both regular and lightweight)
            $cache = Cache::getInstance();
            $cache->delete("verify:{$certificateId}");
            $cache->delete("cert_light:{$certificateId}");
            $cache->delete("blockchain_verify:{$certificateId}:");
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Certificate revocation failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getStudentCertificates($studentId) {
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                un.name as university_name
            FROM certificates c
            JOIN universities un ON c.university_id = un.id
            JOIN students s ON c.student_id = s.id
            WHERE s.id = ?
            ORDER BY c.issue_date DESC
        ");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
    
    private function parseMetadataFromText(string $text): ?array {
        // Simplified parsing - extract certificate ID
        if (preg_match('/CERT[_-]?([A-Z0-9]+)/i', $text, $matches)) {
            return [
                'certificate_id' => 'CERT-' . strtoupper($matches[1])
            ];
        }
        return null;
    }
    
    private function warmupCertificateCache(string $certificateId, string $onchainHash): void {
        try {
            $cache = Cache::getInstance();
            $config = $this->getConfig();
            $ttl = $config['cache']['verification_ttl'] ?? 3600;
            
            // Cache lightweight verification result
            $lightResult = [
                'valid' => true,
                'status' => 'valid',
                'message' => 'Certificate is valid',
                'blockchain_valid' => true
            ];
            $cache->set("cert_light:{$certificateId}", $lightResult, $ttl);
            
            // Pre-cache blockchain verification
            $cache->set("blockchain_verify:{$certificateId}:{$onchainHash}", true, $config['cache']['ttl'] ?? 3600);
            
        } catch (\Exception $e) {
            // Cache warming failure is non-critical
            error_log("Cache warming failed for {$certificateId}: " . $e->getMessage());
        }
    }
    
    private function getConfig() {
        return require __DIR__ . '/../config.php';
    }
}
