<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use App\Database;

/**
 * Suite 4 — API: Certificate endpoints
 *
 * Black-box HTTP tests. Includes blockchain-specific tests that auto-skip
 * when BLOCKCHAIN_RPC is not configured.
 */
class CertificateApiTest extends TestCase
{
    private static Client $http;
    private static \PDO   $db;
    private static array  $createdCertIds = [];

    public static function setUpBeforeClass(): void
    {
        $base = getenv('TEST_BASE_URL') ?: ($_ENV['TEST_BASE_URL'] ?? 'http://localhost:8000/api');
        self::$http = new Client([
            'base_uri'    => rtrim($base, '/') . '/',
            'http_errors' => false,
            'timeout'     => 60, // certificate creation can be slow (blockchain)
            'verify'      => false,
        ]);
        self::$db = Database::getInstance()->getConnection();
    }

    public static function tearDownAfterClass(): void
    {
        // Cleanup all certificates created during this test suite
        if (!empty(self::$createdCertIds)) {
            $in = str_repeat('?,', count(self::$createdCertIds) - 1) . '?';
            $stmt = self::$db->prepare("DELETE FROM certificates WHERE certificate_id IN ($in)");
            $stmt->execute(self::$createdCertIds);
        }
    }

    private function json($response): array
    {
        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    private function authHeader(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    // ─── Helper: create a fresh certificate via API ──────────────────

    private function createFreshCertificate(): array
    {
        $resp = self::$http->post('certificates/create', [
            'headers' => $this->authHeader(TestState::$universityJwt),
            'json'    => [
                'student_id'    => TestState::$studentId,
                'university_id' => TestState::$universityId,
                'course_name'   => 'Temp Course ' . uniqid(),
                'degree_type'   => 'Bachelor',
                'issue_date'    => '2024-09-01',
            ],
        ]);
        $body = $this->json($resp);
        if ($body['success'] ?? false) {
            self::$createdCertIds[] = $body['certificate_id'];
        }
        return $body;
    }

    // ═════════════════════════════════════════════════════════════════
    //  CERTIFICATE CRUD
    // ═════════════════════════════════════════════════════════════════

    // ─── 1. Create as university ─────────────────────────────────────

    public function testCreateCertificateAsUniversity(): void
    {
        $resp = self::$http->post('certificates/create', [
            'headers' => $this->authHeader(TestState::$universityJwt),
            'json'    => [
                'student_id'    => TestState::$studentId,
                'university_id' => TestState::$universityId,
                'course_name'   => 'API Test Course',
                'degree_type'   => 'Bachelor',
                'issue_date'    => '2024-09-01',
            ],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success'], 'Failed: ' . ($body['error'] ?? ''));
        $this->assertNotEmpty($body['certificate_id']);
        $this->assertContains($body['blockchain_mode'], ['live', 'mock']);

        TestState::$certificateId = $body['certificate_id'];
        self::$createdCertIds[] = $body['certificate_id'];
    }

    // ─── 2. Create without auth → 401 ────────────────────────────────

    public function testCreateCertificateWithoutAuthReturns401(): void
    {
        $resp = self::$http->post('certificates/create', [
            'json' => [
                'student_id'    => TestState::$studentId,
                'university_id' => TestState::$universityId,
                'course_name'   => 'NoAuth Course',
                'degree_type'   => 'Bachelor',
                'issue_date'    => '2024-09-01',
            ],
        ]);

        $this->assertSame(401, $resp->getStatusCode());
    }

    // ─── 3. Create as student → 403 ──────────────────────────────────

    public function testCreateCertificateAsStudentReturns403(): void
    {
        $resp = self::$http->post('certificates/create', [
            'headers' => $this->authHeader(TestState::$studentJwt),
            'json'    => [
                'student_id'    => TestState::$studentId,
                'university_id' => TestState::$universityId,
                'course_name'   => 'Student Forbidden Course',
                'degree_type'   => 'Bachelor',
                'issue_date'    => '2024-09-01',
            ],
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }

    // ─── 4. Get by ID ────────────────────────────────────────────────

    /** @depends testCreateCertificateAsUniversity */
    public function testGetCertificateById(): void
    {
        $resp = self::$http->get('certificates/get?certificate_id=' . urlencode(TestState::$certificateId), [
            'headers' => $this->authHeader(TestState::$universityJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertSame(TestState::$certificateId, $body['certificate']['certificate_id']);
        $this->assertNotEmpty($body['certificate']['course_name']);
    }

    // ─── 5. Get bad ID → 404 ─────────────────────────────────────────

    public function testGetCertificateWithBadIdReturns404(): void
    {
        $resp = self::$http->get('certificates/get?certificate_id=CERT-DOESNOTEXIST', [
            'headers' => $this->authHeader(TestState::$universityJwt),
        ]);

        $this->assertSame(404, $resp->getStatusCode());
    }

    // ─── 6. List certificates ────────────────────────────────────────

    public function testListCertificatesAsUniversity(): void
    {
        $resp = self::$http->get('certificates/list', [
            'headers' => $this->authHeader(TestState::$universityJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertIsArray($body['certificates']);
        $this->assertNotEmpty($body['certificates'], 'Expected at least one certificate in list');
    }

    // ─── 7. List with pagination ─────────────────────────────────────

    public function testListCertificatesWithPagination(): void
    {
        $resp = self::$http->get('certificates/list?page=1&per_page=2', [
            'headers' => $this->authHeader(TestState::$universityJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertLessThanOrEqual(2, count($body['certificates']));
        $this->assertArrayHasKey('total', $body);
    }

    // ─── 8. Verify by ID ─────────────────────────────────────────────

    public function testVerifyCertificateById(): void
    {
        $resp = self::$http->post('certificates/verify', [
            'json' => ['certificate_id' => TestState::$certificateId],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue(
            ($body['valid'] ?? false) || ($body['status'] ?? '') === 'valid',
            'Certificate should verify as valid'
        );
    }

    // ─── 9. Download PDF ─────────────────────────────────────────────

    public function testDownloadCertificatePDF(): void
    {
        $resp = self::$http->get(
            'certificates/download?certificate_id=' . urlencode(TestState::$certificateId),
            ['headers' => $this->authHeader(TestState::$universityJwt)]
        );

        $this->assertSame(200, $resp->getStatusCode());
        $ct = $resp->getHeaderLine('Content-Type');
        $this->assertStringContainsString('application/pdf', $ct);

        $bodyStr = $resp->getBody()->getContents();
        $this->assertStringStartsWith('%PDF', $bodyStr);
    }

    // ─── 10. Update allowed fields ───────────────────────────────────

    public function testUpdateCertificateAllowedFields(): void
    {
        $resp = self::$http->post('certificates/update', [
            'headers' => $this->authHeader(TestState::$universityJwt),
            'json'    => [
                'certificate_id' => TestState::$certificateId,
                'course_name'    => 'HTTP Updated Course',
            ],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);

        // Verify via GET
        $getResp = self::$http->get(
            'certificates/get?certificate_id=' . urlencode(TestState::$certificateId),
            ['headers' => $this->authHeader(TestState::$universityJwt)]
        );
        $cert = $this->json($getResp)['certificate'];
        $this->assertSame('HTTP Updated Course', $cert['course_name']);
    }

    // ─── 11. Update without auth → 401 ───────────────────────────────

    public function testUpdateCertificateWithoutAuthReturns401(): void
    {
        $resp = self::$http->post('certificates/update', [
            'json' => [
                'certificate_id' => TestState::$certificateId,
                'course_name'    => 'Should Not Work',
            ],
        ]);

        $this->assertSame(401, $resp->getStatusCode());
    }

    // ─── 12. Revoke then verify revoked ──────────────────────────────

    public function testRevokeCertificateThenVerifyRevoked(): void
    {
        // Create a fresh cert specifically for revocation
        $fresh = $this->createFreshCertificate();
        $this->assertTrue($fresh['success'], 'Failed creating cert for revoke test');
        $freshId = $fresh['certificate_id'];

        // Revoke
        $resp = self::$http->post('certificates/revoke', [
            'headers' => $this->authHeader(TestState::$adminJwt),
            'json'    => ['certificate_id' => $freshId],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);

        // Verify → should be revoked
        $verifyResp = self::$http->post('certificates/verify', [
            'json' => ['certificate_id' => $freshId],
        ]);
        $verifyBody = $this->json($verifyResp);
        $isRevoked  = ($verifyBody['status'] ?? '') === 'revoked'
                   || ($verifyBody['valid'] ?? true) === false;
        $this->assertTrue($isRevoked, 'Certificate should show as revoked after revocation');
    }

    // ─── 13. Delete as admin ─────────────────────────────────────────

    public function testDeleteCertificateAsAdmin(): void
    {
        // Create a fresh cert specifically for deletion
        $fresh = $this->createFreshCertificate();
        $this->assertTrue($fresh['success'], 'Failed creating cert for delete test');
        $freshId = $fresh['certificate_id'];

        $resp = self::$http->post('certificates/delete', [
            'headers' => $this->authHeader(TestState::$adminJwt),
            'json'    => ['certificate_id' => $freshId],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);

        // Verify row is gone
        $stmt = self::$db->prepare("SELECT id FROM certificates WHERE certificate_id = ?");
        $stmt->execute([$freshId]);
        $this->assertFalse($stmt->fetch(), 'Certificate row should be deleted from DB');
    }

    // ─── 14. Delete as university → 403 ──────────────────────────────

    public function testDeleteCertificateAsUniversityReturns403(): void
    {
        $resp = self::$http->post('certificates/delete', [
            'headers' => $this->authHeader(TestState::$universityJwt),
            'json'    => ['certificate_id' => TestState::$certificateId],
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }

    // ═════════════════════════════════════════════════════════════════
    //  BLOCKCHAIN-SPECIFIC TESTS (auto-skip if not configured)
    // ═════════════════════════════════════════════════════════════════

    public function testBlockchainModeIsLiveWhenConnected(): void
    {
        $rpc = getenv('BLOCKCHAIN_RPC') ?: ($_ENV['BLOCKCHAIN_RPC'] ?? '');
        if (empty($rpc)) {
            $this->markTestSkipped('BLOCKCHAIN_RPC not configured — skipping live blockchain test');
        }

        $resp = self::$http->post('certificates/create', [
            'headers' => $this->authHeader(TestState::$universityJwt),
            'json'    => [
                'student_id'    => TestState::$studentId,
                'university_id' => TestState::$universityId,
                'course_name'   => 'Blockchain Live Test ' . uniqid(),
                'degree_type'   => 'Bachelor',
                'issue_date'    => '2024-09-01',
            ],
        ]);

        $body = $this->json($resp);
        $this->assertTrue($body['success'], 'Cert creation failed: ' . ($body['error'] ?? ''));
        self::$createdCertIds[] = $body['certificate_id'];
        $this->assertSame('live', $body['blockchain_mode']);
        $this->assertNotNull($body['tx_hash']);
        $this->assertStringStartsWith('0x', $body['tx_hash']);
    }

    public function testBlockchainVerificationPassesForLiveCertificate(): void
    {
        $rpc = getenv('BLOCKCHAIN_RPC') ?: ($_ENV['BLOCKCHAIN_RPC'] ?? '');
        if (empty($rpc)) {
            $this->markTestSkipped('BLOCKCHAIN_RPC not configured');
        }

        // Find a certificate with blockchain_mode = live
        $stmt = self::$db->prepare(
            "SELECT certificate_id FROM certificates
             WHERE blockchain_tx_hash IS NOT NULL
             LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $this->markTestSkipped('No live blockchain certificate found in DB');
        }

        $resp = self::$http->get('public/verify?certificate_id=' . urlencode($row['certificate_id']));
        $body = $this->json($resp);

        $this->assertTrue($body['success'] ?? false);
    }

    public function testBlockchainModeIsMockWhenNotConfigured(): void
    {
        // Check for certificates with blockchain_tx_hash = NULL (mock mode)
        $stmt = self::$db->prepare(
            "SELECT certificate_id FROM certificates
             WHERE blockchain_tx_hash IS NULL AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $this->markTestSkipped('No mock-mode certificate found in DB');
        }

        // Even mock-mode certificates should still verify via DB/hash checks
        $resp = self::$http->get('public/verify?certificate_id=' . urlencode($row['certificate_id']));
        $body = $this->json($resp);

        $this->assertTrue($body['success'] ?? false);
        $this->assertTrue(
            $body['conclusion']['is_valid'] ?? false,
            'Mock-mode certificate should still verify as valid via DB checks'
        );
    }
}
