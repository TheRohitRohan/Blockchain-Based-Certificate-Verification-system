<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use App\Database;

/**
 * Suite 5 — API: Dynamic-path student & university endpoints
 *
 * Covers the new routes introduced in the Student & University management PR:
 *   GET/PUT/DELETE /students/:id
 *   GET /students/:id/certificates
 *   GET/PUT/DELETE /universities/:id
 *   GET /universities/:id/students
 *   GET /universities/:id/certificates
 *   GET /universities/:id/stats
 */
class StudentUniversityDynamicApiTest extends TestCase
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
            'verify'      => false,
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

    // ─────────────────────────────────────────────────────────────────
    // GET /students/:id
    // ─────────────────────────────────────────────────────────────────

    public function testGetStudentByIdAsAdmin(): void
    {
        $resp = self::$http->get('students/' . TestState::$studentId, [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertSame(TestState::$studentId, (int)$body['data']['id']);
    }

    public function testGetStudentByIdAsStudent(): void
    {
        $resp = self::$http->get('students/' . TestState::$studentId, [
            'headers' => $this->authHeader(TestState::$studentJwt),
        ]);

        // Student may see their own record (200) or get 403 if RBAC rejects
        $this->assertContains($resp->getStatusCode(), [200, 403]);
    }

    public function testGetStudentByIdNotFoundReturns404(): void
    {
        $resp = self::$http->get('students/999999999', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(404, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertArrayHasKey('error', $body);
    }

    public function testGetStudentByIdUnauthorizedReturns401(): void
    {
        $resp = self::$http->get('students/' . TestState::$studentId);

        $this->assertSame(401, $resp->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────────
    // PUT /students/:id
    // ─────────────────────────────────────────────────────────────────

    public function testUpdateStudentAsAdmin(): void
    {
        $resp = self::$http->put('students/' . TestState::$studentId, [
            'headers' => $this->authHeader(TestState::$adminJwt),
            'json'    => ['full_name' => 'Admin Updated Name'],
        ]);

        // 200 if updated, 400 if name identical to current value
        $this->assertContains($resp->getStatusCode(), [200, 400]);
        if ($resp->getStatusCode() === 200) {
            $body = $this->json($resp);
            $this->assertTrue($body['success']);
        }
    }

    public function testUpdateStudentNotFoundReturns404(): void
    {
        $resp = self::$http->put('students/999999999', [
            'headers' => $this->authHeader(TestState::$adminJwt),
            'json'    => ['full_name' => 'Ghost'],
        ]);

        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testUpdateStudentAsUniversityRoleReturns403(): void
    {
        $resp = self::$http->put('students/' . TestState::$studentId, [
            'headers' => $this->authHeader(TestState::$universityJwt),
            'json'    => ['full_name' => 'Should Fail'],
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }

    public function testUpdateStudentInvalidDateReturns400(): void
    {
        $resp = self::$http->put('students/' . TestState::$studentId, [
            'headers' => $this->authHeader(TestState::$adminJwt),
            'json'    => ['date_of_birth' => 'not-a-date'],
        ]);

        $this->assertSame(400, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertArrayHasKey('error', $body);
    }

    // ─────────────────────────────────────────────────────────────────
    // DELETE /students/:id
    // ─────────────────────────────────────────────────────────────────

    public function testDeleteStudentAsUniversityRoleReturns403(): void
    {
        $resp = self::$http->delete('students/' . TestState::$studentId, [
            'headers' => $this->authHeader(TestState::$universityJwt),
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }

    public function testDeleteStudentNotFoundReturns404(): void
    {
        $resp = self::$http->delete('students/999999999', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(404, $resp->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /students/:id/certificates
    // ─────────────────────────────────────────────────────────────────

    public function testGetStudentCertificatesAsAdmin(): void
    {
        $resp = self::$http->get('students/' . TestState::$studentId . '/certificates', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('pagination', $body);
        $this->assertIsArray($body['data']);
    }

    public function testGetStudentCertificatesNotFoundReturns404(): void
    {
        $resp = self::$http->get('students/999999999/certificates', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testGetStudentCertificatesPaginationShape(): void
    {
        $resp = self::$http->get('students/' . TestState::$studentId . '/certificates?page=1&limit=5', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $pagination = $body['pagination'] ?? [];
        $this->assertSame(1, $pagination['page']);
        $this->assertSame(5, $pagination['limit']);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('pages', $pagination);
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /universities/:id
    // ─────────────────────────────────────────────────────────────────

    public function testGetUniversityByIdPublic(): void
    {
        $resp = self::$http->get('universities/' . TestState::$universityId);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertSame(TestState::$universityId, (int)$body['data']['id']);
    }

    public function testGetUniversityByIdNotFoundReturns404(): void
    {
        $resp = self::$http->get('universities/999999999');

        $this->assertSame(404, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertArrayHasKey('error', $body);
    }

    // ─────────────────────────────────────────────────────────────────
    // PUT /universities/:id
    // ─────────────────────────────────────────────────────────────────

    public function testUpdateUniversityNotFoundReturns404(): void
    {
        $resp = self::$http->put('universities/999999999', [
            'headers' => $this->authHeader(TestState::$adminJwt),
            'json'    => ['name' => 'Ghost University'],
        ]);

        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testUpdateUniversityAsStudentReturns403(): void
    {
        $resp = self::$http->put('universities/' . TestState::$universityId, [
            'headers' => $this->authHeader(TestState::$studentJwt),
            'json'    => ['name' => 'Should Fail'],
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }

    public function testUpdateUniversityAsAdmin(): void
    {
        $resp = self::$http->put('universities/' . TestState::$universityId, [
            'headers' => $this->authHeader(TestState::$adminJwt),
            'json'    => ['address' => 'Updated Address ' . uniqid()],
        ]);

        // 200 if updated, 400 if no rows changed
        $this->assertContains($resp->getStatusCode(), [200, 400]);
        if ($resp->getStatusCode() === 200) {
            $this->assertTrue($this->json($resp)['success']);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // DELETE /universities/:id
    // ─────────────────────────────────────────────────────────────────

    public function testDeleteUniversityAsUniversityRoleReturns403(): void
    {
        $resp = self::$http->delete('universities/' . TestState::$universityId, [
            'headers' => $this->authHeader(TestState::$universityJwt),
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }

    public function testDeleteUniversityNotFoundReturns404(): void
    {
        $resp = self::$http->delete('universities/999999999', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(404, $resp->getStatusCode());
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /universities/:id/students
    // ─────────────────────────────────────────────────────────────────

    public function testGetUniversityStudentsAsAdmin(): void
    {
        $resp = self::$http->get('universities/' . TestState::$universityId . '/students', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('pagination', $body);
    }

    public function testGetUniversityStudentsAsStudentReturns403(): void
    {
        $resp = self::$http->get('universities/' . TestState::$universityId . '/students', [
            'headers' => $this->authHeader(TestState::$studentJwt),
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }

    public function testGetUniversityStudentsNotFoundReturns404(): void
    {
        $resp = self::$http->get('universities/999999999/students', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testGetUniversityStudentsPaginationShape(): void
    {
        $resp = self::$http->get('universities/' . TestState::$universityId . '/students?page=1&limit=5', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $pagination = $this->json($resp)['pagination'] ?? [];
        $this->assertSame(1, $pagination['page']);
        $this->assertSame(5, $pagination['limit']);
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /universities/:id/certificates
    // ─────────────────────────────────────────────────────────────────

    public function testGetUniversityCertificatesAsAdmin(): void
    {
        $resp = self::$http->get('universities/' . TestState::$universityId . '/certificates', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('pagination', $body);
    }

    public function testGetUniversityCertificatesAsStudentReturns403(): void
    {
        $resp = self::$http->get('universities/' . TestState::$universityId . '/certificates', [
            'headers' => $this->authHeader(TestState::$studentJwt),
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }

    public function testGetUniversityCertificatesNotFoundReturns404(): void
    {
        $resp = self::$http->get('universities/999999999/certificates', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testGetUniversityCertificatesPaginationShape(): void
    {
        $resp = self::$http->get('universities/' . TestState::$universityId . '/certificates?page=1&limit=5', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $pagination = $this->json($resp)['pagination'] ?? [];
        $this->assertSame(1, $pagination['page']);
        $this->assertSame(5, $pagination['limit']);
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /universities/:id/stats
    // ─────────────────────────────────────────────────────────────────

    public function testGetUniversityStatsAsAdmin(): void
    {
        $resp = self::$http->get('universities/' . TestState::$universityId . '/stats', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $stats = $body['data'];
        $this->assertArrayHasKey('total_students', $stats);
        $this->assertArrayHasKey('active_students', $stats);
        $this->assertArrayHasKey('total_certificates', $stats);
        $this->assertArrayHasKey('active_certificates', $stats);
        $this->assertArrayHasKey('revoked_certificates', $stats);
    }

    public function testGetUniversityStatsAsStudentReturns403(): void
    {
        $resp = self::$http->get('universities/' . TestState::$universityId . '/stats', [
            'headers' => $this->authHeader(TestState::$studentJwt),
        ]);

        $this->assertSame(403, $resp->getStatusCode());
    }

    public function testGetUniversityStatsNotFoundReturns404(): void
    {
        $resp = self::$http->get('universities/999999999/stats', [
            'headers' => $this->authHeader(TestState::$adminJwt),
        ]);

        $this->assertSame(404, $resp->getStatusCode());
    }
}
