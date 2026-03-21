<?php

namespace Tests\Integration;

use App\VerificationEngine;
use Tests\TestCase;

class VerificationEngineTest extends TestCase
{
    /**
     * Verify ID-based lookup returns invalid when blockchain check fails.
     */
    public function test_verifyByCertificateId_withStoredRecord_returnsInvalid(): void
    {
        $university = $this->seedUniversity();
        $user = $this->seedUser(['university_id' => $university['id']]);
        $student = $this->seedStudent($user['id'], $university['id']);
        $this->seedCertificate([
            'certificate_id' => 'CERT-VE',
            'student_id' => $student['id'],
            'university_id' => $university['id'],
            'onchain_hash' => '',
            'pdf_hash' => '0xabc',
        ]);

        $engine = new VerificationEngine();
        $result = $engine->verifyByCertificateId('CERT-VE', '0xabc');

        $this->assertFalse($result['valid']);
        $this->assertSame('invalid', $result['status']);
    }
}
