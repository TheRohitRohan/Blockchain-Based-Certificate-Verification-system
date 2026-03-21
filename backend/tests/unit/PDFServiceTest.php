<?php

namespace Tests\Unit;

use App\PDFService;
use Tests\TestCase;

class PDFServiceTest extends TestCase
{
    /**
     * Ensure calculatePDFHash returns a keccak hash with 0x prefix.
     */
    public function test_calculatePDFHash_returnsKeccakHash(): void
    {
        $pdfPath = dirname(__DIR__) . '/storage/pdf/hashable.pdf';
        file_put_contents($pdfPath, 'sample-pdf-bytes');

        $service = new PDFService();
        $hash = $service->calculatePDFHash($pdfPath);

        $this->assertStringStartsWith('0x', $hash);
        $this->assertSame(66, strlen($hash));
    }

    /**
     * Ensure extractMetadata returns null for missing files.
     */
    public function test_extractMetadata_whenFileMissing_returnsNull(): void
    {
        $service = new PDFService();
        $this->assertNull($service->extractMetadata('/nonexistent.pdf'));
    }

    /**
     * Ensure extractMetadata reads cert metadata from XMP block.
     */
    public function test_extractMetadata_readsEmbeddedJson(): void
    {
        $pdfPath = dirname(__DIR__) . '/storage/pdf/meta.pdf';
        $metadataJson = '{"certificate_id":"CERT-XYZ","student_name":"Alice"}';
        $content = "<cert:metadata><![CDATA[{$metadataJson}]]></cert:metadata>";
        file_put_contents($pdfPath, $content);

        $service = new PDFService();
        $metadata = $service->extractMetadata($pdfPath);

        $this->assertSame('CERT-XYZ', $metadata['certificate_id']);
        $this->assertSame('Alice', $metadata['student_name']);
    }
}
