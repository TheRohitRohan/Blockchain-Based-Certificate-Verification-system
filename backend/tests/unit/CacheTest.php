<?php

namespace Tests\Unit;

use App\Cache;
use Tests\TestCase;

class CacheTest extends TestCase
{
    /**
     * Ensure cache stores and retrieves values with TTL.
     */
    public function test_setAndGet_returnsStoredValue(): void
    {
        $cache = Cache::getInstance();
        $cache->set('foo', ['bar' => 1], 5);

        $this->assertSame(['bar' => 1], $cache->get('foo'));
    }

    /**
     * Ensure delete removes cached entries.
     */
    public function test_delete_removesValue(): void
    {
        $cache = Cache::getInstance();
        $cache->set('tmp', 'value', 5);
        $this->assertTrue($cache->delete('tmp'));
        $this->assertNull($cache->get('tmp'));
    }

    /**
     * Ensure remember caches callback results.
     */
    public function test_remember_cachesCallbackResult(): void
    {
        $cache = Cache::getInstance();
        $first = $cache->remember('remember-key', fn() => 42, 5);
        $second = $cache->remember('remember-key', fn() => 99, 5);

        $this->assertSame(42, $first);
        $this->assertSame(42, $second);
    }
}
