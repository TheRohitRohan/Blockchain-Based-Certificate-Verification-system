<?php

namespace App;

use PDO;

class PublicVerificationService {
    private $db;
    private $verificationEngine;
    private $comparisonEngine;
    private $pdfService;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->verificationEngine = new VerificationEngine();
        $this->comparisonEngine = new ComparisonEngine();
        $this->pdfService = new PDFService();
        $this->config = require __DIR__ . '/../config.php';
    }
    
    /**
     * Public verification endpoint - handles PDF upload or certificate ID
     * 
     * @param string|null $certificateId Certificate ID (from QR code or manual entry)
     * @param array|null $uploadedFile Uploaded PDF file ($_FILES['certificate'])
     * @return array Complete verification result with comparison and stored certificate
     */
    public function verifyPublic(?string $certificateId = null, ?array $uploadedFile = null): array {
        try {
            // Case 1: PDF Upload
            if ($uploadedFile && isset($uploadedFile['tmp_name']) && is_uploaded_file($uploadedFile['tmp_name'])) {
                return $this->verifyWithUploadedPDF($uploadedFile);
            }
            
            // Case 2: Certificate ID only (from QR code or manual entry)
            if ($certificateId) {
                return $this->verifyWithCertificateId($certificateId);
            }
            
            // No input provided
            return [
                'success' => false,
                'error' => 'Please provide either a certificate PDF file or certificate ID',
                'message' => 'Upload a PDF certificate or enter certificate ID to verify'
            ];
            
        } catch (\Exception $e) {
            error_log("Public verification failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Verification failed',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify with uploaded PDF
     */
    private function verifyWithUploadedPDF(array $uploadedFile): array {
        // Validate file
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'error' => 'File upload error',
                'message' => 'Failed to upload certificate file'
            ];
        }
        
        // Check file type
        $fileInfo = pathinfo($uploadedFile['name']);
        if (strtolower($fileInfo['extension']) !== 'pdf') {
            return [
                'success' => false,
                'error' => 'Invalid file type',
                'message' => 'Only PDF files are supported'
            ];
        }
        
        // Move to temp location
        $tempPath = $this->config['storage']['pdf_path'] . 'verify_temp_' . uniqid() . '.pdf';
        if (!move_uploaded_file($uploadedFile['tmp_name'], $tempPath)) {
            return [
                'success' => false,
                'error' => 'File processing error',
                'message' => 'Failed to process uploaded file'
            ];
        }
        
        try {
            // Verify the uploaded PDF
            $verificationResult = $this->verificationEngine->verifyUploadedPDF($tempPath);
            
            // Extract certificate ID from verification result
            $certificateId = null;
            if (isset($verificationResult['extracted_metadata']['certificate_id'])) {
                $certificateId = $verificationResult['extracted_metadata']['certificate_id'];
            } elseif (isset($verificationResult['certificate']['certificate_id'])) {
                $certificateId = $verificationResult['certificate']['certificate_id'];
            }
            
            if (!$certificateId) {
                @unlink($tempPath);
                return [
                    'success' => false,
                    'error' => 'Certificate ID not found',
                    'message' => 'Could not extract certificate ID from uploaded PDF',
                    'verification' => $verificationResult
                ];
            }
            
            // Get stored certificate
            $storedCertificate = $this->getStoredCertificate($certificateId);
            
            // Perform detailed comparison
            $comparison = $this->comparisonEngine->comparePDFWithDatabase($tempPath, $certificateId);
            
            // Get stored PDF data
            $storedPdfData = $this->getStoredCertificatePDF($certificateId);
            
            // Cleanup temp file
            @unlink($tempPath);
            
            // Format differences with boolean matched
            $differences = $this->formatDifferences($comparison, $verificationResult);
            
            // Build response
            return [
                'success' => true,
                'verification_method' => 'pdf_upload',
                'matched' => $differences['matched'], // Boolean: true if uploaded PDF matches stored certificate
                'conclusion' => $this->buildConclusion($verificationResult, $comparison),
                'verification_result' => $verificationResult,
                'comparison' => $comparison,
                'uploaded_certificate' => [
                    'filename' => $uploadedFile['name'],
                    'size' => $uploadedFile['size']
                ],
                'stored_certificate' => $storedCertificate,
                'stored_certificate_pdf' => $storedPdfData,
                'differences' => $differences
            ];
            
        } catch (\Exception $e) {
            @unlink($tempPath);
            throw $e;
        }
    }
    
    /**
     * Verify with certificate ID only
     */
    private function verifyWithCertificateId(string $certificateId): array {
        // Verify certificate
        $verificationResult = $this->verificationEngine->verifyByCertificateId($certificateId);
        
        // Get stored certificate
        $storedCertificate = $this->getStoredCertificate($certificateId);
        
        if (!$storedCertificate) {
            return [
                'success' => false,
                'error' => 'Certificate not found',
                'message' => 'Certificate ID not found in system',
                'certificate_id' => $certificateId
            ];
        }
        
        // Get stored PDF data
        $storedPdfData = $this->getStoredCertificatePDF($certificateId);
        
        // Build response
        return [
            'success' => true,
            'verification_method' => 'certificate_id',
            'conclusion' => $this->buildConclusionFromId($verificationResult),
            'verification_result' => $verificationResult,
            'stored_certificate' => $storedCertificate,
            'stored_certificate_pdf' => $storedPdfData,
            'certificate_id' => $certificateId
        ];
    }
    
    /**
     * Get stored certificate record
     */
    private function getStoredCertificate(string $certificateId): ?array {
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
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
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($certificate) {
            // Parse metadata JSON if exists
            if (!empty($certificate['metadata_json'])) {
                $certificate['metadata'] = json_decode($certificate['metadata_json'], true);
            }
        }
        
        return $certificate;
    }
    
    /**
     * Get stored certificate PDF (as base64 and URL)
     * Public method for API access
     */
    public function getStoredCertificatePDF(string $certificateId): ?array {
        $certificate = $this->getStoredCertificate($certificateId);
        
        if (!$certificate || empty($certificate['pdf_path'])) {
            return null;
        }
        
        $pdfPath = $this->config['storage']['pdf_path'] . $certificate['pdf_path'];
        
        if (!file_exists($pdfPath)) {
            return null;
        }
        
        // Read PDF file
        $pdfContent = file_get_contents($pdfPath);
        $pdfBase64 = base64_encode($pdfContent);
        
        // Generate download URL
        $downloadUrl = $this->config['app']['api_url'] . '/public/certificate/download?certificate_id=' . urlencode($certificateId);
        
        return [
            'filename' => $certificate['pdf_path'],
            'size' => filesize($pdfPath),
            'base64' => $pdfBase64,
            'download_url' => $downloadUrl,
            'view_url' => $downloadUrl . '&view=1'
        ];
    }
    
    /**
     * Build conclusion from verification result (with PDF upload)
     */
    private function buildConclusion(array $verificationResult, array $comparison): array {
        $isValid = $verificationResult['valid'] ?? false;
        $status = $verificationResult['status'] ?? 'unknown';
        $metadataMatch = $comparison['metadata_match'] ?? false;
        $pdfHashMatch = $comparison['pdf_hash_match'] ?? false;
        
        $conclusion = [
            'overall_status' => $status,
            'is_valid' => $isValid,
            'summary' => '',
            'details' => []
        ];
        
        if ($status === 'revoked') {
            $conclusion['summary'] = 'Certificate has been revoked and is no longer valid';
            $conclusion['details'][] = 'This certificate was revoked by the issuing authority';
        } elseif ($status === 'not_found') {
            $conclusion['summary'] = 'Certificate not found in our system';
            $conclusion['details'][] = 'The certificate ID does not exist in our database';
        } elseif ($isValid && $metadataMatch && $pdfHashMatch) {
            $conclusion['summary'] = 'Certificate is valid and matches our records';
            $conclusion['details'][] = 'All metadata fields match';
            $conclusion['details'][] = 'PDF hash matches stored record';
            $conclusion['details'][] = 'Blockchain verification passed';
        } elseif ($isValid && !$metadataMatch) {
            $conclusion['summary'] = 'Certificate is valid but has some differences';
            $conclusion['details'][] = 'Certificate exists and is not revoked';
            $conclusion['details'][] = 'Some metadata fields do not match';
        } elseif ($isValid && !$pdfHashMatch) {
            $conclusion['summary'] = 'Certificate is valid but PDF content differs';
            $conclusion['details'][] = 'Certificate exists and is not revoked';
            $conclusion['details'][] = 'PDF file content does not match stored version';
        } else {
            $conclusion['summary'] = 'Certificate verification failed';
            $conclusion['details'][] = 'One or more verification checks failed';
        }
        
        return $conclusion;
    }
    
    /**
     * Build conclusion from certificate ID verification
     */
    private function buildConclusionFromId(array $verificationResult): array {
        $isValid = $verificationResult['valid'] ?? false;
        $status = $verificationResult['status'] ?? 'unknown';
        
        $conclusion = [
            'overall_status' => $status,
            'is_valid' => $isValid,
            'summary' => '',
            'details' => []
        ];
        
        if ($status === 'revoked') {
            $conclusion['summary'] = 'Certificate has been revoked';
            $conclusion['details'][] = 'This certificate is no longer valid';
        } elseif ($status === 'not_found') {
            $conclusion['summary'] = 'Certificate not found';
            $conclusion['details'][] = 'Certificate ID does not exist in our system';
        } elseif ($isValid) {
            $conclusion['summary'] = 'Certificate is valid';
            $conclusion['details'][] = 'Certificate exists in our system';
            $conclusion['details'][] = 'Certificate is active and not revoked';
            $conclusion['details'][] = 'Blockchain verification passed';
        } else {
            $conclusion['summary'] = 'Certificate verification failed';
            $conclusion['details'][] = 'Verification checks did not pass';
        }
        
        return $conclusion;
    }
    
    /**
     * Format differences for easy display
     * Returns boolean matched and explains what's different in uploaded PDF
     */
    private function formatDifferences(array $comparison, array $verificationResult): array {
        // Determine overall match status
        $metadataMatch = $comparison['metadata_match'] ?? false;
        $pdfHashMatch = $comparison['pdf_hash_match'] ?? false;
        $signatureValid = isset($verificationResult['signature']) && 
                         ($verificationResult['signature']['signed'] ?? false) && 
                         ($verificationResult['signature']['valid'] ?? false);
        
        $matched = $metadataMatch && $pdfHashMatch && $signatureValid;
        
        $differences = [
            'matched' => $matched, // Boolean: true if everything matches
            'has_differences' => !$matched,
            'uploaded_pdf_differences' => [], // What's different in uploaded PDF
            'metadata_differences' => [],
            'field_comparison' => [],
            'summary' => []
        ];
        
        // Explain what's different in uploaded PDF
        if (!$metadataMatch) {
            $differences['uploaded_pdf_differences'][] = 'Metadata in uploaded PDF does not match stored certificate';
            
            if (isset($comparison['metadata_differences']) && !empty($comparison['metadata_differences'])) {
                foreach ($comparison['metadata_differences'] as $field => $diff) {
                    $differences['metadata_differences'][$field] = [
                        'field_name' => $this->getFieldDisplayName($field),
                        'stored_value' => $diff['expected'] ?? 'N/A',
                        'uploaded_pdf_value' => $diff['actual'] ?? 'N/A',
                        'explanation' => sprintf(
                            "The uploaded PDF has '%s' as '%s', but our stored certificate has '%s'",
                            $this->getFieldDisplayName($field),
                            $diff['actual'] ?? 'N/A',
                            $diff['expected'] ?? 'N/A'
                        )
                    ];
                    
                    $differences['summary'][] = sprintf(
                        "Field '%s': Uploaded PDF has '%s', but stored certificate has '%s'",
                        $this->getFieldDisplayName($field),
                        $diff['actual'] ?? 'N/A',
                        $diff['expected'] ?? 'N/A'
                    );
                }
            }
        }
        
        // Field-by-field comparison
        if (isset($comparison['field_matches'])) {
            $differences['field_comparison'] = [];
            
            foreach ($comparison['field_matches'] as $field => $match) {
                $fieldInfo = [
                    'field_name' => $this->getFieldDisplayName($field),
                    'matched' => $match['match'],
                    'stored_value' => $match['db_value'] ?? null,
                    'uploaded_pdf_value' => $match['pdf_value'] ?? null
                ];
                
                if (!$match['match']) {
                    $fieldInfo['explanation'] = sprintf(
                        "The uploaded PDF shows '%s' as '%s', but our stored certificate has '%s'",
                        $this->getFieldDisplayName($field),
                        $match['pdf_value'] ?? 'N/A',
                        $match['db_value'] ?? 'N/A'
                    );
                    $differences['uploaded_pdf_differences'][] = $fieldInfo['explanation'];
                }
                
                $differences['field_comparison'][$field] = $fieldInfo;
            }
        }
        
        // PDF hash mismatch
        if (!$pdfHashMatch) {
            $differences['uploaded_pdf_differences'][] = 'PDF file content does not match stored certificate';
            $differences['pdf_hash'] = [
                'matched' => false,
                'stored_hash' => $comparison['pdf_hash_db'] ?? null,
                'uploaded_pdf_hash' => $comparison['pdf_hash_calculated'] ?? null,
                'explanation' => 'The uploaded PDF file content is different from our stored certificate. This could mean the PDF was modified or is a different version.'
            ];
            $differences['summary'][] = 'PDF file content does not match stored certificate';
        } else {
            $differences['pdf_hash'] = [
                'matched' => true,
                'explanation' => 'PDF file content matches stored certificate'
            ];
        }
        
        // Signature status
        if (isset($verificationResult['signature'])) {
            $signature = $verificationResult['signature'];
            if (!$signature['signed']) {
                $differences['uploaded_pdf_differences'][] = 'Uploaded PDF is not digitally signed';
                $differences['signature'] = [
                    'signed' => false,
                    'valid' => false,
                    'explanation' => 'The uploaded PDF does not have a digital signature'
                ];
                $differences['summary'][] = 'Uploaded PDF is not digitally signed';
            } elseif (!$signature['valid']) {
                $differences['uploaded_pdf_differences'][] = 'Digital signature in uploaded PDF is invalid';
                $differences['signature'] = [
                    'signed' => true,
                    'valid' => false,
                    'explanation' => 'The uploaded PDF has a digital signature, but it is invalid or has been tampered with'
                ];
                $differences['summary'][] = 'Digital signature in uploaded PDF is invalid';
            } else {
                $differences['signature'] = [
                    'signed' => true,
                    'valid' => true,
                    'explanation' => 'Digital signature is valid'
                ];
            }
        }
        
        // Overall explanation
        if ($matched) {
            $differences['explanation'] = 'The uploaded PDF matches our stored certificate in all aspects. All metadata fields, PDF content, and digital signature are correct.';
        } else {
            $differences['explanation'] = 'The uploaded PDF has differences compared to our stored certificate. ' . 
                                        implode(' ', array_slice($differences['uploaded_pdf_differences'], 0, 3));
        }
        
        return $differences;
    }
    
    /**
     * Get human-readable field name
     */
    private function getFieldDisplayName(string $field): string {
        $fieldNames = [
            'certificate_id' => 'Certificate ID',
            'student_id' => 'Student ID',
            'student_name' => 'Student Name',
            'course_name' => 'Course Name',
            'degree_type' => 'Degree Type',
            'issue_date' => 'Issue Date',
            'university_code' => 'University Code',
            'university_name' => 'University Name'
        ];
        
        return $fieldNames[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
