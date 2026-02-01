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

echo "=== Simple Blockchain Connection Test ===\n\n";

try {
    $blockchain = new Blockchain();
    echo "✅ Blockchain class instantiated\n";
    
    $block = $blockchain->getCurrentBlock();
    echo "✅ Current block: $block\n";
    
    if ($block > 0) {
        echo "✅ Connection successful!\n";
    } else {
        echo "⚠️ Block is 0 - may need to mine a block in Ganache\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Direct RPC Test ===\n";

$rpcUrl = 'http://127.0.0.1:7545';
$data = [
    'jsonrpc' => '2.0',
    'method' => 'eth_blockNumber',
    'params' => [],
    'id' => 1
];

$ch = curl_init($rpcUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if (isset($result['result'])) {
    $blockHex = $result['result'];
    $blockDec = hexdec($blockHex);
    echo "✅ Direct RPC - Block: $blockDec (Hex: $blockHex)\n";
} else {
    echo "❌ Direct RPC failed\n";
}