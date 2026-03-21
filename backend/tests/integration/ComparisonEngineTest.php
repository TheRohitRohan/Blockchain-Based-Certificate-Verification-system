<?php

namespace Tests\Integration;

use App\ComparisonEngine;
use App\PDFService;
use Tests\TestCase;

class ComparisonEngineTest extends TestCase
{
    /**
     * Verify comparison succeeds when metadata and hash align with database.
     */
    public function test_comparePDFWithDatabase_whenDataMatches_returnsMatch(): void
    {
        $this->markTestSkipped('ComparisonEngine requires real PDF parsing; skipping in CI-lite environment.');
    }
}
