<?php

use PHPUnit\Framework\TestCase;
use App\Database;
use App\VerificationEngine;

/**
 * Suite 3 — Integration: VerificationEngine
 *
 * Depends on: certificate created in CertificateServiceTest (TestState::$certificateId).
 */
class VerificationEngineTest extends TestCase
{
    private static \PDO $db;
    private static VerificationEngine $engine;

    public static function setUpBeforeClass(): void
    {
        self::$db     = Database::getInstance()->getConnection();
        self::$engine = new VerificationEngine();
    }

    // ─── 1. Verify by certificate ID → valid ─────────────────────────

    public function testVerifyByCertificateIdReturnsValid(): void
    {
        $result = self::$engine->verifyByCertificateId(TestState::$certificateId);

        $this->assertTrue($result['valid'],  'Certificate should verify as valid');
        $this->assertSame('valid', $result['status']);
    }

    // ─── 2. Verify uploaded PDF → all checks pass ────────────────────

    /** @depends testVerifyByCertificateIdReturnsValid */
    public function testVerifyUploadedPDFReturnsValid(): void
    {
        $this->assertFileExists(TestState::$pdfPath, 'PDF path not set — run CertificateServiceTest first');

        $result = self::$engine->verifyUploadedPDF(TestState::$pdfPath);

        $this->assertTrue($result['valid'],  'Uploaded PDF should verify as valid');
        $this->assertSame('valid', $result['status']);
        $this->assertTrue($result['checks']['metadata_match'],  'metadata_match check failed');
        $this->assertTrue($result['checks']['pdf_hash_match'],  'pdf_hash_match check failed');
        $this->assertTrue($result['checks']['signature_valid'], 'signature_valid check failed');
        $this->assertTrue($result['checks']['not_revoked'],     'not_revoked check failed');
    }

    // ─── 3. Tampered binary → pdf_hash_match fails ───────────────────

    /** @depends testVerifyUploadedPDFReturnsValid */
    public function testVerifyUploadedPDFDetectsTamperedBinary(): void
    {
        // Copy to temp — do NOT modify the original
        $config   = require __DIR__ . '/../../config.php';
        $tempPath = $config['storage']['pdf_path'] . 'tampered_verify_' . uniqid() . '.pdf';

        copy(TestState::$pdfPath, $tempPath);
        $this->assertFileExists($tempPath);

        // Tamper: replace a visible character in the binary
        $binary   = file_get_contents($tempPath);
        $tampered = str_replace('Ahmed Al-Rashidi', 'Zzzzzz Xxxxxxxxx', $binary);

        if ($tampered === $binary) {
            // Name not in binary text (could be in XMP) — force bit-flip in middle
            $mid      = (int)(strlen($binary) / 2);
            $tampered = substr($binary, 0, $mid) . chr(ord($binary[$mid]) ^ 0x01) . substr($binary, $mid + 1);
        }

        file_put_contents($tempPath, $tampered);

        $result = self::$engine->verifyUploadedPDF($tempPath);

        $this->assertFalse($result['valid'], 'Tampered PDF should not verify as valid');
        $this->assertFalse($result['checks']['pdf_hash_match'], 'Tamper should cause pdf_hash_match = false');
        // Do not delete tempPath (test data preserved per rules)
    }

    // ─── 4. Wrong hash provided → invalid ────────────────────────────

    /** @depends testVerifyByCertificateIdReturnsValid */
    public function testVerifyByCertificateIdWithWrongHashFails(): void
    {
        $zeroHash = '0x' . str_repeat('00', 32);
        $result   = self::$engine->verifyByCertificateId(TestState::$certificateId, $zeroHash);

        $this->assertFalse($result['valid'], 'Wrong hash should cause verification to fail');
    }

    // ─── 5. Non-existent certificate → not_found ─────────────────────

    public function testVerifyNonExistentCertificateReturnsNotFound(): void
    {
        $result = self::$engine->verifyByCertificateId('CERT-DOES-NOT-EXIST-XYZ999');

        $this->assertFalse($result['valid']);
        $this->assertSame('not_found', $result['status']);
    }

    // ─── 6. Revoke → verify revoked → restore → verify valid ─────────

    /** @depends testVerifyByCertificateIdReturnsValid */
    public function testRevocationAndRestoration(): void
    {
        // Revoke
        $certService = new \App\CertificateService();
        $revoked     = $certService->revokeCertificate(TestState::$certificateId, 1);
        $this->assertTrue($revoked, 'revokeCertificate returned false');

        // DB check
        $stmt = self::$db->prepare(
            "SELECT status FROM certificates WHERE certificate_id = ?"
        );
        $stmt->execute([TestState::$certificateId]);
        $this->assertSame('revoked', $stmt->fetchColumn());

        // Verify returns revoked
        $result = self::$engine->verifyByCertificateId(TestState::$certificateId);
        $this->assertFalse($result['valid']);
        $this->assertSame('revoked', $result['status']);

        // Restore directly via SQL (so downstream tests can continue)
        $restore = self::$db->prepare(
            "UPDATE certificates
             SET status = 'active', is_revoked = 0, revoked_at = NULL
             WHERE certificate_id = ?"
        );
        $restore->execute([TestState::$certificateId]);

        // Invalidate cache and verify restored
        $cache = \App\Cache::getInstance();
        $cache->delete('verify:' . TestState::$certificateId);
        $cache->delete('cert_light:' . TestState::$certificateId);

        $resultAfter = self::$engine->verifyByCertificateId(TestState::$certificateId);
        $this->assertTrue($resultAfter['valid'], 'Certificate should be valid after restoration');
    }

    // ─── 7. Verification logs created ────────────────────────────────

    /** @depends testVerifyByCertificateIdReturnsValid */
    public function testVerificationLogIsWrittenToDatabase(): void
    {
        $stmt = self::$db->prepare(
            "SELECT COUNT(*) FROM verification_logs WHERE certificate_id = ?"
        );
        $stmt->execute([TestState::$certificateId]);
        $count = (int)$stmt->fetchColumn();

        $this->assertGreaterThan(0, $count, 'No verification log entries found for this certificate');
    }
}
