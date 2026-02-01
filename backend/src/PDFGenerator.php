<?php

namespace App;

use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use PDO;

class PDFGenerator {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->config = require __DIR__ . '/../config.php';
        
        // Ensure storage directories exist
        $this->ensureStorageDirectories();
    }
    
    private function ensureStorageDirectories() {
        $directories = [
            $this->config['storage']['pdf_path'],
            $this->config['storage']['qr_path']
        ];
        
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new \Exception("Failed to create directory: {$directory}");
                }
            }
        }
    }
    
    public function generateCertificatePDF($certificateId): string {
        try {
            // Get certificate data from database
            $certificate = $this->getCertificateData($certificateId);
            
            if (!$certificate) {
                throw new \Exception("Certificate not found: {$certificateId}");
            }
            
            // Generate QR code if not exists
            if (empty($certificate['qr_code_path'])) {
                $verificationURL = $this->getVerificationURL($certificateId);
                $qrFilename = $this->generateQRCode($certificate);
                
                if ($qrFilename) {
                    $certificate['qr_code_path'] = $qrFilename;
                    $this->updateQRCodePath($certificateId, $qrFilename);
                }
            }
            
            // Generate HTML content
            $htmlContent = $this->generateCertificateHTML($certificate);
            
            // Create PDF
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
            ]);
            
            // Write HTML to PDF
            $mpdf->WriteHTML($htmlContent, HTMLParserMode::HTML_BODY);
            
            // Generate filename
            $filename = $this->generateFileName($certificate);
            $filepath = $this->config['storage']['pdf_path'] . $filename;
            
            // Save PDF to file
            $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
            
            // Update database with PDF path
            $this->updatePDFPath($certificateId, $filename);
            
            return $filename;
            
        } catch (\Exception $e) {
            error_log("PDF generation failed for {$certificateId}: " . $e->getMessage());
            throw new \Exception("PDF generation failed: " . $e->getMessage());
        }
    }
    
    private function getCertificateData($certificateId): ?array {
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                s.student_id,
                u.full_name as student_name,
                un.name as university_name
            FROM certificates c
            JOIN students s ON c.student_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN universities un ON c.university_id = un.id
            WHERE c.certificate_id = ?
        ");
        $stmt->execute([$certificateId]);
        $certificate = $stmt->fetch();
        
        if ($certificate) {
            // Ensure all required fields are present
            $certificate['student_name'] = $certificate['student_name'] ?? 'Unknown Student';
            $certificate['university_name'] = $certificate['university_name'] ?? 'Demo University';
            $certificate['course_name'] = $certificate['course_name'] ?? 'Unknown Course';
            $certificate['degree_type'] = $certificate['degree_type'] ?? 'Certificate';
            $certificate['issue_date'] = $certificate['issue_date'] ?? date('Y-m-d');
            $certificate['certificate_id'] = $certificate['certificate_id'] ?? $certificateId;
        }
        
        return $certificate;
    }
    
    public function generateCertificateHTML($certificate): string {
        $templatePath = __DIR__ . '/../templates/certificate_template.html';
        
        if (!file_exists($templatePath)) {
            throw new \Exception("Certificate template not found");
        }

        $html = file_get_contents($templatePath);
        
        // Replace placeholders with actual data
        $replacements = [
            '{{UNIVERSITY_NAME}}' => htmlspecialchars($certificate['university_name']),
            '{{STUDENT_NAME}}' => htmlspecialchars($certificate['student_name']),
            '{{COURSE_NAME}}' => htmlspecialchars($certificate['course_name']),
            '{{DEGREE_TYPE}}' => htmlspecialchars($certificate['degree_type'] ?? 'Certificate'),
            '{{CERTIFICATE_ID}}' => htmlspecialchars($certificate['certificate_id']),
            '{{ISSUE_DATE}}' => date('F j, Y', strtotime($certificate['issue_date'])),
            '{{CERTIFICATE_HASH}}' => htmlspecialchars($certificate['certificate_hash']),
            '{{BLOCKCHAIN_TX_HASH}}' => htmlspecialchars($certificate['blockchain_tx_hash'] ?? 'Pending'),
            '{{VERIFICATION_URL}}' => $this->getVerificationURL($certificate['certificate_id']),
            '{{QR_CODE}}' => $this->getQRCodeHTML($certificate['qr_code_path'] ?? '')
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }
    
    private function generateQRCode($certificate): string {
        $verificationURL = $this->getVerificationURL($certificate['certificate_id']);
        
        // Generate QR code using QR Code library (simple base64 approach)
        $qrData = $this->generateQRImageData($verificationURL);
        
        // Save QR code image
        $filename = 'qr_' . $certificate['certificate_id'] . '.png';
        $filepath = $this->config['storage']['qr_path'] . $filename;
        
        // Generate QR code if not exists
        if (empty($certificate['qr_code_path'])) {
            // Create placeholder QR code for now
            $this->createPlaceholderQR($filepath, $verificationURL);
            $certificate['qr_code_path'] = $filepath;
        }
        
        return $filename;
    }
    
    private function generateQRImageData($data): string {
        // This is a placeholder - in production use a real QR code library
        return base64_encode($data);
    }
    
    private function createPlaceholderQR($filepath, $text) {
        // Create a simple placeholder QR code image
        $image = imagecreatetruecolor(200, 200);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        imagefill($image, 0, 0, $white);
        
        // Draw some placeholder pattern
        for ($x = 0; $x < 200; $x += 20) {
            for ($y = 0; $y < 200; $y += 20) {
                if (($x + $y) % 40 == 0) {
                    imagefilledrectangle($image, $x, $y, $x + 15, $y + 15, $black);
                }
            }
        }
        
        // Add text center
        $textColor = imagecolorallocate($image, 100, 100, 100);
        imagestring($image, 2, 50, 95, 'QR Code', $textColor);
        
        imagepng($image, $filepath);
        imagedestroy($image);
    }
    
    private function getQRCodeHTML($qrCodePath): string {
        if (empty($qrCodePath)) {
            return '<div style="text-align: center; color: #999;">QR Code</div>';
        }
        
        $qrURL = $this->config['storage']['base_url'] . 'qr_codes/' . basename($qrCodePath);
        return '<img src="' . $qrURL . '" alt="QR Code" style="max-width: 100%; height: auto;">';
    }
    
    private function getVerificationURL($certificateId): string {
        $config = $this->config['app'];
        return $config['base_url'] . '/verify?certificate_id=' . urlencode($certificateId);
    }
    
    private function generateFileName($certificate): string {
        $studentName = preg_replace('/[^a-zA-Z0-9]/', '_', $certificate['student_name']);
        $courseName = preg_replace('/[^a-zA-Z0-9]/', '_', $certificate['course_name']);
        $date = date('Y-m-d', strtotime($certificate['issue_date']));
        
        return "{$certificate['certificate_id']}_{$studentName}_{$courseName}_{$date}.pdf";
    }
    
    private function updatePDFPath($certificateId, $filename) {
        $stmt = $this->db->prepare("
            UPDATE certificates 
            SET pdf_path = ? 
            WHERE certificate_id = ?
        ");
        $stmt->execute([$filename, $certificateId]);
    }
    
    private function updateQRCodePath($certificateId, $qrCodePath) {
        $stmt = $this->db->prepare("
            UPDATE certificates 
            SET qr_code_path = ? 
            WHERE certificate_id = ?
        ");
        $stmt->execute([$qrCodePath, $certificateId]);
    }
    
    public function deletePDF($certificateId): bool {
        try {
            $pdfPath = $this->getPDFPath($certificateId);
            
            if ($pdfPath && file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            
            // Update database
            $stmt = $this->db->prepare("
                UPDATE certificates 
                SET pdf_path = NULL 
                WHERE certificate_id = ?
            ");
            return $stmt->execute([$certificateId]);
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function getPDFPath($certificateId): ?string {
        $stmt = $this->db->prepare("
            SELECT pdf_path 
            FROM certificates 
            WHERE certificate_id = ? AND pdf_path IS NOT NULL
        ");
        $stmt->execute([$certificateId]);
        $result = $stmt->fetch();
        
        if ($result && $result['pdf_path']) {
            return $this->config['storage']['pdf_path'] . $result['pdf_path'];
        }
        
        return null;
    }
}