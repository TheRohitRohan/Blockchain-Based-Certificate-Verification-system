<?php

use PHPUnit\Framework\TestCase;
use App\Database;
use App\ComparisonEngine;

/**
 * Suite 3 — Integration: ComparisonEngine
 *
 * Depends on: certificate from CertificateServiceTest (TestState::$pdfPath / $certificateId).
 */
class ComparisonEngineTest extends TestCase
{
    private static \PDO $db;
    private static ComparisonEngine $engine;

    public static function setUpBeforeClass(): void
    {
        self::$db     = Database::getInstance()->getConnection();
        self::$engine = new ComparisonEngine();
    }

    // ─── 1. Exact match ──────────────────────────────────────────────

    public function testComparePDFMatchesDatabaseRecord(): void
    {
        $this->assertFileExists(TestState::$pdfPath);

        $result = self::$engine->comparePDFWithDatabase(
            TestState::$pdfPath,
            TestState::$certificateId
        );

        if (!$result['match']) {
            echo "\n\nDEBUG testComparePDFMatchesDatabaseRecord:\n";
            echo "match: " . json_encode($result['match']) . "\n";
            echo "metadata_match: " . json_encode($result['metadata_match'] ?? false) . "\n";
            echo "pdf_hash_match: " . json_encode($result['pdf_hash_match'] ?? false) . "\n";
            echo "metadata_differences: " . json_encode($result['metadata_differences'] ?? []) . "\n";
        }

        $this->assertTrue($result['match'],         'Expected match to be true');
        $this->assertTrue($result['metadata_match'], 'Expected metadata_match to be true');
        $this->assertTrue($result['pdf_hash_match'], 'Expected pdf_hash_match to be true');
        $this->assertEmpty($result['metadata_differences'] ?? [], 'Expected no metadata differences');
    }

    // ─── 2. Tampered metadata block → metadata_match false ───────────

    public function testComparePDFDetectsModifiedMetadataBlock(): void
    {
        $config   = require __DIR__ . '/../../config.php';
        $tempPath = $config['storage']['pdf_path'] . 'tampered_meta_' . uniqid() . '.pdf';

        copy(TestState::$pdfPath, $tempPath);

        $binary   = file_get_contents($tempPath);
        // Student name from seeded data is "Student 1 GIT"
        $tampered = str_replace('Student 1 GIT', 'Tampered Name Here', $binary);
        file_put_contents($tempPath, $tampered);

        $result = self::$engine->comparePDFWithDatabase($tempPath, TestState::$certificateId);

        // Either metadata_match is false OR pdf_hash_match is false (both are acceptable)
        $somethingDiffers = !$result['metadata_match'] || !$result['pdf_hash_match'];
        $this->assertTrue(
            $somethingDiffers,
            'Tampered PDF should produce at least one mismatch'
        );
        $this->assertFalse($result['match'], 'Overall match should be false for tampered PDF');

        // Do not delete tempPath per no-teardown rule
    }

    // ─── 3. Unknown certificate ID ───────────────────────────────────

    public function testComparePDFDetectsUnknownCertificateId(): void
    {
        $result = self::$engine->comparePDFWithDatabase(
            TestState::$pdfPath,
            'CERT-DOESNOTEXIST-999'
        );

        $this->assertFalse($result['match']);
        $this->assertStringContainsStringIgnoringCase('not found', $result['message']);
    }
}
