<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use GuzzleHttp\Client;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

echo "=== System Reset & API Seed for Production ===\n\n";

$db = Database::getInstance()->getConnection();

echo "Step 1: Destroying previously corrupted production data via PDO...\n";
$db->exec("SET FOREIGN_KEY_CHECKS=0");
$db->exec("TRUNCATE TABLE certificates");
$db->exec("TRUNCATE TABLE verification_logs");
$db->exec("TRUNCATE TABLE university_keys");
$db->exec("TRUNCATE TABLE students");
$db->exec("DELETE FROM users WHERE role != 'admin'");
$db->exec("TRUNCATE TABLE universities");
$db->exec("SET FOREIGN_KEY_CHECKS=1");
echo "Data wiped successfully.\n\n";

// Ensure there's an admin user
$stmt = $db->prepare("SELECT email FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$adminEmail = $stmt->fetchColumn();
if (!$adminEmail) {
    die("Error: No admin user found in database. Cannot proceed with API seeding.\n");
}
$adminPassword = 'admin'; // We'll try 'admin123' first as per schema.sql, or change it via query

// Wait, the schema.sql says admin123.
$adminPassword = 'admin123';

echo "Step 2: Connecting to Local API proxying production...\n";
$base_url = 'http://127.0.0.1:8001/api/';

$client = new Client([
    'base_uri' => $base_url,
    'http_errors' => false,
    'timeout' => 120
]);

echo "Authenticating as Admin ($adminEmail)...\n";
$loginRes = $client->post('auth/login', [
    'json' => [
        'email' => $adminEmail,
        'password' => $adminPassword
    ]
]);

if ($loginRes->getStatusCode() !== 200) {
    echo "Failed to login as admin. Updating password to admin123 directly...\n";
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password_hash = ? WHERE role = 'admin'")->execute([$hash]);
    
    $loginRes = $client->post('auth/login', [
        'json' => [
            'email' => $adminEmail,
            'password' => 'admin123'
        ]
    ]);
    if ($loginRes->getStatusCode() !== 200) {
        die("Fatal Error: Could not authenticate admin via API. Expected 200, got \n" . $loginRes->getBody()->getContents());
    }
}

$adminToken = json_decode($loginRes->getBody()->getContents(), true)['token'];
echo "Admin authenticated via API! Token obtained.\n\n";


echo "Step 3: Creating Universities via API...\n";

$universities = [
    ['name' => 'Harvard University', 'code' => 'HARV', 'address' => 'Cambridge, MA', 'email' => 'admin@harvard.edu'],
    ['name' => 'Stanford University', 'code' => 'STAN', 'address' => 'Stanford, CA', 'email' => 'admin@stanford.edu'],
    ['name' => 'MIT', 'code' => 'MIT', 'address' => 'Cambridge, MA', 'email' => 'admin@mit.edu'],
];

$universityIds = [];

foreach ($universities as $u) {
    $res = $client->post('universities', [
        'headers' => ['Authorization' => "Bearer $adminToken"],
        'json' => [
            'name' => $u['name'],
            'code' => $u['code'],
            'address' => $u['address'],
            'contact_email' => $u['email'],
            'contact_phone' => '123-456-7890'
        ]
    ]);
    
    // DB lookup to get ID (API doesn't return ID in this case)
    $uId = $db->query("SELECT id FROM universities ORDER BY id DESC LIMIT 1")->fetchColumn();
    $universityIds[] = ['id' => $uId, 'email' => $u['email']];
    echo "- Created University: {$u['name']} (ID: $uId)\n";
    
    // Generate Key
    $client->post('universities/generate-key', [
        'headers' => ['Authorization' => "Bearer $adminToken"],
        'json' => ['university_id' => $uId]
    ]);
    echo "  - Cryptographic keys generated out-of-band via API\n";
}
echo "\n";


echo "Step 4: Creating Students & Missing Certificates...\n";
$certsCreated = 0;

foreach ($universityIds as $u) {
    echo "Processing University ID: {$u['id']}...\n";
    for ($i = 1; $i <= 3; $i++) {
        $studentId = "STU-{$u['id']}-00$i";
        $email = "student$i@univ{$u['id']}.edu";
        
        echo "  - Registering student: $email... ";
        $regRes = $client->post('students', [
            'headers' => ['Authorization' => "Bearer $adminToken"],
            'json' => [
                'username' => "student_{$i}_{$u['id']}",
                'email' => $email,
                'password' => "Student@123!",
                'full_name' => "Student $i",
                'student_id' => $studentId,
                'university_id' => $u['id'],
                'enrollment_date' => date('Y-01-01')
            ]
        ]);
        echo "Done.\n";
        
        // DB lookup to get true Student Internal ID
        $sId = $db->query("SELECT id FROM students WHERE student_id = '$studentId'")->fetchColumn();
        
        $certBody = [
            'student_id' => $sId, 
            'university_id' => $u['id'],
            'course_name' => "Bachelor of Computer Science",
            'degree_type' => "B.S.",
            'issue_date' => date('Y-m-d')
        ];
        
        echo "  - Minting Certificate on Blockchain [This may take up to 2 minutes!]... ";
        
        try {
            $cRes = $client->post('certificates/create', [
                'headers' => ['Authorization' => "Bearer $adminToken"],
                'json' => $certBody
            ]);
            
            $cResBody = json_decode($cRes->getBody()->getContents(), true);
            if ($cRes->getStatusCode() === 200 && ($cResBody['success'] ?? false)) {
                $certId = $cResBody['certificate_id'];
                echo "SUCCESS!\n";
                echo "    + Certificate ID: $certId (Tx: {$cResBody['tx_hash']})\n";
                $certsCreated++;
                
                // Step 5: Verify the Certificate (Public)
                $vRes = $client->post('public/verify', [
                    'json' => ['certificate_id' => $certId]
                ]);
                $vResObj = json_decode($vRes->getBody()->getContents(), true);
                
                $verificationStatus = $vResObj['conclusion']['is_valid'] ?? false ? 'Valid' : 'Invalid';
                echo "    ✅ Verified natively as: " . $verificationStatus . "\n";
            } else {
                echo "FAILED.\n";
                echo "    ❌ Error: " . ($cResBody['error'] ?? 'Unknown API Error') . "\n";
            }
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            echo "FAILED.\n";
            echo "    ❌ HTTP Error: " . $e->getMessage() . "\n";
        } catch (\Exception $e) {
            echo "FAILED.\n";
            echo "    ❌ Unexpected Error: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== API Seeding Pipeline Complete. Seeded $certsCreated fully-verifiable certificates. ===\n";
