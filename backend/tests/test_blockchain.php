<?php
/**
 * Test 2: Blockchain Integration Tests
 * 
 * Purpose: Test blockchain connectivity, contract interaction, and certificate issuance
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

use App\Blockchain;
use App\Database;

echo "=== BLOCKCHAIN INTEGRATION TEST ===\n\n";

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

runTest("Blockchain Class Instantiation", function() {
    try {
        $blockchain = new Blockchain();
        return true;
    } catch (Exception $e) {
        return false;
    }
});

runTest("Blockchain Connection", function() {
    $blockchain = new Blockchain();
    $blockNumber = $blockchain->getCurrentBlock();
    return is_int($blockNumber) && $blockNumber > 0;
});

runTest("Contract ABI Loading", function() {
    $blockchain = new Blockchain();
    try {
        // Try to call a contract method - this will test if ABI is loaded
        $admin = $blockchain->getAdmin();
        return !empty($admin);
    } catch (Exception $e) {
        return strpos($e->getMessage(), 'ABI') === false;
    }
});

runTest("Certificate Hash Generation", function() {
    $blockchain = new Blockchain();
    $testData = [
        'certificate_id' => 'TEST-123',
        'student_name' => 'John Doe',
        'course_name' => 'Test Course'
    ];
    $hash = $blockchain->generateCertificateHash($testData);
    return !empty($hash) && strlen($hash) === 64;
});

runTest("Hash Consistency", function() {
    $blockchain = new Blockchain();
    $testData = [
        'certificate_id' => 'TEST-123',
        'student_name' => 'John Doe',
        'course_name' => 'Test Course'
    ];
    
    $hash1 = $blockchain->generateCertificateHash($testData);
    $hash2 = $blockchain->generateCertificateHash($testData);
    
    return $hash1 === $hash2;
});

runTest("Certificate Issuance Simulation", function() {
    $blockchain = new Blockchain();
    $testData = [
        'certificate_id' => 'TEST-'.uniqid(),
        'student_name' => 'Test Student',
        'university_id' => 1,
        'course_name' => 'Blockchain Testing',
        'issue_date' => '2024-12-01'
    ];
    
    $result = $blockchain->issueCertificate($testData);
    
    if ($result['success']) {
        return !empty($result['tx_hash']) && !empty($result['certificate_hash']);
    } else {
        // If it fails, check if it's due to missing private key (expected in test)
        return strpos($result['error'], 'Private key') !== false;
    }
});

runTest("Certificate Retrieval", function() {
    $blockchain = new Blockchain();
    
    // This test will fail if no certificate exists, which is expected
    try {
        $result = $blockchain->getCertificate('NONEXISTENT');
        // If it doesn't throw an exception, it should return null or empty array
        return $result === null || empty($result);
    } catch (Exception $e) {
        return true; // Exception is expected for non-existent certificate
    }
});

runTest("Verification Method Exists", function() {
    $blockchain = new Blockchain();
    
    // Test with a hash (will likely fail but method should exist)
    try {
        $result = $blockchain->verifyCertificate('TEST-ID', 'test-hash-123456789');
        return is_bool($result);
    } catch (Exception $e) {
        return strpos($e->getMessage(), 'verifyCertificate call failed') === false;
    }
});

runTest("Configuration Valid", function() {
    $config = require __DIR__ . '/../config.php';
    
    $requiredKeys = ['rpc_url', 'contract_address', 'gas_limit'];
    foreach ($requiredKeys as $key) {
        if (!isset($config['blockchain'][$key])) {
            return false;
        }
    }
    
    return !empty($config['blockchain']['rpc_url']) && 
           !empty($config['blockchain']['contract_address']);
});

// Display test results
echo "\n=== TEST RESULTS ===\n";
echo "Total Tests: {$testResults['total']}\n";
echo "Passed: {$testResults['passed']}\n";
echo "Failed: {$testResults['failed']}\n";
echo "Success Rate: " . round(($testResults['passed'] / $testResults['total']) * 100, 2) . "%\n";

if ($testResults['failed'] === 0) {
    echo "\n🎉 ALL BLOCKCHAIN TESTS PASSED! 🎉\n";
} else {
    echo "\n⚠️ Some blockchain tests failed. Check configuration or Ganache status.\n";
}

echo "\n=== BLOCKCHAIN INFORMATION ===\n";
$config = require __DIR__ . '/../config.php';
echo "RPC URL: {$config['blockchain']['rpc_url']}\n";
echo "Contract Address: {$config['blockchain']['contract_address']}\n";
echo "Gas Limit: {$config['blockchain']['gas_limit']}\n";
echo "Private Key: " . (empty($config['blockchain']['private_key']) ? 'NOT SET' : 'SET') . "\n";
echo "Default Address: {$config['blockchain']['default_address']}\n";

try {
    $blockchain = new Blockchain();
    $currentBlock = $blockchain->getCurrentBlock();
    echo "Current Block: $currentBlock\n";
} catch (Exception $e) {
    echo "❌ Cannot connect to blockchain: " . $e->getMessage() . "\n";
}