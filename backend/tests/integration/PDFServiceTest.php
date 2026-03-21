<?php

namespace Tests\Integration;

use App\PDFService;
use Tests\TestCase;

class PDFServiceTest extends TestCase
{
    /**
     * Verify getPDFPath returns stored path for existing certificate.
     */
    public function test_getPDFPath_returnsFilesystemPath(): void
    {
        $this->seedCertificate([
            'certificate_id' => 'CERT-PATH',
            'pdf_path' => 'cert-path.pdf'
        ]);

        $service = new PDFService();
        $path = $service->getPDFPath('CERT-PATH');

        $this->assertStringContainsString('cert-path.pdf', (string) $path);
    }
}
