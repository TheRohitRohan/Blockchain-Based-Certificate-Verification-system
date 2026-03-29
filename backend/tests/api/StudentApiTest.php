<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use App\Database;

/**
 * Suite 4 — API: Student endpoints
 */
class StudentApiTest extends TestCase
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

    // ─── 1. Get students as university ───────────────────────────────

    public function testGetStudentsAsUniversity(): void
    {
        $resp = self::$http->get('students', [
            'headers' => $this->authHeader(TestState::$universityJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertIsArray($body['students']);
    }

    // ─── 2. Get students as student → 403 ────────────────────────────

    public function testGetStudentsAsStudentReturns403(): void
    {
        $resp = self::$http->get('students', [
            'headers' => $this->authHeader(TestState::$studentJwt),
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }

    // ─── 3. Create student as university ─────────────────────────────

    public function testCreateStudentAsUniversity(): void
    {
        $uniqueId    = uniqid();
        $uniqueEmail = 'stu_' . $uniqueId . '@uot.edu';

        $resp = self::$http->post('students', [
            'headers' => $this->authHeader(TestState::$universityJwt),
            'json'    => [
                'username'        => 'stu_' . $uniqueId,
                'email'           => $uniqueEmail,
                'password'        => 'Student@123',
                'full_name'       => 'Test Student ' . $uniqueId,
                'student_id'      => 'STU-API-' . strtoupper(substr($uniqueId, -6)),
                'university_id'   => TestState::$universityId,
                'enrollment_date' => '2024-09-01',
            ],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);

        // Verify user and student rows
        $stmt = self::$db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$uniqueEmail]);
        $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($userRow, 'User row not found');

        $stmt2 = self::$db->prepare("SELECT id FROM students WHERE user_id = ?");
        $stmt2->execute([$userRow['id']]);
        $this->assertNotFalse($stmt2->fetch(), 'Student row not found');
    }

    // ─── 4. Create student without auth → 401 ────────────────────────

    public function testCreateStudentWithoutAuthReturns401(): void
    {
        $resp = self::$http->post('students', [
            'json' => [
                'username'   => 'noauth_stu',
                'email'      => 'noauth@uot.edu',
                'password'   => 'Test@123',
                'full_name'  => 'No Auth Student',
                'student_id' => 'STU-NOAUTH-001',
            ],
        ]);

        $this->assertSame(401, $resp->getStatusCode());
    }
}
