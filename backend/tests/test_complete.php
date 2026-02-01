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

echo "=== Complete Blockchain Test ===\n\n";

try {
    $blockchain = new Blockchain();
    
    echo "1. Testing connection...\n";
    $block = $blockchain->getCurrentBlock();
    echo "   ✅ Current block: $block\n";
    
    echo "\n2. Testing contract admin...\n";
    $admin = $blockchain->getAdmin();
    echo "   ✅ Contract admin: $admin\n";
    
    echo "\n3. Testing certificate issuance...\n";
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
        echo "   🔐 Certificate Hash: " . substr($result['certificate_hash'], 0, 20) . "...\n";
        
        echo "\n4. Testing certificate retrieval...\n";
        $cert = $blockchain->getCertificate($testData['certificate_id']);
        if ($cert) {
            echo "   ✅ Certificate retrieved from blockchain\n";
            echo "   👤 Student: " . $cert['student_name'] . "\n";
            echo "   🎓 Course: " . $cert['course_name'] . "\n";
        } else {
            echo "   ⚠️ Certificate not found on blockchain\n";
        }
        
        echo "\n5. Testing certificate verification...\n";
        $isValid = $blockchain->verifyCertificate($testData['certificate_id'], $result['certificate_hash']);
        echo "   ✅ Verification result: " . ($isValid ? 'VALID' : 'INVALID') . "\n";
        
    } else {
        echo "   ❌ Certificate issuance failed: " . $result['error'] . "\n";
        
        if (strpos($result['error'], 'Not authorized') !== false) {
            echo "   💡 Tip: The account needs to be added as an authorized issuer\n";
        }
    }
    
    echo "\n🎉 Blockchain setup is working!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}