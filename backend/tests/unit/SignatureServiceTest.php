<?php

namespace Tests\Unit;

use App\SignatureService;
use Tests\TestCase;

class SignatureServiceTest extends TestCase
{
    /**
     * Ensure signPDF returns false when file is missing.
     */
    public function test_signPDF_withMissingFile_returnsFalse(): void
    {
        $service = new SignatureService();
        $this->assertFalse($service->signPDF('/missing.pdf', 1));
    }

    /**
     * Ensure verifySignature reports missing file clearly.
     */
    public function test_verifySignature_withMissingFile_returnsNotFoundResult(): void
    {
        $service = new SignatureService();
        $result = $service->verifySignature('/missing.pdf');

        $this->assertFalse($result['signed']);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not found', $result['message']);
    }
}
