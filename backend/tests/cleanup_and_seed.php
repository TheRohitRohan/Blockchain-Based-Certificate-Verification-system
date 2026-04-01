<?php

// Load composer autoloader and environment
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;
use App\Auth;
use App\SignatureService;

error_reporting(E_ALL);
ini_set('display_errors', 1);

$db = Database::getInstance()->getConnection();
$auth = new Auth();
$signatureService = new SignatureService();

echo "=== Starting Database Cleanup and Seeding ===\n\n";

try {
    // Step 1: Get admin user ID
    echo "Step 1: Getting admin user...\n";
    $stmt = $db->prepare("SELECT id, email FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $adminUser = $stmt->fetch();
    
    if (!$adminUser) {
        throw new Exception("Admin user not found!");
    }
    
    echo "  Admin user found: {$adminUser['email']} (ID: {$adminUser['id']})\n\n";
    
    // Step 2: Delete non-admin data (certificates, students, non-admin users, universities)
    echo "Step 2: Cleaning database (keeping admin data)...\n";
    
    // Delete certificates (they depend on students)
    $stmt = $db->prepare("DELETE FROM certificates");
    $stmt->execute();
    echo "  Deleted certificates\n";
    
    // Delete students (they depend on users)
    $stmt = $db->prepare("DELETE FROM students");
    $stmt->execute();
    echo "  Deleted students\n";
    
    // Delete non-admin users
    $stmt = $db->prepare("DELETE FROM users WHERE role != 'admin'");
    $stmt->execute();
    echo "  Deleted non-admin users\n";
    
    // Delete universities
    $stmt = $db->prepare("DELETE FROM universities");
    $stmt->execute();
    echo "  Deleted universities\n\n";
    
    // Step 3: Add 5 universities
    echo "Step 3: Adding 5 universities...\n";
    
    $universities = [
        [
            'name' => 'Global Institute of Technology',
            'code' => 'GIT',
            'address' => '123 Tech Street, Silicon Valley, CA 94025',
            'contact_email' => 'admin@git.edu',
            'contact_phone' => '+1-650-123-4567'
        ],
        [
            'name' => 'National University of Engineering',
            'code' => 'NUE',
            'address' => '456 Engineering Ave, Boston, MA 02115',
            'contact_email' => 'admin@nue.edu',
            'contact_phone' => '+1-617-123-4567'
        ],
        [
            'name' => 'Oxford International College',
            'code' => 'OIC',
            'address' => '789 Academic Road, London, UK',
            'contact_email' => 'admin@oic.ac.uk',
            'contact_phone' => '+44-20-1234-5678'
        ],
        [
            'name' => 'University of Advanced Studies',
            'code' => 'UAS',
            'address' => '321 Innovation Drive, Toronto, ON',
            'contact_email' => 'admin@uas.ca',
            'contact_phone' => '+1-416-123-4567'
        ],
        [
            'name' => 'Asian Institute of Business & Technology',
            'code' => 'AIBT',
            'address' => '654 Development Road, Singapore 039594',
            'contact_email' => 'admin@aibt.sg',
            'contact_phone' => '+65-6123-4567'
        ]
    ];
    
    $universityIds = [];
    $preparedStmt = $db->prepare("
        INSERT INTO universities (name, code, address, contact_email, contact_phone, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    
    foreach ($universities as $idx => $uni) {
        $preparedStmt->execute([
            $uni['name'],
            $uni['code'],
            $uni['address'],
            $uni['contact_email'],
            $uni['contact_phone']
        ]);
        $universityId = $db->lastInsertId();
        $universityIds[] = $universityId;
        echo "  ✓ Added {$uni['name']} (ID: $universityId, Code: {$uni['code']})\n";
    }
    echo "\n";
    
    // Step 4: Add 5 students for each university
    echo "Step 4: Adding 5 students for each university...\n\n";
    
    $studentCounter = 1;
    $studentInsertStmt = $db->prepare("
        INSERT INTO students (user_id, student_id, university_id, enrollment_date)
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($universityIds as $uniIdx => $universityId) {
        $uni = $universities[$uniIdx];
        echo "  Adding students for {$uni['name']}...\n";
        
        for ($i = 1; $i <= 5; $i++) {
            $username = strtolower($uni['code']) . "std" . str_pad($studentCounter, 3, '0', STR_PAD_LEFT);
            $email = $username . '@' . strtolower(str_replace(' ', '', $uni['code'])) . '.edu';
            $studentId = $uni['code'] . '-' . str_pad($studentCounter, 4, '0', STR_PAD_LEFT);
            $password = 'Student@123!';
            $fullName = "Student {$studentCounter} " . $uni['code'];
            
            // Register user
            $userData = [
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => 'student',
                'full_name' => $fullName,
                'university_id' => null
            ];
            
            if ($auth->register($userData)) {
                $newUser = $auth->login($email, $password);
                
                if ($newUser) {
                    // Create student record
                    $studentInsertStmt->execute([
                        $newUser['id'],
                        $studentId,
                        $universityId,
                        date('Y-m-d')
                    ]);
                    
                    echo "    ✓ {$fullName} (ID: {$studentId}, Email: {$email}, Pass: {$password})\n";
                    $studentCounter++;
                }
            }
        }
        echo "\n";
    }
    
    // Step 5: Generate keys for each university
    echo "Step 5: Generating RSA key pairs for universities...\n\n";
    
    foreach ($universityIds as $uniIdx => $universityId) {
        $uni = $universities[$uniIdx];
        try {
            $signatureService->generateUniversityKeyPair($universityId, $uni['name']);
            echo "  ✓ Generated keys for {$uni['name']} (ID: $universityId)\n";
        } catch (Exception $e) {
            echo "  ✗ Error generating keys for {$uni['name']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== Database Cleanup and Seeding Complete ===\n";
    echo "Summary:\n";
    echo "  - Cleaned all non-admin data\n";
    echo "  - Added 5 universities\n";
    echo "  - Added 25 students (5 per university)\n";
    echo "  - Generated RSA key pairs for all universities\n";
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
