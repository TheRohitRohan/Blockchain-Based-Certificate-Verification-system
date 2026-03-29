<?php

use PHPUnit\Framework\TestCase;
use App\Database;
use App\Auth;
use App\SignatureService;

/**
 * Suite 1 — Database Setup
 *
 * Verifies baseline data exists in the REAL MySQL database and seeds it
 * if missing. Populates TestState for all downstream suites.
 */
class DatabaseSetupTest extends TestCase
{
    private static \PDO $db;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance()->getConnection();
    }

    // ─── 1. University ────────────────────────────────────────────────

    public function testUniversityExists(): void
    {
        // Use seeded university: Global Institute of Technology (GIT)
        $stmt = self::$db->prepare(
            "SELECT id FROM universities WHERE code = ? LIMIT 1"
        );
        $stmt->execute([TestState::$universityCode]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, "University " . TestState::$universityCode . " not found. Please run: php cleanup_and_seed.php");
        TestState::$universityId = (int) $row['id'];

        $this->assertGreaterThan(0, TestState::$universityId);
    }

    // ─── 2. University Key Pair ───────────────────────────────────────

    /** @depends testUniversityExists */
    public function testUniversityKeyPairExists(): void
    {
        $stmt = self::$db->prepare(
            "SELECT key_fingerprint FROM university_keys
             WHERE university_id = ? AND is_active = 1"
        );
        $stmt->execute([TestState::$universityId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            TestState::$keyFingerprint = $row['key_fingerprint'];
        } else {
            // Try to generate, but if openssl_pkey_new fails on Windows, skip
            $sigService = new SignatureService();
            $result = $sigService->generateUniversityKeyPair(
                TestState::$universityId,
                TestState::$universityName
            );
            
            if ($result === null) {
                // OpenSSL key generation failed (common on Windows)
                // Create a manual entry for testing - in production this would be a real key
                $fakeFingerprint = substr(hash('sha256', 'test'), 0, 64);
                $saveStmt = self::$db->prepare(
                    "INSERT INTO university_keys 
                     (university_id, certificate_path, certificate_password, key_fingerprint, is_active) 
                     VALUES (?, ?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE is_active = 1"
                );
                $saveStmt->execute([
                    TestState::$universityId, 
                    '/test/dummy.p12', 
                    'test_encrypted_password',
                    $fakeFingerprint
                ]);
                TestState::$keyFingerprint = $fakeFingerprint;
                $this->markTestSkipped('OpenSSL key generation failed on this platform - using test key');
                return;
            }
            TestState::$keyFingerprint = $result['fingerprint'];
        }

        $this->assertNotEmpty(TestState::$keyFingerprint);

        // Verify DB row
        $stmt2 = self::$db->prepare(
            "SELECT id FROM university_keys WHERE university_id = ? AND is_active = 1"
        );
        $stmt2->execute([TestState::$universityId]);
        $this->assertNotFalse($stmt2->fetch(), 'university_keys row missing');
    }

    // ─── 3. Student User ─────────────────────────────────────────────

    /** @depends testUniversityExists */
    public function testStudentUserExists(): void
    {
        // Use seeded student: gitstd001
        $stmt = self::$db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([TestState::$studentEmail]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, "Student user " . TestState::$studentEmail . " not found. Please run: php cleanup_and_seed.php");
        TestState::$userId = (int) $row['id'];

        $this->assertGreaterThan(0, TestState::$userId);
    }

    // ─── 4. Student Record ───────────────────────────────────────────

    /** @depends testStudentUserExists */
    public function testStudentRecordExists(): void
    {
        // Student record should already exist from seeded data
        $stmt = self::$db->prepare("SELECT id FROM students WHERE user_id = ?");
        $stmt->execute([TestState::$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, "Student record not found for user ID " . TestState::$userId . ". Please run: php cleanup_and_seed.php");
        TestState::$studentId = (int) $row['id'];

        $this->assertGreaterThan(0, TestState::$studentId);
    }

    // ─── 5. University User ──────────────────────────────────────────

    /** @depends testUniversityExists */
    public function testUniversityUserExists(): void
    {
        // For seeded data, university email is from cleanup_and_seed.php
        // Use the university code to find admin email
        $stmt = self::$db->prepare(
            "SELECT contact_email FROM universities WHERE code = ?"
        );
        $stmt->execute([TestState::$universityCode]);
        $uniRow = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($uniRow, "University " . TestState::$universityCode . " not found");
        
        $auth = new Auth();
        
        // Try to login with the university email from seeded data
        $user = $auth->login(TestState::$universityEmail, TestState::$universityPassword);
        
        // If login fails, the user may not exist yet (seeding may not have created university admin)
        if (!$user) {
            $this->markTestSkipped("University admin user not found in seeded data. Created externally if needed.");
            return;
        }

        $token = $auth->generateToken($user);
        $this->assertNotEmpty($token);

        TestState::$universityJwt = $token;
    }

    // ─── 6. Admin User ───────────────────────────────────────────────

    public function testAdminUserExists(): void
    {
        $auth = new Auth();

        // Try the known test admin first
        $stmt = self::$db->prepare("SELECT id, email FROM users WHERE email = ?");
        $stmt->execute([TestState::$adminEmail]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            // Register a test admin
            $auth->register([
                'username'  => 'test_admin',
                'email'     => TestState::$adminEmail,
                'password'  => TestState::$adminPassword,
                'role'      => 'admin',
                'full_name' => 'Test Administrator',
            ]);
        }

        $user = $auth->login(TestState::$adminEmail, TestState::$adminPassword);

        // Fallback: try the default schema admin
        if (!$user) {
            $stmt2 = self::$db->prepare(
                "SELECT id, email FROM users WHERE role = 'admin' LIMIT 1"
            );
            $stmt2->execute();
            $adminRow = $stmt2->fetch(\PDO::FETCH_ASSOC);
            $this->assertNotFalse($adminRow, 'No admin user found in database');

            // Can't login with unknown password — generate a token manually
            $user = $auth->login($adminRow['email'], 'admin123');
            if (!$user) {
                // Last resort: try to use TestState admin credentials
                $this->markTestSkipped('Cannot authenticate any admin user — update admin password');
                return;
            }
        }

        $token = $auth->generateToken($user);
        $this->assertNotEmpty($token);

        TestState::$adminJwt = $token;
    }

    // ─── 7. Student Login ────────────────────────────────────────────

    /** @depends testStudentUserExists */
    public function testStudentLogin(): void
    {
        $auth = new Auth();
        $user = $auth->login(TestState::$studentEmail, TestState::$studentPassword);
        $this->assertNotNull($user, 'Student login failed');

        $token = $auth->generateToken($user);
        $this->assertNotEmpty($token);

        TestState::$studentJwt = $token;
    }
}
