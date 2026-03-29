<?php

use PHPUnit\Framework\TestCase;
use App\Blockchain;

/**
 * Suite 2 — Unit: BlockchainHash
 *
 * Pure hash-logic tests — no DB, no network.
 */
class BlockchainHashTest extends TestCase
{
    private Blockchain $bc;

    protected function setUp(): void
    {
        // Non-strict: will NOT throw if blockchain is unreachable (we only need hash helpers)
        $this->bc = new Blockchain(false);
    }

    // ─────────────────────────────────────────────────────────────────

    public function testKeccak256HashFormat(): void
    {
        $hash = $this->bc->generateKeccak256Hash('hello world');

        $this->assertStringStartsWith('0x', $hash);
        $this->assertSame(66, strlen($hash));
    }

    public function testKeccak256IsDeterministic(): void
    {
        $a = $this->bc->generateKeccak256Hash('test');
        $b = $this->bc->generateKeccak256Hash('test');

        $this->assertSame($a, $b);
    }

    public function testGenerateCombinedHashFormat(): void
    {
        $hash = $this->bc->generateCombinedHash(
            '0xabcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234',
            '0xef567890ef567890ef567890ef567890ef567890ef567890ef567890ef567890'
        );

        $this->assertStringStartsWith('0x', $hash);
        $this->assertSame(66, strlen($hash));
    }

    public function testGenerateCombinedHashStrips0xBeforeHashing(): void
    {
        $withPrefix = $this->bc->generateCombinedHash('0xaabbcc', '0xddeeff');
        $noPrefix   = $this->bc->generateCombinedHash('aabbcc', 'ddeeff');

        $this->assertSame(
            $withPrefix,
            $noPrefix,
            'Combined hash must be identical regardless of 0x prefix'
        );
    }

    public function testGenerateCombinedHashDiffersWithDifferentInput(): void
    {
        $h1 = $this->bc->generateCombinedHash(
            '0xaaaa0000000000000000000000000000aaaa0000000000000000000000000000',
            '0xbbbb0000000000000000000000000000bbbb0000000000000000000000000000'
        );
        $h2 = $this->bc->generateCombinedHash(
            '0xcccc0000000000000000000000000000cccc0000000000000000000000000000',
            '0xdddd0000000000000000000000000000dddd0000000000000000000000000000'
        );

        $this->assertNotSame($h1, $h2);
    }

    public function testCombinedHashIsDeterministic(): void
    {
        $a = $this->bc->generateCombinedHash('0xaaaa', '0xbbbb');
        $b = $this->bc->generateCombinedHash('0xaaaa', '0xbbbb');

        $this->assertSame($a, $b);
    }
}
