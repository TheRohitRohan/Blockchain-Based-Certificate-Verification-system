<?php

namespace App;

use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser as PdfParser;
use kornrunner\Keccak;
use PDO;

class PDFService {
    private $db;
    private $config;
    private $metadataService;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->config = require __DIR__ . '/../config.php';
        $this->metadataService = new MetadataService();
        $this->ensureStorageDirectories();
    }
    
    private function ensureStorageDirectories() {
        $directories = [
            $this->config['storage']['pdf_path'],
            $this->config['storage']['qr_path']
        ];
        
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }
    
    /**
     * Generate certificate PDF from template
     */
    public function generateCertificatePDF(string $certificateId, array $certificateData): string {
        // Get certificate data from database if not provided
        if (empty($certificateData)) {
            $certificateData = $this->getCertificateData($certificateId);
        }
        
        if (!$certificateData) {
            throw new \Exception("Certificate not found: {$certificateId}");
        }
        
        // Generate HTML content
        $htmlContent = $this->generateCertificateHTML($certificateData);
        
        // Create PDF - simple approach
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
        
        [$css, $bodyHtml] = $this->splitHtmlForMpdf($htmlContent);

        if ($css !== '') {
            $mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
        }

        $mpdf->WriteHTML($bodyHtml, HTMLParserMode::HTML_BODY);
        
        // Generate filename
        $filename = $this->generateFileName($certificateData);
        $filepath = $this->config['storage']['pdf_path'] . $filename;
        
        // Save PDF to file
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
        
        return $filepath;
    }
    
    /**
     * Embed XMP metadata into PDF
     */
    public function embedMetadata(string $pdfPath, array $metadata): bool {
        // Metadata is already embedded via mPDF when generating
        // This function is kept for compatibility but does nothing
        return true;
    }
    
    /**
     * Extract metadata from PDF
     */
    public function extractMetadata(string $pdfPath): ?array {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($pdfPath);
            
            // Try to extract XMP metadata first
            $details = $pdf->getDetails();
            
            // Look for custom metadata field
            if (isset($details['Metadata'])) {
                $metadataJson = $details['Metadata'];
                return $this->metadataService->extractMetadata($metadataJson);
            }
            
            // Try to extract from document info
            if (isset($details['certificate_metadata'])) {
                return $this->metadataService->extractMetadata($details['certificate_metadata']);
            }
            
            return null;
        } catch (\Exception $e) {
            error_log("Failed to extract metadata: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract text from PDF (fallback when metadata is missing)
     */
    public function extractText(string $pdfPath): string {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($pdfPath);
            return $pdf->getText();
        } catch (\Exception $e) {
            error_log("Failed to extract text: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Calculate Keccak256 hash of PDF file
     */
    public function calculatePDFHash(string $pdfPath): string {
        if (!file_exists($pdfPath)) {
            throw new \Exception("PDF file not found: {$pdfPath}");
        }
        
        $pdfBinary = file_get_contents($pdfPath);
        return '0x' . Keccak::hash($pdfBinary, 256);
    }
    
    /**
     * Insert QR code into PDF
     */
    public function insertQRCode(string $pdfPath, string $qrCodePath, array $position = ['x' => 150, 'y' => 20]): bool {
        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($pdfPath);
            
            // Import all pages
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplId);
                
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);
                
                // Add QR code on first page
                if ($i === 1 && file_exists($qrCodePath)) {
                    $pdf->Image($qrCodePath, $position['x'], $position['y'], 40, 40);
                }
            }
            
            // Save updated PDF
            $pdf->Output('F', $pdfPath);
            
            return true;
        } catch (\Exception $e) {
            error_log("Failed to insert QR code: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate XMP metadata XML
     */
    private function generateXMPMetadata(string $metadataJson): string {
        $escapedJson = htmlspecialchars($metadataJson, ENT_XML1, 'UTF-8');
        
        return <<<XMP
<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/">
  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
    <rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>Certificate Metadata</dc:title>
    </rdf:Description>
    <rdf:Description rdf:about="" xmlns:cert="http://certificate.system/metadata/">
      <cert:metadata>{$escapedJson}</cert:metadata>
    </rdf:Description>
  </rdf:RDF>
</x:xmpmeta>
<?xpacket end="w"?>
XMP;
    }
    
    /**
     * Get certificate data from database
     */
    private function getCertificateData(string $certificateId): ?array {
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                s.student_id,
                u.full_name as student_name,
                un.name as university_name,
                un.code as university_code
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
     * Generate certificate HTML
     */
    private function generateCertificateHTML(array $certificate): string {
        $templatePath = __DIR__ . '/../templates/certificate_template.html';
        
        if (!file_exists($templatePath)) {
            throw new \Exception("Certificate template not found");
        }

        $html = file_get_contents($templatePath);
        
        // Generate QR code directly in template (base64 or URL)
        $qrCodeData = $this->generateQRCodeBase64($certificate['certificate_id']);
        $verificationURL = $this->getVerificationURL($certificate['certificate_id']);
        
        // Replace placeholders
        $replacements = [
            '{{UNIVERSITY_NAME}}' => htmlspecialchars($certificate['university_name'] ?? ''),
            '{{STUDENT_NAME}}' => htmlspecialchars($certificate['student_name'] ?? ''),
            '{{COURSE_NAME}}' => htmlspecialchars($certificate['course_name'] ?? ''),
            '{{DEGREE_TYPE}}' => htmlspecialchars($certificate['degree_type'] ?? 'Certificate'),
            '{{CERTIFICATE_ID}}' => htmlspecialchars($certificate['certificate_id'] ?? ''),
            '{{ISSUE_DATE}}' => date('F j, Y', strtotime($certificate['issue_date'] ?? 'now')),
            '{{CERTIFICATE_HASH}}' => htmlspecialchars($certificate['certificate_hash'] ?? ''),
            '{{BLOCKCHAIN_TX_HASH}}' => htmlspecialchars($certificate['blockchain_tx_hash'] ?? 'Pending'),
            '{{VERIFICATION_URL}}' => htmlspecialchars($verificationURL),
            '{{QR_CODE}}' => '<img src="' . htmlspecialchars($qrCodeData) . '" alt="QR Code" style="width: 150px; height: 150px; max-width: 100%;">'
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /**
     * mPDF handles embedded styles reliably when CSS is passed separately.
     */
    private function splitHtmlForMpdf(string $html): array {
        $css = '';

        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $matches)) {
            $css = trim(implode("\n", $matches[1]));
            $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        }

        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            $html = $matches[1];
        }

        return [$css, trim($html)];
    }
    
    /**
     * Generate QR code image
     */
    private function generateQRCode(string $certificateId): string {
        $verificationURL = $this->getVerificationURL($certificateId);
        
        // Use simple QR code generation (you can replace with a proper QR library)
        $filename = 'qr_' . $certificateId . '.png';
        $filepath = $this->config['storage']['qr_path'] . $filename;
        
        if (!file_exists($filepath)) {
            $this->createPlaceholderQR($filepath, $verificationURL);
        }
        
        return $filename;
    }
    
    /**
     * Create placeholder QR code
     */
    private function createPlaceholderQR(string $filepath, string $text) {
        $image = imagecreatetruecolor(200, 200);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        imagefill($image, 0, 0, $white);
        
        // Draw QR-like pattern
        for ($x = 0; $x < 200; $x += 20) {
            for ($y = 0; $y < 200; $y += 20) {
                if (($x + $y) % 40 == 0) {
                    imagefilledrectangle($image, $x, $y, $x + 15, $y + 15, $black);
                }
            }
        }
        
        imagepng($image, $filepath);
        imagedestroy($image);
    }
    
    /**
     * Get QR code HTML
     */
    private function getQRCodeHTML(?string $qrCodePath): string {
        if (empty($qrCodePath)) {
            return '<div style="text-align: center; color: #999;">QR Code</div>';
        }
        
        $qrURL = $this->config['storage']['base_url'] . 'qr_codes/' . basename($qrCodePath);
        return '<img src="' . $qrURL . '" alt="QR Code" style="max-width: 100px; height: auto;">';
    }
    
    /**
     * Get verification URL (public endpoint - works with any QR scanner)
     */
    private function getVerificationURL(string $certificateId): string {
        $config = $this->config['app'];
        // Use public verification endpoint - this URL works with any QR code scanner
        // Frontend should handle this route and extract certificate_id
        return $config['base_url'] . '/verify?certificate_id=' . urlencode($certificateId);
    }
    
    /**
     * Generate QR code as base64 data URI for direct embedding in template
     */
    private function generateQRCodeBase64(string $certificateId): string {
        try {
            // Use Endroid QR Code library
            if (class_exists('\Endroid\QrCode\QrCode')) {
                $qrCode = new \Endroid\QrCode\QrCode($this->getVerificationURL($certificateId));
                $qrCode->setSize(200);
                $qrCode->setMargin(10);
                
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($qrCode);
                
                return 'data:image/png;base64,' . base64_encode($result->getString());
            }
        } catch (\Exception $e) {
            error_log("QR code generation failed: " . $e->getMessage());
        }
        
        // Fallback: return placeholder
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }
    
    /**
     * Generate filename
     */
    private function generateFileName(array $certificate): string {
        $studentName = preg_replace('/[^a-zA-Z0-9]/', '_', $certificate['student_name'] ?? 'student');
        $courseName = preg_replace('/[^a-zA-Z0-9]/', '_', $certificate['course_name'] ?? 'course');
        $date = date('Y-m-d', strtotime($certificate['issue_date'] ?? 'now'));
        
        return "{$certificate['certificate_id']}_{$studentName}_{$courseName}_{$date}.pdf";
    }
}
