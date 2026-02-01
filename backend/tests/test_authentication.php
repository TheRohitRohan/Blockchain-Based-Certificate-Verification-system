<?php
/**
 * Test 4: Authentication System Tests
 * 
 * Purpose: Test user authentication, role-based access, and JWT tokens
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

echo "=== AUTHENTICATION SYSTEM TEST ===\n\n";

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

runTest("Auth Class Instantiation", function() {
    try {
        $auth = new Auth();
        return true;
    } catch (Exception $e) {
        return false;
    }
});

runTest("Default Admin Login", function() {
    $auth = new Auth();
    $user = $auth->login('admin', 'admin123');
    return $user !== false && 
           isset($user['id']) && 
           $user['role'] === 'admin' &&
           $user['email'] === 'admin@certificate-system.com';
});

runTest("Invalid Admin Password", function() {
    $auth = new Auth();
    $user = $auth->login('admin', 'wrongpassword');
    return $user === false;
});

runTest("Invalid Admin Username", function() {
    $auth = new Auth();
    $user = $auth->login('nonexistent', 'admin123');
    return $user === false;
});

runTest("JWT Token Generation", function() {
    $auth = new Auth();
    $user = $auth->login('admin', 'admin123');
    if (!$user) return false;
    
    $token = $auth->generateToken($user);
    return !empty($token) && is_string($token);
});

runTest("JWT Token Verification", function() {
    $auth = new Auth();
    $user = $auth->login('admin', 'admin123');
    if (!$user) return false;
    
    $token = $auth->generateToken($user);
    $payload = $auth->verifyToken($token);
    
    return $payload !== false && 
           isset($payload['user_id']) && 
           $payload['role'] === 'admin';
});

runTest("Invalid Token Verification", function() {
    $auth = new Auth();
    $payload = $auth->verifyToken('invalid.token.here');
    return $payload === false;
});

runTest("Expired Token Simulation", function() {
    // This test creates a token with very short expiration
    $config = require __DIR__ . '/../config.php';
    $originalExpiration = $config['jwt']['expiration'];
    
    // Temporarily set short expiration for testing
    $testConfig = $config;
    $testConfig['jwt']['expiration'] = 1; // 1 second
    
    // This would require modifying the Auth class to accept config
    // For now, just test that verification works with valid tokens
    $auth = new Auth();
    $user = $auth->login('admin', 'admin123');
    if (!$user) return false;
    
    $token = $auth->generateToken($user);
    sleep(2); // Wait for expiration (simulated)
    
    $payload = $auth->verifyToken($token);
    return $payload === false; // Should be expired
});

runTest("Password Hashing Security", function() {
    $auth = new Auth();
    $password = 'testpassword123';
    $hash = $auth->hashPassword($password);
    
    return !empty($hash) && 
           $hash !== $password && 
           strlen($hash) >= 60 && // bcrypt hash length
           $auth->verifyPassword($password, $hash);
});

runTest("User Registration", function() {
    $db = Database::getInstance()->getConnection();
    
    // Clean up any existing test user
    $db->exec("DELETE FROM users WHERE email = 'testuser@test.com'");
    
    $auth = new Auth();
    $userData = [
        'username' => 'testuser123',
        'email' => 'testuser@test.com',
        'password' => 'testpassword123',
        'role' => 'student',
        'full_name' => 'Test User'
    ];
    
    $result = $auth->register($userData);
    
    if ($result) {
        // Try to login with the new user
        $loginResult = $auth->login('testuser@test.com', 'testpassword123');
        return $loginResult !== false && $loginResult['email'] === 'testuser@test.com';
    }
    
    return false;
});

runTest("Username Uniqueness", function() {
    $auth = new Auth();
    
    // Try to register with admin username
    $userData = [
        'username' => 'admin',
        'email' => 'different@test.com',
        'password' => 'newpassword123',
        'role' => 'student'
    ];
    
    $result = $auth->register($userData);
    return !$result; // Should fail due to duplicate username
});

runTest("Email Uniqueness", function() {
    $auth = new Auth();
    
    // Try to register with admin email
    $userData = [
        'username' => 'differentuser',
        'email' => 'admin@certificate-system.com',
        'password' => 'newpassword123',
        'role' => 'student'
    ];
    
    $result = $auth->register($userData);
    return !$result; // Should fail due to duplicate email
});

runTest("Role Validation", function() {
    $auth = new Auth();
    
    $validRoles = ['admin', 'university', 'student'];
    
    // Test with invalid role
    $userData = [
        'username' => 'invalidrole',
        'email' => 'invalid@test.com',
        'password' => 'password123',
        'role' => 'invalid_role'
    ];
    
    $result = $auth->register($userData);
    return !$result; // Should fail due to invalid role
});

runTest("Configuration Valid", function() {
    $config = require __DIR__ . '/../config.php';
    
    $requiredKeys = ['secret', 'algorithm', 'expiration'];
    foreach ($requiredKeys as $key) {
        if (!isset($config['jwt'][$key])) {
            return false;
        }
    }
    
    return !empty($config['jwt']['secret']) && 
           !empty($config['jwt']['algorithm']) &&
           is_int($config['jwt']['expiration']);
});

// Display test results
echo "\n=== TEST RESULTS ===\n";
echo "Total Tests: {$testResults['total']}\n";
echo "Passed: {$testResults['passed']}\n";
echo "Failed: {$testResults['failed']}\n";
echo "Success Rate: " . round(($testResults['passed'] / $testResults['total']) * 100, 2) . "%\n";

if ($testResults['failed'] === 0) {
    echo "\n🎉 ALL AUTHENTICATION TESTS PASSED! 🎉\n";
} else {
    echo "\n⚠️ Some authentication tests failed. Check configuration or database.\n";
}

echo "\n=== AUTHENTICATION STATISTICS ===\n";
$db = Database::getInstance()->getConnection();

// Count users by role
$stmt = $db->query("
    SELECT role, COUNT(*) as count 
    FROM users 
    GROUP BY role
");
echo "Users by Role:\n";
while ($row = $stmt->fetch()) {
    echo "  {$row['role']}: {$row['count']}\n";
}

// Total users
$stmt = $db->query("SELECT COUNT(*) as count FROM users");
$count = $stmt->fetch()['count'];
echo "Total Users: $count\n";

// JWT Configuration
$config = require __DIR__ . '/../config.php';
echo "\nJWT Configuration:\n";
echo "Algorithm: {$config['jwt']['algorithm']}\n";
echo "Expiration: {$config['jwt']['expiration']} seconds (" . round($config['jwt']['expiration']/3600, 1) . " hours)\n";
echo "Secret: " . (strlen($config['jwt']['secret']) >= 32 ? 'SECURE (>=32 chars)' : 'INSECURE (<32 chars)') . "\n";

// Test credentials
echo "\nTest Login Credentials:\n";
echo "Admin: admin / admin123\n";
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT username, email, role FROM users WHERE role != 'admin' LIMIT 3");
echo "Recent Users:\n";
while ($row = $stmt->fetch()) {
    echo "  {$row['username']} ({$row['email']}) - {$row['role']}\n";
}