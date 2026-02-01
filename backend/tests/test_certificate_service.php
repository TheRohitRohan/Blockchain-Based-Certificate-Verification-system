<?php
/**
 * Test 5: Certificate Service Integration Tests
 * 
 * Purpose: Test complete certificate creation, verification, and blockchain integration
 */

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use App\CertificateService;
use App\Database;

echo "=== CERTIFICATE SERVICE INTEGRATION TEST ===\n\n";

$testResults = [
    'passed' => 0,
    'failed' => 0,
    'total' => 0
];

function runTest($testName, $testFunction) {
    global $testResults;
    $testResults['total']++;
    
    try {
        $result = $testFunction();
        if ($result) {
            echo "✅ PASS: $testName\n";
            $testResults['passed']++;
        } else {
            echo "❌ FAIL: $testName\n";
            $testResults['failed']++;
        }
    } catch (Exception $e) {
        echo "❌ FAIL: $testName - " . $e->getMessage() . "\n";
        $testResults['failed']++;
    }
}

runTest("Certificate Service Instantiation", function() {
    try {
        $certService = new CertificateService();
        return true;
    } catch (Exception $e) {
        return false;
    }
});

runTest("Create Certificate with Valid Data", function() {
    $certService = new CertificateService();
    
    // Get existing university and student for testing
    $db = Database::getInstance()->getConnection();
    $university = $db->query("SELECT id FROM universities LIMIT 1")->fetch();
    $student = $db->query("SELECT s.id FROM students s JOIN users u ON s.user_id = u.id LIMIT 1")->fetch();
    
    if (!$university || !$student) {
        return false;
    }
    
    $certificateData = [
        'student_id' => $student['id'],
        'university_id' => $university['id'],
        'course_name' => 'Test Certificate Service',
        'degree_type' => 'Test Certificate',
        'issue_date' => '2024-12-01'
    ];
    
    $result = $certService->createCertificate($certificateData);
    return $result['success'] && 
           !empty($result['certificate_id']) && 
           !empty($result['certificate_hash']);
});

runTest("Certificate ID Uniqueness", function() {
    $certService = new CertificateService();
    
    // Try to create duplicate certificate with same data
    $db = Database::getInstance()->getConnection();
    $university = $db->query("SELECT id FROM universities LIMIT 1")->fetch();
    $student = $db->query("SELECT s.id FROM students s JOIN users u ON s.user_id = u.id LIMIT 1")->fetch();
    
    if (!$university || !$student) {
        return false;
    }
    
    $certificateData = [
        'student_id' => $student['id'],
        'university_id' => $university['id'],
        'course_name' => 'Test Certificate Service',
        'degree_type' => 'Test Certificate',
        'issue_date' => '2024-12-01'
    ];
    
    $result1 = $certService->createCertificate($certificateData);
    $result2 = $certService->createCertificate($certificateData);
    
    // First should succeed, second might fail due to unique constraint
    return $result1['success'] && 
           (!empty($result1['certificate_id'])) &&
           (!empty($result1['certificate_hash'])) &&
           $result1['certificate_id'] !== ($result2['certificate_id'] ?? null);
});

runTest("Get Certificate Details", function() {
    $certService = new CertificateService();
    
    // Get any existing certificate
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT certificate_id FROM certificates LIMIT 1");
    $cert = $stmt->fetch();
    
    if (!$cert) {
        return false;
    }
    
    $certificate = $certService->getCertificate($cert['certificate_id']);
    return $certificate !== null && 
           isset($certificate['student_name']) && 
           isset($certificate['university_name']) &&
           isset($certificate['course_name']);
});

runTest("Get Student Certificates", function() {
    $certService = new CertificateService();
    
    // Get any student ID
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id FROM students LIMIT 1");
    $student = $stmt->fetch();
    
    if (!$student) {
        return false;
    }
    
    $certificates = $certService->getStudentCertificates($student['id']);
    return is_array($certificates) && 
           (count($certificates) >= 0); // Could be empty array
});

runTest("Verify Valid Certificate", function() {
    $certService = new CertificateService();
    
    // Get any existing certificate
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT certificate_id, certificate_hash FROM certificates LIMIT 1");
    $cert = $stmt->fetch();
    
    if (!$cert) {
        return false;
    }
    
    $result = $certService->verifyCertificate($cert['certificate_id'], $cert['certificate_hash']);
    return $result['valid'] && $result['status'] === 'valid';
});

runTest("Verify Non-existent Certificate", function() {
    $certService = new CertificateService();
    
    $result = $certService->verifyCertificate('NONEXISTENT-CERT-123', 'fakehash');
    return $result['valid'] === false && $result['status'] === 'not_found';
});

runTest("Verify with Wrong Hash", function() {
    $certService = new CertificateService();
    
    // Get any existing certificate
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT certificate_id FROM certificates LIMIT 1");
    $cert = $stmt->fetch();
    
    if (!$cert) {
        return false;
    }
    
    $result = $certService->verifyCertificate($cert['certificate_id'], 'wronghash123');
    return $result['valid'] === false && $result['status'] === 'invalid';
});

