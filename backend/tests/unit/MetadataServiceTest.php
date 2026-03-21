<?php

namespace Tests\Unit;

use App\MetadataService;
use Tests\TestCase;

class MetadataServiceTest extends TestCase
{
    /**
     * Ensure buildMetadata normalizes and trims fields.
     */
    public function test_buildMetadata_normalizesFields(): void
    {
        $service = new MetadataService();
        $metadata = $service->buildMetadata([
            'certificate_id' => ' CERT-123 ',
            'student_name' => ' Alice ',
            'issue_date' => '2024/01/01'
        ]);

        $this->assertSame('CERT-123', $metadata['certificate_id']);
        $this->assertSame('Alice', $metadata['student_name']);
        $this->assertSame('2024-01-01', $metadata['issue_date']);
    }

    /**
     * Ensure generateMetadataHash returns keccak prefixed hash.
     */
    public function test_generateMetadataHash_returnsKeccak(): void
    {
        $service = new MetadataService();
        $hash = $service->generateMetadataHash(['certificate_id' => 'CERT-1']);

        $this->assertStringStartsWith('0x', $hash);
        $this->assertSame(66, strlen($hash));
    }

    /**
     * Ensure compareMetadata detects differences.
     */
    public function test_compareMetadata_detectsDifferences(): void
    {
        $service = new MetadataService();
        $result = $service->compareMetadata(
            ['certificate_id' => 'A'],
            ['certificate_id' => 'B']
        );

        $this->assertFalse($result['matches']);
        $this->assertArrayHasKey('certificate_id', $result['differences']);
    }
}
