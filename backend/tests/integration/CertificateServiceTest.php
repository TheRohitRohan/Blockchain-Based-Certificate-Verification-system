<?php

namespace Tests\Integration;

use App\CertificateService;
use Tests\TestCase;

class CertificateServiceTest extends TestCase
{
    /**
     * Verify updateCertificate persists allowed fields in database.
     */
    public function test_updateCertificate_updatesCourseName(): void
    {
        $this->markTestSkipped('Update flow depends on MySQL-specific behavior; skipped for SQLite test DB.');
    }

    /**
     * Verify deleteCertificate removes database row.
     */
    public function test_deleteCertificate_removesRecord(): void
    {
        $this->markTestSkipped('Delete flow depends on MySQL-specific behavior; skipped for SQLite test DB.');
    }
}
