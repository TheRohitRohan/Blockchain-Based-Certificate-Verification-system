<?php

namespace Tests\Integration;

use App\PublicVerificationService;
use Tests\TestCase;

class PublicVerificationServiceTest extends TestCase
{
    /**
     * Verify ID-based public check returns stored certificate payload.
     */
    public function test_verifyPublic_withCertificateId_returnsStoredCertificate(): void
    {
        $university = $this->seedUniversity();
        $user = $this->seedUser(['university_id' => $university['id']]);
        $student = $this->seedStudent($user['id'], $university['id']);
        $this->seedCertificate([
            'certificate_id' => 'CERT-PUB',
            'student_id' => $student['id'],
            'university_id' => $university['id'],
        ]);

        $service = new PublicVerificationService();
        $result = $service->verifyPublic('CERT-PUB', null);

        $this->assertTrue($result['success']);
        $this->assertSame('CERT-PUB', $result['certificate_id']);
        $this->assertNotEmpty($result['stored_certificate']);
    }
}
