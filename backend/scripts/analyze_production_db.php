<?php

$dbHost = 'ballast.proxy.rlwy.net';
$dbPort = 21041;
$dbUser = 'root';
$dbPass = 'SjaueyNNGuKYFOYydzpjoUCNjdEwQxsY';
$dbName = 'railway';

$out = [];

try {
    $pdo = new \PDO(
        "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );

    $tables = ['users', 'universities', 'university_keys', 'students', 'certificates', 'verification_logs'];

    // 1. Schemas
    $schemas = [];
    foreach ($tables as $table) {
        $stmt = $pdo->query("DESCRIBE $table");
        $cols = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $schemas[$table] = array_map(function($c) {
            return $c['Field'] . ' (' . $c['Type'] . ')';
        }, $cols);
    }
    $out['schemas'] = $schemas;

    // 2. Row counts
    $counts = [];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
        $counts[$table] = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];
    }
    $out['row_counts'] = $counts;

    // 3. Users breakdown
    $stmt = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
    $out['users_by_role'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // 4. All users (mask passwords)
    $stmt = $pdo->query("SELECT id, username, email, role, university_id, created_at FROM users ORDER BY id");
    $out['users'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // 5. Universities
    $stmt = $pdo->query("SELECT * FROM universities ORDER BY id");
    $out['universities'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // 6. University keys (mask sensitive data)
    $stmt = $pdo->query("SELECT id, university_id, certificate_path, key_fingerprint, is_active, created_at FROM university_keys ORDER BY id");
    $out['university_keys'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // 7. Students - use SELECT * to see actual columns
    $stmt = $pdo->query("SELECT * FROM students ORDER BY id");
    $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    // Mask password hashes
    foreach ($students as &$s) {
        if (isset($s['password_hash'])) $s['password_hash'] = '***MASKED***';
        if (isset($s['password'])) $s['password'] = '***MASKED***';
    }
    unset($s);
    $out['students'] = $students;

    // 8. Certificates
    $stmt = $pdo->query("SELECT * FROM certificates ORDER BY id");
    $certs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($certs as &$c) {
        // Keep tx_hash but mask very long fields
        if (isset($c['certificate_hash']) && strlen($c['certificate_hash']) > 80) {
            $c['certificate_hash'] = substr($c['certificate_hash'], 0, 20) . '...TRUNCATED';
        }
        if (isset($c['metadata_hash']) && strlen($c['metadata_hash']) > 80) {
            $c['metadata_hash'] = substr($c['metadata_hash'], 0, 20) . '...TRUNCATED';
        }
        if (isset($c['pdf_hash']) && strlen($c['pdf_hash']) > 80) {
            $c['pdf_hash'] = substr($c['pdf_hash'], 0, 20) . '...TRUNCATED';
        }
        if (isset($c['digital_signature']) && strlen($c['digital_signature']) > 80) {
            $c['digital_signature'] = substr($c['digital_signature'], 0, 20) . '...TRUNCATED';
        }
    }
    unset($c);
    $out['certificates'] = $certs;

    // 9. Verification logs (last 10)
    $stmt = $pdo->query("SELECT * FROM verification_logs ORDER BY id DESC LIMIT 10");
    $out['recent_verification_logs'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // 10. Integrity checks
    $integrity = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM students s LEFT JOIN universities u ON s.university_id = u.id WHERE u.id IS NULL");
    $integrity['orphaned_students'] = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM certificates c LEFT JOIN students s ON c.student_id = s.id WHERE s.id IS NULL");
    $integrity['orphaned_certs_no_student'] = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM certificates c LEFT JOIN universities u ON c.university_id = u.id WHERE u.id IS NULL");
    $integrity['orphaned_certs_no_university'] = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM university_keys k LEFT JOIN universities u ON k.university_id = u.id WHERE u.id IS NULL");
    $integrity['orphaned_keys'] = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM certificates WHERE tx_hash = 'pending' OR tx_hash IS NULL OR tx_hash = ''");
    $integrity['pending_tx_certs'] = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM certificates WHERE block_number = 0 OR block_number IS NULL");
    $integrity['no_block_number_certs'] = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM universities u LEFT JOIN university_keys k ON u.id = k.university_id WHERE k.id IS NULL");
    $integrity['universities_without_keys'] = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

    // Check which universities have keys
    $stmt = $pdo->query("SELECT u.id, u.name, u.code, (SELECT COUNT(*) FROM university_keys k WHERE k.university_id = u.id) as key_count FROM universities u ORDER BY u.id");
    $integrity['university_key_mapping'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $out['integrity'] = $integrity;

} catch (Exception $e) {
    $out['error'] = $e->getMessage();
    $out['trace'] = $e->getTraceAsString();
}

$json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__ . '/db_analysis.json', $json);
echo "Done. Written to scripts/db_analysis.json (" . strlen($json) . " bytes)\n";
