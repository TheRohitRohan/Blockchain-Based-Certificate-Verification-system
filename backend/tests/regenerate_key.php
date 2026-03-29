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

    echo "Deleting corrupted key for university 2...\n";
    $stmt = $db->prepare('DELETE FROM university_keys WHERE university_id = 2');
    $result = $stmt->execute();
    echo "Deleted rows: " . $stmt->rowCount() . "\n";

    // Regenerate using SignatureService
    echo "\nRegenerating university key...\n";
    $sigService = new App\SignatureService();
    $result = $sigService->generateUniversityKeyPair(2, 'University of Technology');
    
    if ($result) {
        echo "Key generated successfully!\n";
        echo "Fingerprint: {$result['fingerprint']}\n";
        echo "Key file: {$result['key_path']}\n";
    } else {
        echo "Key generation failed!\n";
    }

    // Verify
    echo "\nVerifying key in database...\n";
    $stmt = $db->prepare('SELECT certificate_password FROM university_keys WHERE university_id = 2');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "Key found! Password field length: " . strlen($row['certificate_password']) . "\n";
        echo "First 50 chars: " . substr($row['certificate_password'], 0, 50) . "\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
