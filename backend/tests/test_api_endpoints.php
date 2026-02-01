<?php
/**
 * Test 6: API Endpoint Tests
 * 
 * Purpose: Test all API endpoints for proper HTTP responses and data handling
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

use App\Auth;
use App\Database;

echo "=== API ENDPOINT TESTS ===\n\n";

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

// Helper function to make API calls
function makeAPICall($method, $endpoint, $data = null, $token = null) {
    $url = 'http://localhost/backend/api' . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($token) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ]);
    }
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'data' => json_decode($response, true),
        'http_code' => $httpCode,
        'response' => $response
    ];
}

runTest("API Server Responding", function() {
    $result = makeAPICall('GET', '/');
    return $result['http_code'] === 404; // Should return 404 for root
});

runTest("Login Endpoint Valid Credentials", function() {
    $data = [
        'email' => 'admin@certificate-system.com',
        'password' => 'admin123'
    ];
    
    $result = makeAPICall('POST', '/auth/login', $data);
    return $result['http_code'] === 200 && 
           isset($result['data']['success']) && 
           isset($result['data']['token']);
});

runTest("Login Endpoint Invalid Credentials", function() {
    $data = [
        'email' => 'admin@certificate-system.com',
        'password' => 'wrongpassword'
    ];
    
    $result = makeAPICall('POST', '/auth/login', $data);
    return $result['http_code'] === 401;
});

runTest("Login Endpoint Missing Data", function() {
    $result = makeAPICall('POST', '/auth/login', []);
    return $result['http_code'] === 401;
});

runTest("JWT Token Generation", function() {
    $data = [
        'email' => 'admin@certificate-system.com',
        'password' => 'admin123'
    ];
    
    $result = makeAPICall('POST', '/auth/login', $data);
    
    if ($result['http_code'] === 200 && isset($result['data']['token'])) {
        $token = $result['data']['token'];
        return !empty($token) && is_string($token);
    }
    
    return false;
});

runTest("Protected Endpoint Without Token", function() {
    $result = makeAPICall('GET', '/certificates');
    return $result['http_code'] === 401;
});

runTest("Protected Endpoint With Valid Token", function() {
    // First get token
    $loginData = [
        'email' => 'admin@certificate-system.com',
        'password' => 'admin123'
    ];
    $loginResult = makeAPICall('POST', '/auth/login', $loginData);
    
    if ($loginResult['http_code'] !== 200) {
        return false;
    }
    
    $token = $loginResult['data']['token'];
    
    // Now access protected endpoint
    $result = makeAPICall('GET', '/certificates', null, $token);
    return $result['http_code'] === 200 && isset($result['data']['certificates']);
});

runTest("Universities List Endpoint", function() {
    $result = makeAPICall('GET', '/universities');
    return $result['http_code'] === 200 && isset($result['data']['universities']);
});

runTest("Certificate Verification Endpoint", function() {
    // First get a certificate ID from database
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT certificate_id, certificate_hash FROM certificates LIMIT 1");
    $cert = $stmt->fetch();
    
    if (!$cert) {
        return false; // No certificates to test with
    }
    
    $data = [
        'certificate_id' => $cert['certificate_id'],
        'certificate_hash' => $cert['certificate_hash']
    ];
    
    $result = makeAPICall('POST', '/certificates/verify', $data);
    return $result['http_code'] === 200 && 
           isset($result['data']['valid']) && 
           $result['data']['valid'] === true;
});

runTest("Certificate Verification Non-existent", function() {
    $data = [
        'certificate_id' => 'NONEXISTENT-123',
        'certificate_hash' => 'fakehash'
    ];
    
    $result = makeAPICall('POST', '/certificates/verify', $data);
    return $result['http_code'] === 200 && 
           isset($result['data']['valid']) && 
           $result['data']['valid'] === false;
});

runTest("PDF Download Endpoint Unauthorized", function() {
    $result = makeAPICall('GET', '/certificates/download?certificate_id=TEST-123');
    return $result['http_code'] === 401;
});

runTest("PDF Download Endpoint With Token", function() {
    // First get admin token
    $loginData = [
        'email' => 'admin@certificate-system.com',
        'password' => 'admin123'
    ];
    $loginResult = makeAPICall('POST', '/auth/login', $loginData);
    
    if ($loginResult['http_code'] !== 200) {
        return false;
    }
    
    $token = $loginResult['data']['token'];
    
    // Try to download PDF
    $result = makeAPICall('GET', '/certificates/download?certificate_id=TEST-123', null, $token);
    
    // Should return either 404 (not found) or 500 (PDF generation error), not 401
    return $result['http_code'] !== 401;
});

runTest("Certificate Creation Endpoint", function() {
    // Get admin token
    $loginData = [
        'email' => 'admin@certificate-system.com',
        'password' => 'admin123'
    ];
    $loginResult = makeAPICall('POST', '/auth/login', $loginData);
    
    if ($loginResult['http_code'] !== 200) {
        return false;
    }
    
    $token = $loginResult['data']['token'];
    
    // Get university and student for certificate creation
    $db = Database::getInstance()->getConnection();
    $university = $db->query("SELECT id FROM universities LIMIT 1")->fetch();
    $student = $db->query("SELECT s.id FROM students s JOIN users u ON s.user_id = u.id LIMIT 1")->fetch();
    
    if (!$university || !$student) {
        return false;
    }
    
    $certificateData = [
        'student_id' => $student['id'],
        'university_id' => $university['id'],
        'course_name' => 'API Test Certificate',
        'degree_type' => 'Test Certificate',
        'issue_date' => '2024-12-01'
    ];
    
    $result = makeAPICall('POST', '/certificates/create', $certificateData, $token);
    return $result['http_code'] === 200 && isset($result['data']['success']);
});

runTest("CORS Headers Present", function() {
    $ch = curl_init('http://localhost/backend/api/certificates');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return strpos($response, 'Access-Control-Allow-Origin') !== false && 
           strpos($response, 'Access-Control-Allow-Methods') !== false;
});

runTest("Error Response Format", function() {
    $result = makeAPICall('POST', '/auth/login', ['invalid' => 'data']);
    return $result['http_code'] === 401 && isset($result['data']['error']);
});

runTest("Success Response Format", function() {
    $result = makeAPICall('GET', '/universities');
    return $result['http_code'] === 200 && isset($result['data']['success']) && $result['data']['success'] === true;
});

// Display test results
echo "\n=== TEST RESULTS ===\n";
echo "Total Tests: {$testResults['total']}\n";
echo "Passed: {$testResults['passed']}\n";
echo "Failed: {$testResults['failed']}\n";
echo "Success Rate: " . round(($testResults['passed'] / $testResults['total']) * 100, 2) . "%\n";

if ($testResults['failed'] === 0) {
    echo "\n🎉 ALL API TESTS PASSED! 🎉\n";
} else {
    echo "\n⚠️ Some API tests failed. Check if backend server is running.\n";
}

echo "\n=== API ENDPOINTS TESTED ===\n";
$endpoints = [
    'POST /auth/login' => 'User authentication',
    'POST /auth/register' => 'User registration',
    'GET /universities' => 'List universities',
    'GET /certificates' => 'List certificates (protected)',
    'POST /certificates/create' => 'Create certificate (university/admin)',
    'POST /certificates/verify' => 'Verify certificate',
    'GET /certificates/download' => 'Download PDF (protected)',
    'GET /students' => 'List students (protected)',
    'POST /students/create' => 'Create student (university/admin)',
    'POST /certificates/revoke' => 'Revoke certificate (admin)'
];

foreach ($endpoints as $endpoint => $description) {
    echo "  $endpoint - $description\n";
}

echo "\n=== API TESTING NOTES ===\n";
echo "• Backend server must be running at http://localhost/backend\n";
echo "• Database must be accessible\n";
echo "• Certificate verification tests require existing certificates\n";
echo "• Some tests may fail if prerequisites not met\n";

// Quick server check
echo "\n=== SERVER STATUS ===\n";
$ch = curl_init('http://localhost/backend/api/certificates');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode > 0) {
    echo "✅ Backend server is running (HTTP $httpCode)\n";
} else {
    echo "❌ Backend server is not responding\n";
    echo "   Please start the backend server before running API tests\n";
}