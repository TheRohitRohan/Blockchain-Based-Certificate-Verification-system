<?php
require 'vendor/autoload.php';
$config = require 'config.php';

try {
    $db = new PDO(
        'mysql:host=' . $config['database']['host'] . 
        ';port=' . $config['database']['port'] . 
        ';dbname=' . $config['database']['dbname'],
        $config['database']['username'],
        $config['database']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "=== KEY DECRYPTION DEBUG ===\n";

    $stmt = $db->prepare('SELECT id, university_id, certificate_password, key_fingerprint FROM university_keys WHERE university_id = 2 AND is_active = 1 LIMIT 1');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "ERROR: No active key found for university 2\n";
        exit(1);
    }

    echo "Key ID: {$row['id']}\n";
    echo "University ID: {$row['university_id']}\n";
    echo "Fingerprint: {$row['key_fingerprint']}\n";
    echo "Certificate Password (first 50 chars): " . substr($row['certificate_password'], 0, 50) . "...\n";

    // Try to decode
    $encryptedKeyB64 = $row['certificate_password'];
    $data = base64_decode($encryptedKeyB64);
    echo "\nBase64 decode: " . ($data !== false ? "SUCCESS, length=" . strlen($data) : "FAILED") . "\n";

    if ($data !== false) {
        $parts = explode('::', $data, 2);
        echo "Parts count: " . count($parts) . "\n";
        if (count($parts) === 2) {
            list($iv, $encrypted) = $parts;
            echo "IV length: " . strlen($iv) . " (expected 16)\n";
            echo "Encrypted length: " . strlen($encrypted) . "\n";

            // Try decryption
            $secret = $config['signing']['key_encryption_secret'] ?? '';
            if (strlen($secret) < 32) {
                $secret = hash('sha256', $secret, true);
            } else {
                $secret = substr($secret, 0, 32);
            }
            echo "Secret length: " . strlen($secret) . "\n";

            $decrypted = @openssl_decrypt($encrypted, 'AES-256-CBC', $secret, OPENSSL_RAW_DATA, $iv);
            if ($decrypted !== false) {
                echo "Decryption: SUCCESS, length=" . strlen($decrypted) . "\n";
                if (strpos($decrypted, 'BEGIN PRIVATE KEY') !== false) {
                    echo "Result looks like a valid PEM private key!\n";
                }
            } else {
                echo "Decryption: FAILED - " . openssl_error_string() . "\n";
            }
        }
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
