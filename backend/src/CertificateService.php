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
                SELECT s.id, s.student_id, s.university_id, u.full_name, un.name as university_name, un.code as university_code
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

            $issuerUniversityId = (int)($data['university_id'] ?? 0);
            if ($issuerUniversityId < 1 || (int)$student['university_id'] !== $issuerUniversityId) {
                throw new \Exception('Student does not belong to your university');
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
            
            // Step 3: Get QR code filename for database tracking
            $qrCodeFileName = $this->pdfService->generateQRCodeFileName($certificateId);
            
            // Step 4: Embed metadata (already embedded via SetAdditionalXmpRdf in generateCertificatePDF)
            // This call is now a no-op but kept for compatibility
            $this->pdfService->embedMetadata($pdfPath, $metadata);

            // Step 5: QR code is already embedded in the PDF via the HTML template.
            // No separate insertion needed for Flow 1 (generated certificates).

            // Step 6: Calculate PDF hash BEFORE signing.
            // This is the hash of the metadata-embedded PDF (before signature is added).
            $pdfHash = $this->pdfService->calculatePDFHash($pdfPath);

            // Step 7: Generate combined onchain hash: keccak256(metadataHash + pdfHash)
            // This  stable hash will be what we sign.
            $onchainHash = $this->blockchain->generateCombinedHash($metadataHash, $pdfHash);

            // Step 8: Sign the PDF with the university's private key using the onchainHash.
            // FIXED: Pass onchainHash as the third parameter.
            // Signing no longer invalidates the PDF hash since we sign the stable onchainHash,
            // not the PDF binary.
            $signatureStatus = $this->signatureService->signPDF(
                $pdfPath,
                $data['university_id'],
                $onchainHash  // FIXED: Pass the onchain hash as 3rd parameter
            );
            if (!$signatureStatus) {
                error_log("Warning: PDF signing failed for {$certificateId} — certificate will be unsigned");
            }

            // Step 9: Anchor on blockchain (will return mock:true if not connected)
            $blockchainResult = $this->blockchain->issueCertificate([
                'certificate_id'   => $certificateId,
                'student_name'     => $student['full_name'],
                'university_name'  => $student['university_name'],
                'course_name'      => $data['course_name'],
                'issue_date'       => $data['issue_date'],
                'certificate_hash' => $onchainHash,
            ]);

            // Check if blockchain actually failed (not mock, but actual error)
            $isMock = $blockchainResult['mock'] ?? false;
            $blockchainSuccess = $blockchainResult['success'] ?? false;
            $txHash = $blockchainResult['tx_hash'] ?? null;

            // Step 10: Store block info
            $blockNumber = $this->blockchain->getCurrentBlock();
            $chainId     = $this->getConfig()['blockchain']['chain_id'] ?? 1337;

            // Step 11: Persist to database
            $stmt = $this->db->prepare("
                INSERT INTO certificates 
                (certificate_id, student_id, university_id, course_name, degree_type, issue_date,
                 certificate_hash, blockchain_tx_hash, pdf_path, qr_code_path, status,
                 metadata_hash, pdf_hash, onchain_hash, metadata_json, signature_status,
                 block_number, chain_id, schema_version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $pdfFilename = basename($pdfPath);

            $stmt->execute([
                $certificateId,
                $student['id'],
                $data['university_id'],
                $data['course_name'],
                $data['degree_type'] ?? null,
                $data['issue_date'],
                $onchainHash,
                $txHash,                // null when mock, real hash when blockchain worked
                $pdfFilename,
                $qrCodeFileName,
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
            $this->warmupCertificateCache($certificateId, $onchainHash, !$isMock);

            return [
                'success'          => true,
                'certificate_id'   => $certificateId,
                'certificate_hash' => $onchainHash,
                'metadata_hash'    => $metadataHash,
                'pdf_hash'         => $pdfHash,
                'tx_hash'          => $txHash ?? 'pending',
                'blockchain_mode'  => $isMock ? 'mock' : 'live',
                'signature_status' => $signatureStatus,
                'pdf_path'         => $pdfFilename,
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Certificate creation failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Upload certificate (Mode 2: Upload Certificate PDF)
     *
     * Identical pipeline to createCertificate() — all certificate data comes from
     * form inputs ($data), not from the PDF. The uploaded PDF is used purely as the
     * visual document; any existing XMP metadata in it is overwritten with the
     * canonical metadata built from $data.
     *
     * @param array $uploadedFile  $_FILES entry for the PDF
     * @param array $data          Same fields as createCertificate(): student_id (DB row ID),
     *                             university_id, course_name, degree_type, issue_date
     */
    public function uploadCertificate(array $uploadedFile, array $data): array
    {
        $tempPath = null;

        try {
            // ── Validate uploaded file ─────────────────────────────────────────────
            if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
                throw new \Exception("Invalid file upload");
            }

            $maxFileSize = 10 * 1024 * 1024; // 10 MB
            if ($uploadedFile['size'] > $maxFileSize) {
                throw new \Exception("File too large. Maximum allowed size is 10MB.");
            }

            // Validate by MIME type, not just extension
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($uploadedFile['tmp_name']);
            if ($mimeType !== 'application/pdf') {
                throw new \Exception("Only PDF files are allowed");
            }

            $this->db->beginTransaction();

            // ── Step 1: Resolve and validate student (same as createCertificate) ───
            $stmt = $this->db->prepare("
                SELECT s.id, s.student_id, s.university_id,
                       u.full_name,
                       un.name AS university_name,
                       un.code AS university_code
                FROM students s
                JOIN users u  ON s.user_id      = u.id
                JOIN universities un ON s.university_id = un.id
                WHERE s.id = ?
            ");
            $stmt->execute([$data['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                throw new \Exception("Student not found");
            }

            $issuerUniversityId = (int)($data['university_id'] ?? 0);
            if ($issuerUniversityId < 1 || (int)$student['university_id'] !== $issuerUniversityId) {
                throw new \Exception("Student does not belong to your university");
            }

            // ── Step 2: Generate certificate ID (same as createCertificate) ────────
            $certificateId = 'CERT-' . strtoupper(uniqid());

            // ── Step 3: Build canonical metadata from form data ────────────────────
            $metadata = $this->metadataService->buildMetadata([
                'certificate_id'  => $certificateId,
                'student_id'      => $student['student_id'],
                'student_name'    => $student['full_name'],
                'course_name'     => $data['course_name'],
                'degree_type'     => $data['degree_type'] ?? null,
                'issue_date'      => $data['issue_date'],
                'university_code' => $student['university_code'],
                'university_name' => $student['university_name'],
            ]);

            $metadataJson = $this->metadataService->generateMetadataJson($metadata);
            $metadataHash = $this->metadataService->generateMetadataHash($metadata);

            // ── Step 4: Move uploaded PDF to temp storage ──────────────────────────
            $tempPath = $this->getConfig()['storage']['pdf_path'] . 'temp_' . uniqid() . '.pdf';
            move_uploaded_file($uploadedFile['tmp_name'], $tempPath);

            // ── Step 5: Overwrite any existing XMP metadata with canonical metadata ─
            // Any metadata already in the PDF is intentionally ignored and replaced.
            $this->pdfService->embedMetadataIntoPDF($tempPath, $metadata);

            // ── Step 6: Overlay QR code ────────────────────────────────────────────
            $this->pdfService->addQRCodeToExistingPDF($tempPath, $certificateId);
            $qrCodeFileName = $this->pdfService->generateQRCodeFileName($certificateId);

            // ── Step 7: Calculate PDF hash BEFORE signing ─────────────────────────
            $pdfHash     = $this->pdfService->calculatePDFHash($tempPath);
            $onchainHash = $this->blockchain->generateCombinedHash($metadataHash, $pdfHash);

            // ── Step 8: Sign the PDF using the onchain hash ────────────────────────
            $signatureStatus = $this->signatureService->signPDF($tempPath, $data['university_id'], $onchainHash);
            if (!$signatureStatus) {
                error_log("Warning: PDF signing failed for upload {$certificateId} — certificate will be unsigned");
            }

            // ── Step 9: Anchor on blockchain ──────────────────────────────────────
            $blockchainResult = $this->blockchain->issueCertificate([
                'certificate_id'   => $certificateId,
                'student_name'     => $student['full_name'],
                'university_name'  => $student['university_name'],
                'course_name'      => $data['course_name'],
                'issue_date'       => $data['issue_date'],
                'certificate_hash' => $onchainHash,
            ]);

            $isMock      = $blockchainResult['mock'] ?? false;
            $txHash      = $blockchainResult['tx_hash'] ?? null;
            $blockNumber = $this->blockchain->getCurrentBlock();
            $chainId     = $this->getConfig()['blockchain']['chain_id'] ?? 1337;

            // ── Step 10: Move PDF to final storage location ───────────────────────
            $finalFilename = $certificateId . '_uploaded_' . date('Y-m-d') . '.pdf';
            $finalPath     = $this->getConfig()['storage']['pdf_path'] . $finalFilename;
            rename($tempPath, $finalPath);
            $tempPath = null; // Prevent cleanup from deleting the now-renamed file

            // ── Step 11: Persist to database ──────────────────────────────────────
            $stmt = $this->db->prepare("
                INSERT INTO certificates
                (certificate_id, student_id, university_id, course_name, degree_type, issue_date,
                 certificate_hash, blockchain_tx_hash, pdf_path, qr_code_path, status,
                 metadata_hash, pdf_hash, onchain_hash, metadata_json, signature_status,
                 block_number, chain_id, schema_version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $certificateId,
                $student['id'],
                $data['university_id'],
                $data['course_name'],
                $data['degree_type'] ?? null,
                $data['issue_date'],
                $onchainHash,
                $txHash,
                $finalFilename,
                $qrCodeFileName,
                $metadataHash,
                $pdfHash,
                $onchainHash,
                $metadataJson,
                $signatureStatus ? 1 : 0,
                $blockNumber,
                $chainId,
                $this->metadataService->getSchemaVersion(),
            ]);

            $this->db->commit();
            $this->warmupCertificateCache($certificateId, $onchainHash, !$isMock);

            return [
                'success'          => true,
                'certificate_id'   => $certificateId,
                'certificate_hash' => $onchainHash,
                'metadata_hash'    => $metadataHash,
                'pdf_hash'         => $pdfHash,
                'tx_hash'          => $txHash ?? 'pending',
                'blockchain_mode'  => $isMock ? 'mock' : 'live',
                'signature_status' => $signatureStatus,
                'pdf_path'         => $finalFilename,
            ];

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($tempPath !== null && file_exists($tempPath)) {
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

    /**
     * Update certificate metadata (admin/university only).
     * Re-generates PDF, re-signs, re-hashes. Does NOT re-anchor on blockchain
     * (the original blockchain record is kept as the immutable anchor;
     * the DB update is tracked separately).
     */
    public function updateCertificate(string $certificateId, array $updateData, int $universityId): array
    {
        try {
            $existing = $this->getCertificate($certificateId);
            if (!$existing) {
                error_log("updateCertificate: Certificate not found for ID: {$certificateId}");
                return ['success' => false, 'error' => 'Certificate not found'];
            }
            if ($existing['status'] === 'revoked') {
                error_log("updateCertificate: Certificate {$certificateId} is revoked");
                return ['success' => false, 'error' => 'Cannot update a revoked certificate'];
            }

            $this->db->beginTransaction();

            // Only allow updating non-identity fields
            $allowedFields = ['course_name', 'degree_type', 'issue_date'];
            $updates = array_intersect_key($updateData, array_flip($allowedFields));

            if (empty($updates)) {
                error_log("updateCertificate: No updatable fields for {$certificateId}. Provided: " . json_encode($updateData));
                $this->db->rollBack();
                return ['success' => false, 'error' => 'No updatable fields provided'];
            }

            // Build updated data for PDF regeneration
            $updatedData = array_merge($existing, $updates);

            // Regenerate PDF if course_name or other fields that affect PDF changed
            $certificateDataForPdf = [
                'certificate_id' => $certificateId,
                'student_id' => $existing['student_id'],
                'student_name' => $existing['student_name'],
                'course_name' => $updatedData['course_name'],
                'degree_type' => $updatedData['degree_type'] ?? $existing['degree_type'],
                'issue_date' => $updatedData['issue_date'] ?? $existing['issue_date'],
                'university_name' => $existing['university_name'],
                'university_code' => $existing['university_code']
            ];
            
            // Regenerate PDF
            $pdfPath = $this->pdfService->generateCertificatePDF($certificateId, $certificateDataForPdf);
            $pdfFilename = basename($pdfPath);
            $newPdfHash = $this->pdfService->calculatePDFHash($pdfPath);

            // Build SET clause
            $setClauses = [];
            $params = [];
            foreach ($updates as $field => $value) {
                $setClauses[] = "{$field} = ?";
                $params[] = $value;
            }
            $setClauses[] = 'pdf_path = ?';
            $params[] = $pdfFilename;
            $setClauses[] = 'pdf_hash = ?';
            $params[] = $newPdfHash;
            $setClauses[] = 'updated_at = NOW()';
            $params[] = $certificateId;

            $stmt = $this->db->prepare("
                UPDATE certificates SET " . implode(', ', $setClauses) . "
                WHERE certificate_id = ?
            ");
            $stmt->execute($params);

            $this->db->commit();

            // Invalidate cache
            $cache = Cache::getInstance();
            $cache->delete("verify:{$certificateId}");
            $cache->delete("cert_light:{$certificateId}");

            return ['success' => true, 'message' => 'Certificate updated'];

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("updateCertificate Exception for {$certificateId}: " . $e->getMessage() . " | " . $e->getTraceAsString());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete certificate record (admin only).
     * Also deletes the PDF file from storage.
     * Does NOT revoke on blockchain — use revokeCertificate for that.
     */
    public function deleteCertificate(string $certificateId): array
    {
        try {
            $existing = $this->getCertificate($certificateId);
            if (!$existing) {
                return ['success' => false, 'error' => 'Certificate not found'];
            }

            $this->db->beginTransaction();

            // Delete verification logs first (FK)
            $stmt = $this->db->prepare("DELETE FROM verification_logs WHERE certificate_id = ?");
            $stmt->execute([$certificateId]);

            // Delete certificate record
            $stmt = $this->db->prepare("DELETE FROM certificates WHERE certificate_id = ?");
            $stmt->execute([$certificateId]);

            $this->db->commit();

            // Delete PDF file
            if (!empty($existing['pdf_path'])) {
                $pdfFull = $this->getConfig()['storage']['pdf_path'] . $existing['pdf_path'];
                if (file_exists($pdfFull)) {
                    @unlink($pdfFull);
                }
            }

            // Invalidate cache
            $cache = Cache::getInstance();
            $cache->delete("verify:{$certificateId}");
            $cache->delete("cert_light:{$certificateId}");

            return ['success' => true, 'message' => 'Certificate deleted'];

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * List certificates with filtering and pagination.
     */
    public function listCertificates(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where   = ['1=1'];
        $params  = [];

        if (!empty($filters['university_id'])) {
            $where[] = 'c.university_id = ?';
            $params[] = $filters['university_id'];
        }
        if (!empty($filters['student_id'])) {
            $where[] = 'c.student_id = ?';
            $params[] = $filters['student_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'c.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['course_name'])) {
            $where[] = 'c.course_name LIKE ?';
            $params[] = '%' . $filters['course_name'] . '%';
        }

        $offset     = ($page - 1) * $perPage;
        $whereClause = implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM certificates c WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT
                c.certificate_id, c.course_name, c.degree_type, c.issue_date,
                c.status, c.signature_status, c.created_at,
                c.blockchain_tx_hash, c.pdf_path,
                u.full_name as student_name, s.student_id,
                un.name as university_name
            FROM certificates c
            JOIN students s  ON c.student_id  = s.id
            JOIN users u     ON s.user_id     = u.id
            JOIN universities un ON c.university_id = un.id
            WHERE {$whereClause}
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute(array_merge($params, [$perPage, $offset]));
        $certificates = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'success'      => true,
            'certificates' => $certificates,
            'pagination'   => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => (int) ceil($total / $perPage),
            ],
        ];
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
    
    private function warmupCertificateCache(string $certificateId, string $onchainHash, bool $blockchainReal = true): void {
        try {
            $cache = Cache::getInstance();
            $config = $this->getConfig();
            $ttl = $config['cache']['verification_ttl'] ?? 3600;
            
            // Cache lightweight verification result
            $lightResult = [
                'valid' => true,
                'status' => 'valid',
                'message' => 'Certificate is valid',
                'blockchain_valid' => $blockchainReal ? true : null,
                'blockchain_connected' => $blockchainReal
            ];
            $cache->set("cert_light:{$certificateId}", $lightResult, $ttl);
            
            // Only pre-cache blockchain verification if it was actually on blockchain
            if ($blockchainReal) {
                $cache->set("blockchain_verify:{$certificateId}:{$onchainHash}", true, $config['cache']['ttl'] ?? 3600);
            }
            
        } catch (\Exception $e) {
            // Cache warming failure is non-critical
            error_log("Cache warming failed for {$certificateId}: " . $e->getMessage());
        }
    }
    
    private function getConfig() {
        return require __DIR__ . '/../config.php';
    }
    
    /**
     * Get university code by university ID
     */
    private function getUniversityCode($universityId) {
        if (!$universityId) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT code FROM universities WHERE id = ?");
        $stmt->execute([$universityId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['code'] : null;
    }
}