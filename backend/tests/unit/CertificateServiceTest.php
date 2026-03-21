<?php

namespace Tests\Unit;

use App\CertificateService;
use App\VerificationEngine;
use Tests\TestCase;

class CertificateServiceTest extends TestCase
{
    /**
     * Ensure verifyCertificate delegates to verification engine by ID.
     */
    public function test_verifyCertificate_withoutHash_delegatesToEngine(): void
    {
        $service = new CertificateService();
        $engine = $this->createMock(VerificationEngine::class);
        $engine->method('verifyByCertificateId')->willReturn(['valid' => false, 'status' => 'not_found']);

        $this->setPrivateProperty($service, 'verificationEngine', $engine);

        $result = $service->verifyCertificate('CERT-MISSING');
        $this->assertSame('not_found', $result['status']);
    }

    /**
     * Ensure verifyUploadedPDF rejects invalid upload arrays.
     */
    public function test_verifyUploadedPDF_withInvalidUpload_returnsError(): void
    {
        $service = new CertificateService();
        $result = $service->verifyUploadedPDF(['tmp_name' => '/missing', 'name' => 'cert.pdf']);

        $this->assertFalse($result['valid']);
        $this->assertSame('error', $result['status']);
    }

    private function setPrivateProperty(object $object, string $property, $value): void
    {
        $ref = new \ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
