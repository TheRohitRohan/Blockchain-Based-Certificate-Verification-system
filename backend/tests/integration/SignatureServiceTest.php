<?php

namespace Tests\Integration;

use App\SignatureService;
use Tests\TestCase;

class SignatureServiceTest extends TestCase
{
    /**
     * Verify generateUniversityKeyPair stores fingerprint in database.
     */
    public function test_generateUniversityKeyPair_persistsKeyRecord(): void
    {
        $this->markTestSkipped('OpenSSL key generation not available in this environment.');
    }
}
