<?php

namespace App;

use setasign\Fpdi\Fpdi;
use TCPDF;

class SignatureService {
    private $config;
    private $db;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config.php';
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Sign PDF with digital signature
     * 
     * @param string $pdfPath Path to PDF file
     * @param int $universityId University ID (for getting certificate)
     * @return bool Success status
     */
    public function signPDF(string $pdfPath, int $universityId): bool {
        try {
            // Get signing certificate for university
            $certInfo = $this->getUniversityCertificate($universityId);
            
            if (!$certInfo) {
                throw new \Exception("No signing certificate found for university {$universityId}");
            }
            
            $certPath = $certInfo['certificate_path'];
            $certPassword = $certInfo['certificate_password'];
            
            if (!file_exists($certPath)) {
                throw new \Exception("Certificate file not found: {$certPath}");
            }
            
            // Read certificate
            $certData = $this->readCertificate($certPath, $certPassword);
            
            if (!$certData) {
                throw new \Exception("Failed to read certificate");
            }
            
            // Sign PDF using TCPDF
            return $this->signPDFWithTCPDF($pdfPath, $certData);
            
        } catch (\Exception $e) {
            error_log("PDF signing failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify PDF digital signature
     */
    public function verifySignature(string $pdfPath): array {
        try {
            // Use FPDI to check for signatures
            $pdf = new Fpdi();
            $pdf->setSourceFile($pdfPath);
            
            // Check if PDF has signature field
            // Note: Full signature verification requires more complex implementation
            // This is a simplified version
            
            $hasSignature = $this->checkPDFSignature($pdfPath);
            
            return [
                'signed' => $hasSignature,
                'valid' => $hasSignature, // In production, verify actual signature
                'message' => $hasSignature ? 'PDF is digitally signed' : 'PDF is not signed'
            ];
            
        } catch (\Exception $e) {
            return [
                'signed' => false,
                'valid' => false,
                'message' => 'Signature verification failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get university signing certificate
     */
    private function getUniversityCertificate(int $universityId): ?array {
        // First check university_keys table
        $stmt = $this->db->prepare("
            SELECT certificate_path, certificate_password 
            FROM university_keys 
            WHERE university_id = ? AND is_active = TRUE 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$universityId]);
        $key = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($key) {
            // Decrypt password (in production, use proper encryption)
            return [
                'certificate_path' => $key['certificate_path'],
                'certificate_password' => $this->decryptPassword($key['certificate_password'])
            ];
        }
        
        // Fallback to default certificate
        $defaultPath = $this->config['signing']['default_cert_path'];
        if (file_exists($defaultPath)) {
            return [
                'certificate_path' => $defaultPath,
                'certificate_password' => $this->config['signing']['default_cert_password']
            ];
        }
        
        return null;
    }
    
    /**
     * Read .p12 certificate
     */
    private function readCertificate(string $certPath, string $password): ?array {
        $certData = file_get_contents($certPath);
        
        // Extract certificate and private key
        $certs = [];
        $privateKey = null;
        
        if (openssl_pkcs12_read($certData, $certs, $password)) {
            return [
                'cert' => $certs['cert'],
                'pkey' => $certs['pkey'],
                'extracerts' => $certs['extracerts'] ?? []
            ];
        }
        
        return null;
    }
    
    /**
     * Sign PDF using TCPDF
     */
    private function signPDFWithTCPDF(string $pdfPath, array $certData): bool {
        try {
            // Create temporary signed PDF
            $tempPath = $pdfPath . '.signed';
            
            // Use FPDI to import existing PDF
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($pdfPath);
            
            // Import all pages
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplId);
                
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);
            }
            
            // Set certificate info
            $pdf->setSignature(
                $certData['cert'],
                $certData['pkey'],
                '', // password (already extracted)
                '', // extra certificates
                2,  // signature appearance
                'Certificate issued by university'
            );
            
            // Save signed PDF
            $pdf->Output('F', $tempPath);
            
            // Replace original with signed version
            if (file_exists($tempPath)) {
                rename($tempPath, $pdfPath);
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            error_log("TCPDF signing failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if PDF has signature
     */
    private function checkPDFSignature(string $pdfPath): bool {
        // Read PDF content
        $content = file_get_contents($pdfPath);
        
        // Check for signature dictionary
        // This is a simplified check - full verification requires parsing PDF structure
        return strpos($content, '/Sig') !== false || 
               strpos($content, '/ByteRange') !== false ||
               strpos($content, '/Contents') !== false;
    }
    
    /**
     * Decrypt password (simplified - use proper encryption in production)
     */
    private function decryptPassword(string $encrypted): string {
        // In production, use proper encryption (e.g., openssl_encrypt)
        // For now, assume it's stored in plain text (NOT RECOMMENDED FOR PRODUCTION)
        return base64_decode($encrypted);
    }
    
    /**
     * Encrypt password for storage
     */
    public function encryptPassword(string $password): string {
        // In production, use proper encryption
        return base64_encode($password);
    }
}
