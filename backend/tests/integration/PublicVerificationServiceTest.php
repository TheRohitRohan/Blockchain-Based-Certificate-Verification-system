<?php

use PHPUnit\Framework\TestCase;
use App\PublicVerificationService;
use App\VerificationEngine;

/**
 * Suite 3 — Integration: PublicVerificationService
 *
 * Depends on: certificate from CertificateServiceTest.
 */
class PublicVerificationServiceTest extends TestCase
{
    private static PublicVerificationService $svc;

    public static function setUpBeforeClass(): void
    {
        self::$svc = new PublicVerificationService();
    }

    // ─── 1. Verify by certificate ID → valid ─────────────────────────

    public function testVerifyPublicByCertificateIdReturnsValid(): void
    {
        $result = self::$svc->verifyPublic(TestState::$certificateId, null);

        $this->assertTrue($result['success'], 'Expected success=true');
        $this->assertTrue($result['conclusion']['is_valid'], 'conclusion.is_valid should be true');
        $this->assertSame('valid', $result['conclusion']['overall_status']);
    }

    // ─── 2. Verify via VerificationEngine directly (upload workaround) ─

    public function testVerifyPublicWithSimulatedFileUpload(): void
    {
        /*
         * NOTE: PublicVerificationService::verifyPublic() checks is_uploaded_file()
         * which will return false for a non-HTTP-uploaded file. This is a PHP
         * SAPI limitation — there is no way to simulate a real upload in a CLI test.
         *
         * WORKAROUND: Call VerificationEngine::verifyUploadedPDF() directly, which
         * performs the same verification without the is_uploaded_file() guard.
         */
        $this->assertFileExists(TestState::$pdfPath);

        $engine = new VerificationEngine();
        $result = $engine->verifyUploadedPDF(TestState::$pdfPath);

        $this->assertTrue($result['valid'], 'Direct PDF verification should return valid=true');
    }

    // ─── 3. Fake certificate ID → not found ──────────────────────────

    public function testVerifyPublicWithFakeIdReturnsNotFound(): void
    {
        $result = self::$svc->verifyPublic('CERT-FAKE-XYZ-000', null);

        // Service returns success=false when certificate is not found
        $this->assertFalse($result['success']);
    }

    // ─── 4. getStoredCertificatePDF returns base64 ───────────────────

    public function testGetStoredCertificatePDFReturnsBase64(): void
    {
        $result = self::$svc->getStoredCertificatePDF(TestState::$certificateId);

        $this->assertNotNull($result, 'getStoredCertificatePDF returned null');
        $this->assertArrayHasKey('base64', $result);
        $this->assertNotEmpty($result['base64']);

        // Decode and verify it's a real PDF
        $decoded = base64_decode($result['base64']);
        $this->assertStringStartsWith('%PDF', $decoded, 'Decoded base64 is not a valid PDF');
    }
}
