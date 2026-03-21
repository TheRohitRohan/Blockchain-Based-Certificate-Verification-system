<?php

namespace Tests\Unit;

use App\Database;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    /**
     * Ensure Database::getInstance returns injected stub connection in tests.
     */
    public function test_getInstance_returnsStubbedInstance(): void
    {
        $pdo = $this->pdo;
        test_set_database_connection($pdo);

        $instance = Database::getInstance();
        $this->assertSame($pdo, $instance->getConnection());
    }
}
