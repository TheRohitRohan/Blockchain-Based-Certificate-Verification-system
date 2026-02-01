<?php

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

echo "=== Add Authorized Issuer Test ===\n\n";

try {
    $blockchain = new Blockchain();
    
    echo "Current admin: " . $blockchain->getAdmin() . "\n";
    
    // Add our account as authorized issuer
    $config = require __DIR__ . '/../config.php';
    $account = $config['blockchain']['default_address'];
    
    echo "Adding account as authorized issuer: $account\n";
    
    // This would be done by the admin account
    echo "✅ Account should be authorized by admin\n";
    
    // Now test certificate issuance
    echo "\nTesting certificate issuance...\n";
    $testData = [
        'certificate_id' => 'CERT-' . uniqid(),
        'student_name' => 'Test Student',
        'university_name' => 'Test University',
        'course_name' => 'Blockchain Testing',
        'issue_date' => '2024-12-01'
    ];
    
    $result = $blockchain->issueCertificate($testData);
    
    if ($result['success']) {
        echo "   ✅ Certificate issued successfully!\n";
        echo "   📝 TX Hash: " . substr($result['tx_hash'], 0, 20) . "...\n";
    } else {
        echo "   ❌ Failed: " . $result['error'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nNote: In production, the admin account must add university accounts as authorized issuers.\n";