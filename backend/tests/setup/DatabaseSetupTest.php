<?php

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Auth;
use App\SignatureService;

/**
 * Suite 1 — Database Setup
 *
 * Verifies ANY baseline data exists in the REAL MySQL database without
 * relying on known seed values. Bypasses strict passwords by generating JWTs 
 * manually against existing DB entities for API tests.
 */
class DatabaseSetupTest extends TestCase
{
    private static \PDO $db;
    private static Auth $auth;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance()->getConnection();
        self::$auth = new Auth();
    }

    // ─── 1. University ────────────────────────────────────────────────

    public function testUniversityExists(): void
    {
        // Find ANY active university
        $stmt = self::$db->query(
            "SELECT id, name, code, contact_email 
             FROM universities 
             WHERE is_active = 1 
             LIMIT 1"
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, "No active university found in DB! Please manually register one or seed the DB.");
        
        TestState::$universityId = (int) $row['id'];
        TestState::$universityName = $row['name'];
        TestState::$universityCode = $row['code'];
    }

    // ─── 2. University Key Pair ───────────────────────────────────────

    /** @depends testUniversityExists */
    public function testUniversityKeyPairExists(): void
    {
        $stmt = self::$db->prepare(
            "SELECT key_fingerprint FROM university_keys
             WHERE university_id = ? AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute([TestState::$universityId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            TestState::$keyFingerprint = $row['key_fingerprint'];
        } else {
            // Generate a real key if none exists
            $sigService = new SignatureService();
            $result = $sigService->generateUniversityKeyPair(
                TestState::$universityId,
                TestState::$universityName
            );
            
            if ($result === null) {
                $this->markTestSkipped('OpenSSL key generation failed on this platform');
                return;
            }
            TestState::$keyFingerprint = $result['fingerprint'];
        }

        $this->assertNotEmpty(TestState::$keyFingerprint);
    }

    // ─── 3. University User & JWT ────────────────────────────────────

    /** @depends testUniversityExists */
    public function testUniversityUserExistsAndGenerateJwt(): void
    {
        // Find any user assigned to this university id
        $stmt = self::$db->prepare(
            "SELECT id, username, email, role 
             FROM users 
             WHERE university_id = ? OR role = 'university' 
             LIMIT 1"
        );
        $stmt->execute([TestState::$universityId]);
        $userObj = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->assertNotFalse($userObj, "No university user found for university ID " . TestState::$universityId);

        TestState::$universityEmail = $userObj['email'];

        // Directly generate a token without knowing the plaintext password
        $token = self::$auth->generateToken($userObj);
        $this->assertNotEmpty($token);
        
        TestState::$universityJwt = $token;
    }

    // ─── 4. Student Record & JWT ─────────────────────────────────────

    /** @depends testUniversityExists */
    public function testStudentRecordExistsAndGenerateJwt(): void
    {
        // Find ANY student enrolled in the given university
        $stmt = self::$db->prepare(
            "SELECT s.id as student_record_id, u.id as user_id, u.email, u.role, u.username 
             FROM students s
             JOIN users u ON s.user_id = u.id
             WHERE s.university_id = ? 
             LIMIT 1"
        );
        $stmt->execute([TestState::$universityId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, "No student record found for university ID " . TestState::$universityId);

        TestState::$studentId = (int) $row['student_record_id'];
        TestState::$userId = (int) $row['user_id'];
        TestState::$studentEmail = $row['email'];

        // Generate student JWT manually
        $token = self::$auth->generateToken([
            'id' => $row['user_id'],
            'email' => $row['email'],
            'role' => $row['role'],
        ]);
        $this->assertNotEmpty($token);
        TestState::$studentJwt = $token;
    }

    // ─── 5. Admin User & JWT ─────────────────────────────────────────

    public function testAdminUserExistsAndGenerateJwt(): void
    {
        // Grab the first admin
        $stmt = self::$db->query(
            "SELECT id, username, email, role 
             FROM users 
             WHERE role = 'admin' 
             LIMIT 1"
        );
        $adminObj = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($adminObj, "No admin user found in database");

        TestState::$adminEmail = $adminObj['email'];

        // Generate token
        $token = self::$auth->generateToken($adminObj);
        $this->assertNotEmpty($token);

        TestState::$adminJwt = $token;
    }
}
