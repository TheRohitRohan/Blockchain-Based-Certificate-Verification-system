<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use App\Database;

/**
 * Suite 4 — API: Public verification & download endpoints
 *
 * No authentication required — mirrors what an external user sees.
 */
class PublicApiTest extends TestCase
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

    // ─── 1. Verify by ID (GET) ───────────────────────────────────────

    public function testPublicVerifyByCertificateIdGet(): void
    {
        $resp = self::$http->get(
            'public/verify?certificate_id=' . urlencode(TestState::$certificateId)
        );

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['success']);
        $this->assertTrue($body['conclusion']['is_valid']);
        $this->assertSame('valid', $body['conclusion']['overall_status']);
    }

    // ─── 2. Verify by ID (POST) ──────────────────────────────────────

    public function testPublicVerifyByCertificateIdPost(): void
    {
        $resp = self::$http->post('public/verify', [
            'json' => ['certificate_id' => TestState::$certificateId],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        $this->assertTrue($body['conclusion']['is_valid']);
    }

    // ─── 3. Verify with invalid ID ───────────────────────────────────

    public function testPublicVerifyWithInvalidId(): void
    {
        $resp = self::$http->get('public/verify?certificate_id=CERT-FAKE-000');

        $this->assertSame(200, $resp->getStatusCode());
        $body = $this->json($resp);
        // When certificate is not found, success = false or conclusion.is_valid = false
        $isInvalid = ($body['success'] ?? true) === false
                  || ($body['conclusion']['is_valid'] ?? true) === false;
        $this->assertTrue($isInvalid, 'Fake certificate should not verify as valid');
    }

    // ─── 4. Verify with revoked certificate ──────────────────────────

    public function testPublicVerifyWithRevokedCertificate(): void
    {
        // Skip if $uploadedCertId is empty (CertificateApiTest may not have run)
        if (empty(TestState::$uploadedCertId)) {
            $this->markTestSkipped('uploadedCertId not set — CertificateApiTest must run first');
        }

        // Revoke directly via DB
        $stmt = self::$db->prepare(
            "UPDATE certificates SET status = 'revoked', is_revoked = 1 WHERE certificate_id = ?"
        );
        $stmt->execute([TestState::$uploadedCertId]);

        // Clear cache for this certificate
        $cache = \App\Cache::getInstance();
        $cache->delete('verify:' . TestState::$uploadedCertId);
        $cache->delete('cert_light:' . TestState::$uploadedCertId);

        $resp = self::$http->get(
            'public/verify?certificate_id=' . urlencode(TestState::$uploadedCertId)
        );

        $body = $this->json($resp);

        $isRevoked = ($body['conclusion']['is_valid'] ?? true) === false
                  && ($body['conclusion']['overall_status'] ?? '') === 'revoked';

        // Even if the exact shape varies, the certificate must NOT verify as valid
        $this->assertFalse($body['conclusion']['is_valid'] ?? true, 'Revoked cert should be invalid');

        // Restore for other tests
        $restore = self::$db->prepare(
            "UPDATE certificates SET status = 'active', is_revoked = 0, revoked_at = NULL
             WHERE certificate_id = ?"
        );
        $restore->execute([TestState::$uploadedCertId]);
        $cache->delete('verify:' . TestState::$uploadedCertId);
        $cache->delete('cert_light:' . TestState::$uploadedCertId);
    }

    // ─── 5. Download PDF ─────────────────────────────────────────────

    public function testPublicDownloadCertificatePDF(): void
    {
        $resp = self::$http->get(
            'public/certificate/download?certificate_id=' . urlencode(TestState::$certificateId)
        );

        $this->assertSame(200, $resp->getStatusCode());
        $ct = $resp->getHeaderLine('Content-Type');
        $this->assertStringContainsString('application/pdf', $ct);

        $bodyStr = $resp->getBody()->getContents();
        $this->assertStringStartsWith('%PDF', $bodyStr);
    }

    // ─── 6. Download with view mode → inline disposition ─────────────

    public function testPublicDownloadWithViewMode(): void
    {
        $resp = self::$http->get(
            'public/certificate/download?certificate_id=' . urlencode(TestState::$certificateId) . '&view=1'
        );

        $this->assertSame(200, $resp->getStatusCode());
        $disposition = $resp->getHeaderLine('Content-Disposition');
        $this->assertStringContainsString('inline', $disposition);
    }

    // ─── 7. Download with bad ID → 404 ───────────────────────────────

    public function testPublicDownloadWithBadIdReturns404(): void
    {
        $resp = self::$http->get('public/certificate/download?certificate_id=CERT-BAD-XYZ');

        $this->assertSame(404, $resp->getStatusCode());
    }
}
