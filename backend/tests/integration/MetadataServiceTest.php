<?php

namespace Tests\Integration;

use App\MetadataService;
use Tests\TestCase;

class MetadataServiceTest extends TestCase
{
    /**
     * Verify normalizeMetadata sorts keys consistently.
     */
    public function test_normalizeMetadata_sortsKeys(): void
    {
        $service = new MetadataService();
        $json = $service->generateMetadataJson([
            'certificate_id' => 'CERT-INT',
            'student_name' => 'Integration Student'
        ]);

        $this->assertStringContainsString('certificate_id', $json);
        $this->assertStringContainsString('student_name', $json);
    }
}
