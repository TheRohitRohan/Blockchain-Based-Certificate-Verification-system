<?php
require 'vendor/autoload.php';
try {
    $qrCode = new \Endroid\QrCode\QrCode('test');
    echo "SUCCESS: new QrCode()\n";
} catch (\Throwable $e) {
    echo "ERROR with new QrCode(): " . $e->getMessage() . "\n";
}

try {
    $qrCode2 = \Endroid\QrCode\QrCode::create('test');
    echo "SUCCESS: QrCode::create()\n";
} catch (\Throwable $e) {
    echo "ERROR with QrCode::create(): " . $e->getMessage() . "\n";
}
