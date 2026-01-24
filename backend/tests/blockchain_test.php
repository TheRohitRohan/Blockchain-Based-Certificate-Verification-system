<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Blockchain;

echo "<pre>";

try {
    echo "=== Blockchain Integration Test ===\n\n";

    $blockchain = new Blockchain();
    echo "✅ Blockchain service initialized\n";

    $block = $blockchain->getCurrentBlock();
    echo "✅ Connected to Ganache | Current Block: $block\n";

    $admin = $blockchain->getAdmin();
    echo "✅ Contract admin: $admin\n";

    $result = $blockchain->verifyCertificate('CERT001', 'hash123');
    echo "✅ Certificate Verification: " . ($result ? 'VALID' : 'INVALID') . "\n";

    echo "\n🎉 Blockchain setup is WORKING\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage();
}

echo "</pre>";
