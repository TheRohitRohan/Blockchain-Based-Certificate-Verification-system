<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

/**
 * Suite 4 — API: Auth endpoints
 *
 * Black-box HTTP tests against the running server.
 * Base URL from TEST_BASE_URL env variable.
 */
class AuthApiTest extends TestCase
{
    private static Client $http;

    public static function setUpBeforeClass(): void
    {
        $base = getenv('TEST_BASE_URL') ?: ($_ENV['TEST_BASE_URL'] ?? 'http://localhost:8000/api');
        self::$http = new Client([
            'base_uri'    => rtrim($base, '/') . '/',
            'http_errors' => false,
            'timeout'     => 30,
            'verify'      => false,
        ]);
    }

    private function json($response): array
    {
        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    private function authHeader(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    // ─── 1. Login valid ──────────────────────────────────────────────

    public function testLoginWithValidCredentials(): void
    {
        // First, register a temporary test user so we explicitly know their password
        $testEmail = 'login_test_' . uniqid() . '@test.com';
        $testPass = 'TempPass@123!';
        
        $regResp = self::$http->post('auth/register', [
            'json' => [
                'username'  => 'logintest_' . uniqid(),
                'email'     => $testEmail,
                'password'  => $testPass,
                'role'      => 'student',
                'full_name' => 'Login Tester',
            ],
        ]);
        $this->assertSame(200, $regResp->getStatusCode(), 'Temporary registration failed');

        // Now test the actual login endpoint
        $resp = self::$http->post('auth/login', [
            'json' => [
                'email'    => $testEmail,
                'password' => $testPass,
            ],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('token', $body);
        $this->assertSame('student', $body['user']['role']);

        // Persist email to test invalid paths
        TestState::$studentEmail = $testEmail;
    }

    // ─── 2. Login wrong password ─────────────────────────────────────

    public function testLoginWithInvalidPassword(): void
    {
        // Use the newly registered email
        $resp = self::$http->post('auth/login', [
            'json' => [
                'email'    => TestState::$studentEmail,
                'password' => 'TotallyWrongPassword!',
            ],
        ]);

        $this->assertSame(401, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertArrayHasKey('error', $body);
    }

    // ─── 3. Login non-existent email ─────────────────────────────────

    public function testLoginWithNonExistentEmail(): void
    {
        $resp = self::$http->post('auth/login', [
            'json' => [
                'email'    => 'nobody@nowhere.com',
                'password' => 'anything',
            ],
        ]);

        $this->assertSame(401, $resp->getStatusCode());
    }

    // ─── 4. Register new user ────────────────────────────────────────

    public function testRegisterNewUser(): void
    {
        $uniqueEmail = 'apitest_' . uniqid() . '@test.com';

        $resp = self::$http->post('auth/register', [
            'json' => [
                'username'  => 'apitest_' . uniqid(),
                'email'     => $uniqueEmail,
                'password'  => 'Test@654321',
                'role'      => 'student',
                'full_name' => 'API Test User',
            ],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);

        // Verify DB row
        $db   = \App\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$uniqueEmail]);
        $this->assertNotFalse($stmt->fetch(), 'User row not found in DB after register');
    }

    // ─── 5. Register duplicate email ─────────────────────────────────

    public function testRegisterDuplicateEmailFails(): void
    {
        $uniqueEmail = 'test.' . uniqid() . '@test.com';
        
        // First registration should succeed
        $resp1 = self::$http->post('auth/register', [
            'json' => [
                'username'  => 'user_' . uniqid(),
                'email'     => $uniqueEmail,
                'password'  => 'Test@654321',
                'role'      => 'student',
                'full_name' => 'First User',
            ],
        ]);
        $this->assertSame(200, $resp1->getStatusCode());

        // Second registration with same email should fail
        $resp2 = self::$http->post('auth/register', [
            'json' => [
                'username'  => 'user_dup_' . uniqid(),
                'email'     => $uniqueEmail, // duplicate email
                'password'  => 'Test@654321',
                'role'      => 'student',
                'full_name' => 'Duplicate User',
            ],
        ]);

        // Should fail with 400 or 500
        $this->assertGreaterThanOrEqual(400, $resp2->getStatusCode());
    }

    // ─── 6. Token verification works ─────────────────────────────────

    public function testTokenVerificationWorks(): void
    {
        $resp = self::$http->get('certificates/list', [
            'headers' => $this->authHeader(TestState::$universityJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode(), 'Valid token should be accepted');
    }

    // ─── 7. Garbage token rejected ───────────────────────────────────

    public function testExpiredOrGarbageTokenReturns401(): void
    {
        $resp = self::$http->get('certificates/list', [
            'headers' => $this->authHeader('garbage.token.here'),
        ]);

        $this->assertSame(401, $resp->getStatusCode());
    }
}
