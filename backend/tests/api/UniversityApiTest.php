<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use App\Database;

/**
 * Suite 4 — API: University endpoints
 */
class UniversityApiTest extends TestCase
{
    private static Client $http;
    private static \PDO   $db;

    public static function setUpBeforeClass(): void
    {
        $base = getenv('TEST_BASE_URL') ?: ($_ENV['TEST_BASE_URL'] ?? 'http://localhost:8000/api');
        self::$http = new Client([
            'base_uri'    => rtrim($base, '/') . '/',
            'http_errors' => false,
            'timeout'     => 30,
        ]);
        self::$db = Database::getInstance()->getConnection();
    }

    private function json($response): array
    {
        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    private function authHeader(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    // ─── 1. GET /universities — no auth required ─────────────────────

    public function testGetUniversitiesNoAuth(): void
    {
        $resp = self::$http->get('universities');

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertIsArray($body['universities']);

        $names = array_column($body['universities'], 'name');
        $this->assertContains('University of Technology', $names);
    }

    // ─── 2. Create university as admin ───────────────────────────────

    public function testCreateUniversityAsAdmin(): void
    {
        // Use a unique code to avoid duplicate key errors on re-run
        $code = 'ATU' . substr(uniqid(), -3);

        $resp = self::$http->post('universities', [
            'headers' => $this->authHeader(TestState::$adminJwt),
            'json'    => [
                'name'          => 'API Test University',
                'code'          => $code,
                'contact_email' => 'test@atu.edu',
            ],
        ]);

        $status = $resp->getStatusCode();
        $this->assertTrue(in_array($status, [200, 201]), "Expected 200 or 201, got {$status}");

        $body = $this->json($resp);
        $this->assertTrue($body['success']);

        // Verify DB
        $stmt = self::$db->prepare("SELECT id FROM universities WHERE code = ?");
        $stmt->execute([$code]);
        $this->assertNotFalse($stmt->fetch(), 'University row not found in DB');
    }

    // ─── 3. Create university as university role → 403 ───────────────

    public function testCreateUniversityAsUniversityReturns403(): void
    {
        $resp = self::$http->post('universities', [
            'headers' => $this->authHeader(TestState::$universityJwt),
            'json'    => [
                'name'          => 'Forbidden University',
                'code'          => 'FBD',
                'contact_email' => 'test@fbd.edu',
            ],
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }

    // ─── 4. Generate key pair as admin ───────────────────────────────

    public function testGenerateKeyPairAsAdmin(): void
    {
        $resp = self::$http->post('universities/generate-key', [
            'headers' => $this->authHeader(TestState::$adminJwt),
            'json'    => ['university_id' => TestState::$universityId],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);

        // Verify key in DB
        $stmt = self::$db->prepare(
            "SELECT id FROM university_keys WHERE university_id = ? AND is_active = 1"
        );
        $stmt->execute([TestState::$universityId]);
        $this->assertNotFalse($stmt->fetch(), 'Active key row missing after generate');
    }

    // ─── 5. Generate key without university_id → 400 ─────────────────

    public function testGenerateKeyPairWithoutUniversityIdReturns400(): void
    {
        $resp = self::$http->post('universities/generate-key', [
            'headers' => $this->authHeader(TestState::$adminJwt),
            'json'    => [],  // missing university_id
        ]);

        $this->assertSame(400, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertArrayHasKey('error', $body);
    }

    // ─── 6. Generate key as university role → 403 ────────────────────

    public function testGenerateKeyPairAsUniversityReturns403(): void
    {
        $resp = self::$http->post('universities/generate-key', [
            'headers' => $this->authHeader(TestState::$universityJwt),
            'json'    => ['university_id' => TestState::$universityId],
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }
}
