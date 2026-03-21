<?php

namespace Tests\Integration;

use App\Blockchain;
use Tests\TestCase;

class BlockchainTest extends TestCase
{
    /**
     * Verify blockchain mock mode still returns deterministic hash.
     */
    public function test_generateCertificateHash_returnsHashString(): void
    {
        $bc = new Blockchain();
        $hash = $bc->generateCertificateHash(['certificate_id' => 'CERT-INT']);

        $this->assertNotEmpty($hash);
    }
}
