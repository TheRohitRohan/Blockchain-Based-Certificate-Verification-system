<?php

namespace Tests\Unit;

use App\VerificationEngine;
use Tests\TestCase;

class VerificationEngineTest extends TestCase
{
    /**
     * Ensure verifyUploadedPDF flags invalid when metadata cannot be extracted.
     */
    public function test_verifyUploadedPDF_withNoMetadata_returnsInvalid(): void
    {
        $pdfPath = dirname(__DIR__) . '/storage/pdf/upload_missing_meta.pdf';
        file_put_contents($pdfPath, 'no metadata here');

        $engine = new VerificationEngine();
        $result = $engine->verifyUploadedPDF($pdfPath);

        $this->assertFalse($result['valid']);
        $this->assertSame('invalid', $result['status']);
    }

    /**
     * Ensure verifyByCertificateId returns not_found for unknown IDs.
     */
    public function test_verifyByCertificateId_withUnknownId_returnsNotFound(): void
    {
        $engine = new VerificationEngine();
        $result = $engine->verifyByCertificateId('CERT-UNKNOWN');

        $this->assertFalse($result['valid']);
        $this->assertSame('not_found', $result['status']);
    }
}
