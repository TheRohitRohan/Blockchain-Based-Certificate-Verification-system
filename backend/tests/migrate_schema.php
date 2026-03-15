<?php
/**
 * Database Schema Migration - Add signing key columns to university_keys
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

$db = \App\Database::getInstance()->getConnection();

echo "Database Schema Migration\n";
echo str_repeat("=", 50) . "\n\n";

$migrations = [
    [
        'name' => 'Add public_key_pem column to university_keys',
        'sql' => 'ALTER TABLE university_keys ADD COLUMN public_key_pem TEXT NULL',
    ],
    [
        'name' => 'Add key_fingerprint column to university_keys',
        'sql' => 'ALTER TABLE university_keys ADD COLUMN key_fingerprint VARCHAR(64) NULL UNIQUE',
    ],
];

foreach ($migrations as $migration) {
    echo "[Migrating] {$migration['name']}\n";
    try {
        $db->exec($migration['sql']);
        echo "  ✓ Successfully executed\n";
    } catch (\PDOException $e) {
        // Check if column already exists
        if (strpos($e->getMessage(), 'Duplicate column') !== false || 
            strpos($e->getMessage(), 'already exists') !== false) {
            echo "  ⚠ Column already exists (OK)\n";
        } else {
            echo "  ✗ Error: {$e->getMessage()}\n";
        }
    }
    echo "\n";
}

echo str_repeat("=", 50) . "\n";
echo "Migration complete!\n";