runTest("Revoke Certificate", function() {
    $certService = new CertificateService();
    
    // Create a certificate specifically for revocation test
    $db = Database::getInstance()->getConnection();
    $university = $db->query("SELECT id FROM universities LIMIT 1")->fetch();
    $student = $db->query("SELECT s.id FROM students s JOIN users u ON s.user_id = u.id LIMIT 1")->fetch();
    
    if (!$university || !$student) {
        return false;
    }
    
    $certificateData = [
        'student_id' => $student['id'],
        'university_id' => $university['id'],
        'course_name' => 'To Be Revoked',
        'degree_type' => 'Test Certificate',
        'issue_date' => '2024-12-01'
    ];
    
    $createResult = $certService->createCertificate($certificateData);
    
    if (!$createResult['success']) {
        return false;
    }
    
    // Now revoke it (using a different method name to avoid conflicts)
    $revokeResult = $certService->revokeCertificate($createResult['certificate_id'], 1);
    
    // Verify it's now revoked
    $verifyResult = $certService->verifyCertificate($createResult['certificate_id'], $createResult['certificate_hash']);
    return $verifyResult['valid'] === false && $verifyResult['status'] === 'revoked';
    
    if ($revokeResult) {
        // Verify it's now revoked
        $verifyResult = $certService->verifyCertificate($createResult['certificate_id']);
        return $verifyResult['valid'] === false && $verifyResult['status'] === 'revoked';
    }
    
    return false;
});

runTest("Certificate Data Validation", function() {
    $certService = new CertificateService();
    
    // Test with missing required fields
    $invalidData = [
        'student_id' => '', // Missing
        'university_id' => 1,
        'course_name' => '',
        'issue_date' => 'invalid-date'
    ];
    
    $result = $certService->createCertificate($invalidData);
    return !$result['success'] || empty($result['certificate_id']);
});

runTest("Blockchain Hash Generation", function() {
    $certService = new CertificateService();
    $db = Database::getInstance()->getConnection();
    
    // Get a certificate with its hash
    $stmt = $db->query("SELECT * FROM certificates WHERE certificate_hash IS NOT NULL LIMIT 1");
    $cert = $stmt->fetch();
    
    if (!$cert) {
        return false;
    }
    
    // Hash should be 64 characters (SHA256)
    return !empty($cert['certificate_hash']) && strlen($cert['certificate_hash']) === 64;
});

runTest("Verification Logging", function() {
    $certService = new CertificateService();
    
    // Perform a verification to trigger logging
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT certificate_id, certificate_hash FROM certificates LIMIT 1");
    $cert = $stmt->fetch();
    
    if (!$cert) {
        return false;
    }
    
    // Initial count
    $initialCount = $db->query("SELECT COUNT(*) as count FROM verification_logs")->fetch()['count'];
    
    // Perform verification
    $certService->verifyCertificate($cert['certificate_id'], $cert['certificate_hash']);
    
    // Check if log was created
    $finalCount = $db->query("SELECT COUNT(*) as count FROM verification_logs")->fetch()['count'];
    
    return $finalCount > $initialCount;
});

// Display test results
echo "\n=== TEST RESULTS ===\n";
echo "Total Tests: {$testResults['total']}\n";
echo "Passed: {$testResults['passed']}\n";
echo "Failed: {$testResults['failed']}\n";
echo "Success Rate: " . round(($testResults['passed'] / $testResults['total']) * 100, 2) . "%\n";

if ($testResults['failed'] === 0) {
    echo "\n🎉 ALL CERTIFICATE SERVICE TESTS PASSED! 🎉\n";
} else {
    echo "\n⚠️ Some certificate service tests failed. Check database or blockchain setup.\n";
}

echo "\n=== CERTIFICATE STATISTICS ===\n";
$db = Database::getInstance()->getConnection();

// Total certificates
$stmt = $db->query("SELECT COUNT(*) as count FROM certificates");
$count = $stmt->fetch()['count'];
echo "Total Certificates: $count\n";

// Certificates by status
$stmt = $db->query("
    SELECT status, COUNT(*) as count 
    FROM certificates 
    GROUP BY status
");
echo "Certificates by Status:\n";
while ($row = $stmt->fetch()) {
    echo "  {$row['status']}: {$row['count']}\n";
}

// Recent certificates
$stmt = $db->query("
    SELECT certificate_id, course_name, issue_date 
    FROM certificates 
    ORDER BY created_at DESC 
    LIMIT 3
");
echo "\nRecent Certificates:\n";
while ($row = $stmt->fetch()) {
    echo "  {$row['certificate_id']} - {$row['course_name']} ({$row['issue_date']})\n";
}

// Verification statistics
$stmt = $db->query("
    SELECT verification_result, COUNT(*) as count 
    FROM verification_logs 
    GROUP BY verification_result
");
echo "\nVerification Statistics:\n";
while ($row = $stmt->fetch()) {
    echo "  {$row['verification_result']}: {$row['count']}\n";
}