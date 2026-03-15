<?php
require 'vendor/autoload.php';
try {
    $qrCode = new \Endroid\QrCode\QrCode('test');
    $qrCode->setSize(200);
    echo "SUCCESS: setSize()\n";
} catch (\Throwable $e) {
    echo "ERROR with setSize(): " . $e->getMessage() . "\n";
}
