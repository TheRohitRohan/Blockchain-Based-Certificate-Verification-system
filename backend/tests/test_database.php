<?php
/**
 * Test 1: Database Connection and Schema Verification
 * 
 * Purpose: Verify database connection, table structure, and basic data
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

use App\Database;

echo "=== DATABASE CONNECTION AND SCHEMA TEST ===\n\n";

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

runTest("Database Connection", function() {
    $db = Database::getInstance()->getConnection();
    return $db instanceof PDO;
});

runTest("Database Exists", function() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'certificate_db'");
    return $stmt->rowCount() > 0;
});

runTest("Universities Table Exists", function() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW TABLES LIKE 'universities'");
    return $stmt->rowCount() > 0;
});

runTest("Users Table Exists", function() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    return $stmt->rowCount() > 0;
});

runTest("Students Table Exists", function() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW TABLES LIKE 'students'");
    return $stmt->rowCount() > 0;
});

runTest("Certificates Table Exists", function() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW TABLES LIKE 'certificates'");
    return $stmt->rowCount() > 0;
});

runTest("Verification Logs Table Exists", function() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW TABLES LIKE 'verification_logs'");
    return $stmt->rowCount() > 0;
});

runTest("Admin User Exists", function() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    return $stmt->rowCount() > 0;
});

runTest("Foreign Key Constraints", function() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'certificate_db' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    return $stmt->rowCount() >= 3; // At least 3 foreign key relationships
});

runTest("Indexes Present", function() {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT INDEX_NAME 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = 'certificate_db' 
        AND INDEX_NAME != 'PRIMARY'
    ");
    return $stmt->rowCount() >= 8; // Expected number of indexes
});

// Display test results
echo "\n=== TEST RESULTS ===\n";
echo "Total Tests: {$testResults['total']}\n";
echo "Passed: {$testResults['passed']}\n";
echo "Failed: {$testResults['failed']}\n";
echo "Success Rate: " . round(($testResults['passed'] / $testResults['total']) * 100, 2) . "%\n";

if ($testResults['failed'] === 0) {
    echo "\n🎉 ALL DATABASE TESTS PASSED! 🎉\n";
} else {
    echo "\n⚠️ Some database tests failed. Check the issues above.\n";
}

echo "\n=== DATABASE STATISTICS ===\n";
$db = Database::getInstance()->getConnection();

$tables = ['universities', 'users', 'students', 'certificates', 'verification_logs'];
foreach ($tables as $table) {
    $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
    $count = $stmt->fetch()['count'];
    echo "$table: $count records\n";
}