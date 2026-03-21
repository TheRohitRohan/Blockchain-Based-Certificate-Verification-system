<?php

namespace Tests\Integration;

use App\Cache;
use Tests\TestCase;

class CacheTest extends TestCase
{
    /**
     * Verify cache flush clears stored keys.
     */
    public function test_flush_clearsCache(): void
    {
        $cache = Cache::getInstance();
        $cache->set('key', 'value', 60);
        $cache->flush();

        $this->assertNull($cache->get('key'));
    }
}
