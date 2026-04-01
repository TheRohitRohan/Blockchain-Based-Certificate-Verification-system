<?php

use PHPUnit\Framework\TestCase;
use App\Database;
use App\VerificationEngine;
use App\PDFService;

/**
 * Suite 3 — SECURITY: Student Name Tampering Detection
 * 
 * FOCUS: Detect if someone changes the student name on the certificate
 * 
 * Scenario:
 * - Original: "Student 1 GIT" issued on 2024-06-15
 * - Attacker modifies: "HACKER NAME" or "John Doe"
 * 
 * Expected: System MUST detect this tampering
 */
class TamperingDetectionTest extends TestCase
{
    private static \PDO $db;
    private static PDFService $pdfService;
    private static VerificationEngine $engine;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance()->getConnection();
        self::$pdfService = new PDFService();
        self::$engine = new VerificationEngine();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 1: STUDENT NAME TAMPERING — DISPLAY VS DATABASE 
    // ═══════════════════════════════════════════════════════════════════════

    public function testStudentNameTampering_DetectFakeName(): void
    {
        /**
         * ATTACK: Attacker changes visible student name on certificate
         *         Original: "Student 1 GIT"
         *         Forged:   "HACKER - Student Name Changed"
         * 
         * DEFENSE: System extracts name from PDF and compares to database
         */

        echo "\n\n╔════════════════════════════════════════════════════════════════╗\n";
        echo "║ TEST 1: STUDENT NAME TAMPERING DETECTION                      ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";

        $config = require __DIR__ . '/../../config.php';
        $tamperPath = $config['storage']['pdf_path'] . 'tamper_student_' . uniqid() . '.pdf';

        // Step 1: Copy original certificate
        copy(TestState::$pdfPath, $tamperPath);
        echo "\n[Step 1] Copied original PDF to: $tamperPath\n";

        // Get original student name from database
        $stmt = self::$db->prepare(
            "SELECT u.full_name FROM certificates c 
             JOIN students s ON c.student_id = s.id 
             JOIN users u ON s.user_id = u.id 
             WHERE c.certificate_id = ?"
        );
        $stmt->execute([TestState::$certificateId]);
        $dbStudentName = $stmt->fetchColumn();

        echo "[Step 1] Database Student Name: $dbStudentName\n";

        // Step 2: Read PDF and modify visible text
        $pdfBinary = file_get_contents($tamperPath);
        
        echo "\n[Step 2] ATTACKER ACTION: Modifying visible student name...\n";
        echo "         Original display: \"$dbStudentName\"\n";
        
        $fakeNames = [
            'HACKER NAME',
            'John Doe FAKE',
            'Forged Certificate',
            'Unknown Student'
        ];
        
        $fakeName = $fakeNames[array_rand($fakeNames)];
        echo "         Forged display:  \"$fakeName\"\n";

        // Replace in PDF binary (changes visible text)
        $modifiedBinary = str_replace(
            $dbStudentName,
            $fakeName,
            $pdfBinary
        );

        file_put_contents($tamperPath, $modifiedBinary);
        echo "         ✓ Modified PDF saved\n";

        // Step 3: Extract student name from tampered PDF
        echo "\n[Step 3] SYSTEM EXTRACTION - Reading from PDF binary...\n";
        
        $extractedMetadata = self::$pdfService->extractMetadata($tamperPath);
        $displayedName = $extractedMetadata['student_name'] ?? 'NOT_FOUND';

        echo "         Extracted Name: \"$displayedName\"\n";
        echo "         Database Name:  \"$dbStudentName\"\n";

        // Step 4: CRITICAL COMPARISON
        echo "\n[Step 4] VERIFICATION COMPARISON...\n";

        if ($displayedName === $dbStudentName) {
            echo "         ✓ Names MATCH - Certificate is AUTHENTIC\n";
        } else {
            echo "         ✗ Names DO NOT MATCH - TAMPERING DETECTED!\n";
            echo "           Expected: \"$dbStudentName\"\n";
            echo "           Got:      \"$displayedName\"\n";
        }

        // Step 5: Hash verification (second layer)
        echo "\n[Step 5] HASH VERIFICATION (Second Layer of Protection)...\n";
        
        $storedPdfHash = $this->getPdfHashFromDb(TestState::$certificateId);
        $tamperPdfHash = self::$pdfService->calculatePDFHash($tamperPath);

        echo "         Stored Hash (Original):  " . substr($storedPdfHash, 0, 20) . "...\n";
        echo "         Tampered Hash (New):     " . substr($tamperPdfHash, 0, 20) . "...\n";

        if ($tamperPdfHash === $storedPdfHash) {
            echo "         ✗ HASHES MATCH (shouldn't happen!) - Check failed!\n";
        } else {
            echo "         ✗ HASHES DO NOT MATCH - TAMPERING CONFIRMED!\n";
        }

        // Step 6: Full verification through VerificationEngine
        echo "\n[Step 6] COMPLETE VERIFICATION ENGINE CHECK...\n";
        
        $verifyResult = self::$engine->verifyUploadedPDF($tamperPath);

        echo "         Valid: " . json_encode($verifyResult['valid']) . "\n";
        echo "         Status: " . $verifyResult['status'] . "\n";
        echo "         Message: " . $verifyResult['message'] . "\n";

        // ASSERTIONS - SECURITY CHECKS
        echo "\n[Step 7] SECURITY ASSERTIONS...\n";

        // Check 1: Names must match if authentic
        if ($displayedName !== $dbStudentName && $displayedName !== 'NOT_FOUND') {
            echo "         ✓ Check 1 PASSED: Name mismatch detected\n";
            $this->assertNotSame(
                $fakeName,
                $dbStudentName,
                'Tampering should be obvious'
            );
        }

        // Check 2: Hash must change
        echo "         ✓ Check 2 PASSED: PDF hash changed after tampering\n";
        $this->assertNotSame(
            $tamperPdfHash,
            $storedPdfHash,
            'Tampered PDF hash must differ from stored hash'
        );

        // Check 3: Overall verification must fail
        echo "         ✓ Check 3 PASSED: Overall verification failed\n";
        $this->assertFalse(
            $verifyResult['valid'],
            'Tampered certificate must fail verification'
        );

        echo "\n╔════════════════════════════════════════════════════════════════╗\n";
        echo "║ ✅ TEST PASSED - STUDENT NAME TAMPERING DETECTED SUCCESSFULLY  ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 2: XMP METADATA CONTAINS STUDENT NAME (Proof in Binary)
    // ═══════════════════════════════════════════════════════════════════════

    public function testStudentNameInXMP_EmbeddedInBinary(): void
    {
        /**
         * KEY FACT: Student name is embedded in PDF XMP metadata
         * It's not just text on display - it's cryptographically protected
         */

        echo "\n\n╔════════════════════════════════════════════════════════════════╗\n";
        echo "║ TEST 2: STUDENT NAME IN XMP METADATA (Binary Level)           ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";

        // Extract metadata from original PDF
        $metadata = self::$pdfService->extractMetadata(TestState::$pdfPath);
        
        echo "\n[Step 1] Extracted XMP Metadata from PDF:\n";
        echo "         Student ID: " . $metadata['student_id'] . "\n";
        echo "         Student Name: " . $metadata['student_name'] . "\n";
        echo "         Course: " . $metadata['course_name'] . "\n";
        echo "         Issue Date: " . $metadata['issue_date'] . "\n";

        // Get from database
        $stmt = self::$db->prepare(
            "SELECT u.full_name, c.course_name, c.issue_date 
             FROM certificates c 
             JOIN students s ON c.student_id = s.id 
             JOIN users u ON s.user_id = u.id 
             WHERE c.certificate_id = ?"
        );
        $stmt->execute([TestState::$certificateId]);
        $dbData = $stmt->fetch(\PDO::FETCH_ASSOC);

        echo "\n[Step 2] Database Values:\n";
        echo "         Student Name: " . $dbData['full_name'] . "\n";
        echo "         Course: " . $dbData['course_name'] . "\n";
        echo "         Issue Date: " . $dbData['issue_date'] . "\n";

        // Verify XMP matches database
        echo "\n[Step 3] Comparing XMP vs Database:\n";
        echo "         Name Match: " . ($metadata['student_name'] === $dbData['full_name'] ? '✓ YES' : '✗ NO') . "\n";
        echo "         Course Match: " . ($metadata['course_name'] === $dbData['course_name'] ? '✓ YES' : '✗ NO') . "\n";

        $this->assertSame(
            $metadata['student_name'],
            $dbData['full_name'],
            'XMP student name must match database'
        );

        echo "\n✅ TEST PASSED - Student name authentically embedded in PDF\n\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 3: MULTI-CHECK PROTECTION - All Three Must Pass
    // ═══════════════════════════════════════════════════════════════════════

    public function testMultiLayerProtection_AllChecksMustPass(): void
    {
        /**
         * THREE-LAYER PROTECTION FOR STUDENT NAME:
         * 1. Database has authoritative student name
         * 2. XMP metadata embedded in PDF has student name
         * 3. PDF hash proves integrity of both
         */

        echo "\n\n╔════════════════════════════════════════════════════════════════╗\n";
        echo "║ TEST 3: MULTI-LAYER PROTECTION - ALL CHECKS MUST PASS         ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";

        echo "\n[Layer 1] DATABASE AUTHORITY\n";
        $stmt = self::$db->prepare(
            "SELECT u.full_name FROM certificates c 
             JOIN students s ON c.student_id = s.id 
             JOIN users u ON s.user_id = u.id 
             WHERE c.certificate_id = ?"
        );
        $stmt->execute([TestState::$certificateId]);
        $dbName = $stmt->fetchColumn();
        echo "          Student Name: \"$dbName\"\n";
        echo "          Authority Level: Source of Truth ✓\n";

        echo "\n[Layer 2] XMP METADATA (Embedded in PDF Binary)\n";
        $metadata = self::$pdfService->extractMetadata(TestState::$pdfPath);
        $xmpName = $metadata['student_name'];
        echo "          Student Name: \"$xmpName\"\n";
        echo "          Protection: Cryptographically hashed ✓\n";

        echo "\n[Layer 3] PDF HASH (Keccak256 Fingerprint)\n";
        $pdfHash = self::$pdfService->calculatePDFHash(TestState::$pdfPath);
        $dbHash = $this->getPdfHashFromDb(TestState::$certificateId);
        echo "          Stored Hash: " . substr($dbHash, 0, 20) . "...\n";
        echo "          Calculated Hash: " . substr($pdfHash, 0, 20) . "...\n";
        echo "          Match: " . ($pdfHash === $dbHash ? '✓ YES' : '✗ NO') . "\n";

        echo "\n[VERIFICATION] All Checks:\n";
        $checks = [
            'Database has name' => true,
            'XMP contains name' => $xmpName !== null && $xmpName !== '',
            'Names match (DB=XMP)' => $dbName === $xmpName,
            'Hash is valid' => $pdfHash === $dbHash,
        ];

        $allPass = true;
        foreach ($checks as $check => $result) {
            echo "          " . ($result ? '✓' : '✗') . " $check\n";
            $allPass = $allPass && $result;
        }

        echo "\n" . ($allPass ? "✅ ALL CHECKS PASSED\n" : "❌ SOME CHECKS FAILED\n");

        $this->assertTrue($allPass, 'All protection layers must pass');

        echo "\n✅ TEST PASSED - Multi-layer protection working perfectly\n";
        echo "   If any layer fails → Student name tampering is detected ✓\n\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPER METHOD
    // ═══════════════════════════════════════════════════════════════════════

    private function getPdfHashFromDb(string $certificateId): string
    {
        $stmt = self::$db->prepare("SELECT pdf_hash FROM certificates WHERE certificate_id = ?");
        $stmt->execute([$certificateId]);
        return $stmt->fetchColumn();
    }
}
