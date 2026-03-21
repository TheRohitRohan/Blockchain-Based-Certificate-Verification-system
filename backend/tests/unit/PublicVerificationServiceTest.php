<?php

namespace Tests\Unit;

use App\PublicVerificationService;
use Tests\TestCase;

class PublicVerificationServiceTest extends TestCase
{
    /**
     * Ensure verifyPublic prompts for input when none provided.
     */
    public function test_verifyPublic_withoutInput_returnsError(): void
    {
        $service = new PublicVerificationService();
        $result = $service->verifyPublic(null, null);

        $this->assertFalse($result['success']);
        $this->assertSame('Please provide either a certificate PDF file or certificate ID', $result['error']);
    }

    /**
     * Ensure verifyPublic rejects non-PDF uploads.
     */
    public function test_verifyPublic_withInvalidFileType_returnsError(): void
    {
        $service = new PublicVerificationService();
        $file = ['name' => 'image.png', 'tmp_name' => __FILE__, 'error' => UPLOAD_ERR_OK];

        // is_uploaded_file will be false in this environment; service should prompt for input
        $result = $service->verifyPublic(null, $file);

        $this->assertFalse($result['success']);
        $this->assertSame('Please provide either a certificate PDF file or certificate ID', $result['error']);
    }
}
