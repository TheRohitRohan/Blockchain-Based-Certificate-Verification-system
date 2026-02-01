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

echo "=== Mock Deployment Test ===\n\n";

try {
    $blockchain = new Blockchain();
    
    // Test certificate issuance which should trigger a block mine
    $testData = [
        'certificate_id' => 'TEST-' . uniqid(),
        'student_name' => 'Test Student',
        'university_name' => 'Test University',
        'course_name' => 'Blockchain Testing',
        'issue_date' => '2024-12-01'
    ];
    
    echo "Testing certificate issuance...\n";
    $result = $blockchain->issueCertificate($testData);
    
    if ($result['success']) {
        echo "✅ Certificate issued successfully\n";
        echo "📝 TX Hash: " . substr($result['tx_hash'], 0, 20) . "...\n";
        echo "🔐 Certificate Hash: " . substr($result['certificate_hash'], 0, 20) . "...\n";
        
        // Check block number after transaction
        $block = $blockchain->getCurrentBlock();
        echo "📦 Current block after transaction: $block\n";
        
        if ($block > 0) {
            echo "✅ Blockchain is working!\n";
        } else {
            echo "⚠️ Still at block 0 - may need manual mining in Ganache\n";
        }
        
    } else {
        echo "❌ Certificate issuance failed: " . $result['error'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Manual Mining Instructions ===\n";
echo "If block is still 0:\n";
echo "1. Open Ganache UI\n";
echo "2. Click the 'mine' button or create a transaction\n";
echo "3. Or use Ganache CLI with --miner.mine=true\n";