<?php

namespace App;

use PDO;

class CertificateService {
    private $db;
    private $blockchain;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->blockchain = new Blockchain();
    }

    public function createCertificate($data) {
        try {
            $this->db->beginTransaction();

            // Get student info
            $stmt = $this->db->prepare("SELECT s.id, s.student_id, u.full_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
            $stmt->execute([$data['student_id']]);
            $student = $stmt->fetch();

            if (!$student) {
                throw new \Exception("Student not found");
            }

            // Generate certificate ID
            $certificateId = 'CERT-' . strtoupper(uniqid());

// Prepare certificate data for hashing
            $certificateData = [
                'certificate_id' => $certificateId,
                'student_name' => $student['full_name'],
                'university_name' => $student['university_name'] ?? 'Demo University',
                'course_name' => $data['course_name'],
                'degree_type' => $data['degree_type'] ?? null,
                'issue_date' => $data['issue_date']
            ];

            // Generate hash
            $certificateHash = $this->blockchain->generateCertificateHash($certificateData);

            // Store on blockchain
            $blockchainResult = $this->blockchain->issueCertificate($certificateData);

            // Insert into database
            $stmt = $this->db->prepare("
                INSERT INTO certificates 
                (certificate_id, student_id, university_id, course_name, degree_type, issue_date, certificate_hash, blockchain_tx_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

$stmt->execute([
                $certificateId,
                $data['student_id'],
                $data['university_id'],
                $data['course_name'],
                $data['degree_type'] ?? null,
                $data['issue_date'],
                $certificateHash,
                $blockchainResult['tx_hash'] ?? null
            ]);

$this->db->commit();

            // Generate PDF certificate
            try {
                $pdfGenerator = new PDFGenerator();
                $pdfFilename = $pdfGenerator->generateCertificatePDF($certificateId);
            } catch (\Exception $e) {
                // Log error but don't fail certificate creation
                error_log("PDF generation failed for {$certificateId}: " . $e->getMessage());
            }

            return [
                'success' => true,
                'certificate_id' => $certificateId,
                'certificate_hash' => $certificateHash,
                'tx_hash' => $blockchainResult['tx_hash'] ?? 'pending'
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getCertificate($certificateId) {
$stmt = $this->db->prepare("
            SELECT 
                c.*,
                s.student_id,
                u.full_name as student_name,
                un.name as university_name,
                CASE WHEN c.is_revoked = 1 THEN 1 ELSE 0 END as is_revoked
            FROM certificates c
            JOIN students s ON c.student_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN universities un ON c.university_id = un.id
            WHERE c.certificate_id = ? AND c.status = 'active'
        ");
        $stmt->execute([$certificateId]);
        return $stmt->fetch();
    }

    public function verifyCertificate($certificateId, $certificateHash = null) {
        $certificate = $this->getCertificate($certificateId);

        if (!$certificate) {
            return ['valid' => false, 'status' => 'not_found'];
        }

        if ($certificate['is_revoked']) {
            $this->logVerification($certificateId, 'revoked');
            return ['valid' => false, 'status' => 'revoked'];
        }

        // Verify hash if provided
        if ($certificateHash && $certificate['certificate_hash'] !== $certificateHash) {
            $this->logVerification($certificateId, 'invalid');
            return ['valid' => false, 'status' => 'invalid'];
        }

        // Verify on blockchain
        $blockchainValid = $this->blockchain->verifyCertificate($certificateId, $certificate['certificate_hash']);

        if (!$blockchainValid) {
            $this->logVerification($certificateId, 'invalid');
            return ['valid' => false, 'status' => 'invalid'];
        }

        $this->logVerification($certificateId, 'valid');
        return [
            'valid' => true,
            'status' => 'valid',
            'certificate' => $certificate
        ];
    }

    public function revokeCertificate($certificateId, $revokedBy) {
$stmt = $this->db->prepare("
            UPDATE certificates 
            SET status = 'revoked', revoked_at = NOW(), revoked_by = ?
            WHERE certificate_id = ?
        ");
        return $stmt->execute([$revokedBy, $certificateId]);
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

    private function logVerification($certificateId, $result) {
        $statusMap = [
            'valid' => 'valid',
            'invalid' => 'invalid',
            'revoked' => 'revoked',
            'not_found' => 'not_found'
        ];

        $stmt = $this->db->prepare("
            INSERT INTO verification_logs (certificate_id, verifier_ip, verification_method, verification_result)
            VALUES (?, ?, 'certificate_id', ?)
        ");
        $stmt->execute([
            $certificateId,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $statusMap[$result] ?? 'invalid'
        ]);
    }
}

