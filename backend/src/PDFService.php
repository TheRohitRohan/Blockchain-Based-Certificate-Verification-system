<?php

namespace App;

use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser as PdfParser;
use kornrunner\Keccak;
use PDO;

class PDFService
{
    private $db;
    private $config;
    private $metadataService;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->config = require __DIR__ . '/../config.php';
        $this->metadataService = new MetadataService();
        $this->ensureStorageDirectories();
    }

    private function ensureStorageDirectories()
    {
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
    public function generateCertificatePDF(string $certificateId, array $certificateData): string
    {
        // Get certificate data from database if not provided
        if (empty($certificateData)) {
            $certificateData = $this->getCertificateData($certificateId);
        }

        if (!$certificateData) {
            throw new \Exception("Certificate not found: {$certificateId}");
        }

        // Generate HTML content
        $htmlContent = $this->generateCertificateHTML($certificateData);

        // Create PDF
        // format: custom [width, height] in mm — landscape certificate dimensions.
        // Using a fixed 210×148mm (A5-L) avoids the bottom blank space that A4-L
        // produces when content does not fill the full 297mm height.
        // PDFA + PDFAauto: required for XMP metadata to be written to the binary.
        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => [297, 210],
            'orientation'   => 'L',
            'margin_left'   => 0,
            'margin_right'  => 0,
            'margin_top'    => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'PDFA'          => true,
            'PDFAauto'      => true,
        ]);

        [$css, $bodyHtml] = $this->splitHtmlForMpdf($htmlContent);

        if ($css !== '') {
            $mpdf->WriteHTML($css, HTMLParserMode::HEADER_CSS);
        }

        $mpdf->WriteHTML($bodyHtml, HTMLParserMode::HTML_BODY);

        // FIX 5: Inject XMP metadata during PDF generation (before Output)
        if (!empty($certificateData) && !empty($certificateData['certificate_id'])) {
            $metadataForXmp = $this->metadataService->buildMetadata([
                'certificate_id' => $certificateData['certificate_id'] ?? '',
                'student_id' => $certificateData['student_id'] ?? '',
                'student_name' => $certificateData['student_name'] ?? '',
                'course_name' => $certificateData['course_name'] ?? '',
                'degree_type' => $certificateData['degree_type'] ?? '',
                'issue_date' => $certificateData['issue_date'] ?? '',
                'university_code' => $certificateData['university_code'] ?? '',
                'university_name' => $certificateData['university_name'] ?? '',
            ]);
            $metadataJson = $this->metadataService->generateMetadataJson($metadataForXmp);
            $mpdf->SetAdditionalXmpRdf($this->buildXmpRdf($metadataJson));
        }

        // Generate filename
        $filename = $this->generateFileName($certificateData);
        $filepath = $this->config['storage']['pdf_path'] . $filename;

        // Save PDF to file
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

        // Store pdf_path in database
        $stmt = $this->db->prepare("
            UPDATE certificates SET pdf_path = ? WHERE certificate_id = ?
        ");
        $stmt->execute([$filename, $certificateId]);

        return $filepath;
    }

    /**
     * Embed XMP metadata into PDF (stub kept for CertificateService compatibility).
     * Real embedding now happens inside generateCertificatePDF() via SetAdditionalXmpRdf.
     */
    public function embedMetadata(string $pdfPath, array $metadata): bool
    {
        return true;
    }

    /**
     * Get full filesystem path to the PDF for a given certificate ID.
     * Returns null if no PDF is stored.
     */
    public function getPDFPath(string $certificateId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT pdf_path FROM certificates
            WHERE certificate_id = ? AND pdf_path IS NOT NULL
        ");
        $stmt->execute([$certificateId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result && $result['pdf_path']) {
            return $this->config['storage']['pdf_path'] . $result['pdf_path'];
        }
        return null;
    }

    /**
     * Extract certificate metadata from PDF.
     * Reads the XMP packet from the PDF binary directly — smalot/pdfparser
     * does not reliably surface custom XMP namespaces like cert:metadata.
     */
    public function extractMetadata(string $pdfPath): ?array
    {
        if (!file_exists($pdfPath)) {
            return null;
        }

        try {
            $binary = file_get_contents($pdfPath);

            // Method 1: Extract from CDATA block (written by our buildXmpRdf method)
            if (preg_match('/<cert:metadata><!\[CDATA\[(.*?)\]\]><\/cert:metadata>/s', $binary, $matches)) {
                $json = trim($matches[1]);
                $decoded = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($decoded)) {
                    return $this->metadataService->normalizeMetadata($decoded);
                }
            }

            // Method 2: Extract from non-CDATA escaped block (fallback for older format)
            if (preg_match('/<cert:metadata>(.*?)<\/cert:metadata>/s', $binary, $matches)) {
                $json = html_entity_decode(trim($matches[1]), ENT_XML1, 'UTF-8');
                $decoded = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($decoded)) {
                    return $this->metadataService->normalizeMetadata($decoded);
                }
            }

            return null;
        } catch (\Exception $e) {
            error_log("Failed to extract metadata from PDF: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract text from PDF (fallback when metadata is missing)
     */
    public function extractText(string $pdfPath): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($pdfPath);
            return $pdf->getText();
        }
        catch (\Exception $e) {
            error_log("Failed to extract text: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Calculate Keccak256 hash of PDF file
     */
    public function calculatePDFHash(string $pdfPath): string
    {
        if (!file_exists($pdfPath)) {
            throw new \Exception("PDF file not found: {$pdfPath}");
        }

        $pdfBinary = file_get_contents($pdfPath);
        return '0x' . Keccak::hash($pdfBinary, 256);
    }

    /**
     * Insert QR code into PDF
     */
    public function insertQRCode(string $pdfPath, string $qrCodePath, array $position = ['x' => 150, 'y' => 20]): bool
    {
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
        }
        catch (\Exception $e) {
            error_log("Failed to insert QR code: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Builds the inner rdf:Description block for mPDF's SetAdditionalXmpRdf().
     * Do NOT include the xpacket wrapper — mPDF adds that automatically.
     */
    private function buildXmpRdf(string $metadataJson): string
    {
        // Wrap JSON in CDATA so no character escaping is needed.
        // htmlspecialchars() breaks JSON (braces, quotes get entity-encoded),
        // producing invalid XML that mPDF silently rejects.
        // Decode JSON to extract student name for signer tag
        $metadata = json_decode($metadataJson, true) ?? [];
        $studentName = $metadata['student_name'] ?? 'Unknown';
        
        return '<rdf:Description rdf:about="" xmlns:cert="http://certificate.system/metadata/">'
            . '<cert:metadata><![CDATA[' . $metadataJson . ']]></cert:metadata>'
            . '<cert:signature>digital</cert:signature>'
            . '<cert:signer>' . htmlspecialchars($studentName, ENT_XML1, 'UTF-8') . '</cert:signer>'
            . '</rdf:Description>';
    }

    /**
     * Generate XMP metadata XML
     */
    private function generateXMPMetadata(string $metadataJson): string
    {
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
    private function getCertificateData(string $certificateId): ?array
    {
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
    private function generateCertificateHTML(array $certificate): string
    {
        $templatePath = __DIR__ . '/../templates/certificate_template.html';

        if (!file_exists($templatePath)) {
            throw new \Exception("Certificate template not found");
        }

        $html = file_get_contents($templatePath);

        // Generate QR code PNG file and get its full filesystem path for mPDF.
        // mPDF cannot load HTTP URLs — it must be given an absolute filesystem path.
        $qrFileName = $this->generateQRCodeFile($certificate['certificate_id']);
        $qrFilePath = $qrFileName
            ? $this->config['storage']['qr_path'] . $qrFileName
            : null;

        // Build the <img> tag once. The template's .qr-box div handles sizing.
        // Do NOT wrap this in another <img> in the replacements array below.
        $qrImgHtml = ($qrFilePath && file_exists($qrFilePath))
            ? '<img src="' . $qrFilePath . '" width="93" height="93">'
            : '';

        // Replace placeholders
        $replacements = [
            '{{UNIVERSITY_NAME}}' => htmlspecialchars($certificate['university_name']  ?? ''),
            '{{STUDENT_NAME}}'    => htmlspecialchars($certificate['student_name']     ?? ''),
            '{{COURSE_NAME}}'     => htmlspecialchars($certificate['course_name']      ?? ''),
            '{{DEGREE_TYPE}}'     => htmlspecialchars($certificate['degree_type']      ?? 'Certificate'),
            '{{ISSUE_DATE}}'      => date('F j, Y', strtotime($certificate['issue_date'] ?? 'now')),
            '{{CERTIFICATE_ID}}'  => htmlspecialchars($certificate['certificate_id']   ?? ''),
            '{{QR_CODE}}'         => $qrImgHtml,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /**
     * mPDF handles embedded styles reliably when CSS is passed separately.
     */
    private function splitHtmlForMpdf(string $html): array
    {
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
    private function generateQRCode(string $certificateId): string
    {
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
    private function createPlaceholderQR(string $filepath, string $text)
    {
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
     * Get QR code HTML — handles data URI, file path, or empty (FIX 4)
     */
    private function getQRCodeHTML(?string $qrBase64OrPath): string
    {
        if (empty($qrBase64OrPath)) {
            return '<div style="text-align:center;color:#999;font-size:10px;padding:5px;">QR unavailable</div>';
        }
        // If it's already a data URI, use directly
        if (str_starts_with($qrBase64OrPath, 'data:')) {
            return '<img src="' . htmlspecialchars($qrBase64OrPath) . '" alt="QR Code" style="width:100%;height:auto;">';
        }
        // If it's a file path, convert to base64
        if (file_exists($qrBase64OrPath)) {
            $data = 'data:image/png;base64,' . base64_encode(file_get_contents($qrBase64OrPath));
            return '<img src="' . $data . '" alt="QR Code" style="width:100%;height:auto;">';
        }
        return '<div style="text-align:center;color:#999;font-size:10px;">QR unavailable</div>';
    }

    /**
     * Get verification URL — points to the frontend (works with any QR scanner)
     */
    private function getVerificationURL(string $certificateId): string
    {
        // FIX 11: Point to frontend URL, not the API server
        return $this->config['app']['frontend_url'] . '/verify?certificate_id=' . urlencode($certificateId);
    }

    /**
     * Generate QR code file and return filename.
     * Public method for external callers (e.g., CertificateService) to retrieve QR filename
     * for database storage. If QR already exists, returns the filename without regenerating.
     */
    public function generateQRCodeFileName(string $certificateId): ?string
    {
        $qrFileName = 'qr_' . $certificateId . '.png';
        $qrFilePath = $this->config['storage']['qr_path'] . $qrFileName;

        // Check if QR already exists (don't regenerate)
        if (file_exists($qrFilePath)) {
            return $qrFileName;
        }

        // Generate new QR
        return $this->generateQRCodeFile($certificateId);
    }

    /**
     * Generate QR code as PNG file and return filename.
     * Used for embedding in generated PDFs via mPDF's file-based Image() method.
     * Returns null if generation fails (fallback to placeholder).
     * 
     * Note: File is saved to permanent storage, not temporary.
     * Caller should store the path in database for audit trail.
     */
    private function generateQRCodeFile(string $certificateId): ?string
    {
        try {
            $url = $this->getVerificationURL($certificateId);

            $qrCode = \Endroid\QrCode\QrCode::create($url)
                ->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
                ->setSize(200)
                ->setMargin(10)
                ->setForegroundColor(new \Endroid\QrCode\Color\Color(0, 0, 0))
                ->setBackgroundColor(new \Endroid\QrCode\Color\Color(255, 255, 255));

            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);

            // Create permanent QR file (not temporary - kept for audit trail)
            $qrFileName = 'qr_' . $certificateId . '.png';
            $qrFilePath = $this->config['storage']['qr_path'] . $qrFileName;

            // Write QR file
            file_put_contents($qrFilePath, $result->getString());

            return $qrFileName; // Return filename only (not full path) for database storage

        }
        catch (\Exception $e) {
            error_log('QR code generation failed: ' . $e->getMessage());
            return null; // Null indicates failure; template renders placeholder
        }
    }

    /**
     * Generate QR code as base64 data URI.
     * Legacy method - kept for backwards compatibility if needed.
     * Prefer generateQRCodeFile() for mPDF-generated PDFs.
     */
    private function generateQRCodeBase64(string $certificateId): string
    {
        try {
            $url = $this->getVerificationURL($certificateId);

            $qrCode = \Endroid\QrCode\QrCode::create($url)
                ->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'))
                ->setSize(200)
                ->setMargin(10)
                ->setForegroundColor(new \Endroid\QrCode\Color\Color(0, 0, 0))
                ->setBackgroundColor(new \Endroid\QrCode\Color\Color(255, 255, 255));

            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);

            return 'data:image/png;base64,' . base64_encode($result->getString());

        }
        catch (\Exception $e) {
            error_log('QR code generation failed: ' . $e->getMessage());
            return ''; // Empty string — template renders nothing rather than a broken pixel
        }
    }

    /**
     * Embed metadata into an existing PDF binary using XMP injection.
     * Used for uploaded PDFs that were not generated by this system.
     * For system-generated PDFs, metadata is already embedded via SetAdditionalXmpRdf.
     *
     * NOTE: This method modifies the PDF binary directly by inserting/replacing the XMP packet.
     * It is only needed for Flow 2 (university upload). Flow 1 uses mPDF's SetAdditionalXmpRdf.
     */
    public function embedMetadataIntoPDF(string $pdfPath, array $metadata): bool
    {
        try {
            $metadataJson = $this->metadataService->generateMetadataJson($metadata);
            $xmpBlock = $this->buildXmpRdf($metadataJson);

            $binary = file_get_contents($pdfPath);

            // If PDF already has an xpacket, replace the cert:metadata section
            if (strpos($binary, 'cert:metadata') !== false) {
                // FIX 2: Validate PDF has proper RDF structure before modifying
                // Regex replacement on binary PDF can corrupt the file if structure is unexpected
                if (strpos($binary, '<rdf:RDF') === false) {
                    error_log("PDF missing proper RDF structure - refusing to modify: {$pdfPath}");
                    return false;
                }
                
                $binary = preg_replace(
                    '/<cert:metadata>.*?<\/cert:metadata>/s',
                    '<cert:metadata><![CDATA[' . $metadataJson . ']]></cert:metadata>',
                    $binary
                );
            } else {
                // Inject a minimal XMP packet before %%EOF
                $xmpPacket = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
                    . '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
                    . $xmpBlock
                    . '</rdf:RDF></x:xmpmeta><?xpacket end="w"?>';

                // Insert before the last %%EOF
                $eofPos = strrpos($binary, '%%EOF');
                if ($eofPos !== false) {
                    $binary = substr($binary, 0, $eofPos) . $xmpPacket . "\n%%EOF";
                } else {
                    $binary .= $xmpPacket;
                }
            }

            return file_put_contents($pdfPath, $binary) !== false;
        } catch (\Exception $e) {
            error_log("Failed to embed metadata into PDF: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add QR code overlay to an existing PDF (Flow 2 — uploaded certificates).
     * Uses FPDI to import the existing page and overlay the QR image.
     * For Flow 1, QR is already embedded in the HTML template.
     */
    public function addQRCodeToExistingPDF(string $pdfPath, string $certificateId): bool
    {
        try {
            // Generate QR as a temp PNG file
            $qrBase64 = $this->generateQRCodeBase64($certificateId);
            if (empty($qrBase64)) {
                error_log("QR generation failed for {$certificateId}");
                return false;
            }

            // Save QR to temp file (FPDI needs a file path)
            $qrPngPath = $this->config['storage']['qr_path'] . 'qr_' . $certificateId . '.png';
            $qrData = base64_decode(str_replace('data:image/png;base64,', '', $qrBase64));
            file_put_contents($qrPngPath, $qrData);

            // Use FPDI to overlay QR on existing PDF
            $pdf = new \setasign\Fpdi\Fpdi();
            $pageCount = $pdf->setSourceFile($pdfPath);

            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $pdf->importPage($i);
                $size  = $pdf->getTemplateSize($tplId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);

                // Add QR to bottom-right of first page (adjust x/y as needed)
                if ($i === 1) {
                    $pdf->Image(
                        $qrPngPath,
                        $size['width'] - 45,   // x: 45mm from right
                        $size['height'] - 45,  // y: 45mm from bottom
                        35,                    // width: 35mm
                        35                     // height: 35mm
                    );
                }
            }

            $pdf->Output('F', $pdfPath);

            // Clean up temp QR file
            @unlink($qrPngPath);

            return true;
        } catch (\Exception $e) {
            error_log("Failed to add QR to PDF: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate filename
     */
    private function generateFileName(array $certificate): string
    {
        $studentName = preg_replace('/[^a-zA-Z0-9]/', '_', $certificate['student_name'] ?? 'student');
        $courseName = preg_replace('/[^a-zA-Z0-9]/', '_', $certificate['course_name'] ?? 'course');
        $date = date('Y-m-d', strtotime($certificate['issue_date'] ?? 'now'));

        return "{$certificate['certificate_id']}_{$studentName}_{$courseName}_{$date}.pdf";
    }
}