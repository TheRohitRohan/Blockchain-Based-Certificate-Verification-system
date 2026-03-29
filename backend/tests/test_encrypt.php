<?php
require 'vendor/autoload.php';
$config = require 'config.php';

// Test encryption
$privKeyPem = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7VJTUt9Us8cKj
MzEfYyjiWA4R0i+PQjJQjvwRaHRzUPqVfvHRPomIYSXR6hkPvVpqmqLh
-----END PRIVATE KEY-----
PEM;

$secret = $config['signing']['key_encryption_secret'] ?? '';
echo "Config KEY_ENCRYPTION_SECRET length: " . strlen($secret) . "\n";
echo "Config setting: " . json_encode($config['signing']['key_encryption_secret'] ?? null) . "\n";

if (strlen($secret) < 32) {
    $secret = hash('sha256', $secret, true);
    echo "Secret converted with SHA256, new length: " . strlen($secret) . "\n";
} else {
    $secret = substr($secret, 0, 32);
    echo "Secret truncated to 32 bytes\n";
}

// Test encryption
$iv = openssl_random_pseudo_bytes(16);
$encrypted = openssl_encrypt($privKeyPem, 'AES-256-CBC', $secret, OPENSSL_RAW_DATA, $iv);
echo "Encrypted result length: " . strlen($encrypted) . "\n";

$result = base64_encode($iv . '::' . $encrypted);
echo "Final encoded length: " . strlen($result) . "\n";
echo "First 50 chars: " . substr($result, 0, 50) . "\n";
