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

    echo "=== UNIVERSITIES ===\n";
    $stmt = $db->query('SELECT id, name, code FROM universities ORDER BY id DESC LIMIT 3');
    foreach ($stmt as $row) {
        echo "  ID: {$row['id']}, Name: {$row['name']}, Code: {$row['code']}\n";
    }

    echo "\n=== UNIVERSITY KEYS ===\n";
    $stmt = $db->query('SELECT id, university_id, is_active, key_fingerprint FROM university_keys ORDER BY id DESC LIMIT 3');
    foreach ($stmt as $row) {
        echo "  ID: {$row['id']}, Uni: {$row['university_id']}, Active: {$row['is_active']}, Fingerprint: {$row['key_fingerprint']}\n";
    }

    echo "\n=== CHECKING KEY FILES ===\n";
    $certsDir = __DIR__ . '/certs/';
    if (is_dir($certsDir)) {
        $files = scandir($certsDir);
        foreach ($files as $f) {
            if ($f !== '.' && $f !== '..') {
                echo "  Found: $f\n";
            }
        }
    } else {
        echo "  Certs directory doesn't exist: $certsDir\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
