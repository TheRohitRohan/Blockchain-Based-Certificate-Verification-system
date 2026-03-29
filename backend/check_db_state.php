<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
$db = App\Database::getInstance()->getConnection();

echo "=== DATABASE STATE ===\n";

$rows = $db->query("SELECT blockchain_status, COUNT(*) as cnt FROM certificates GROUP BY blockchain_status")->fetchAll(PDO::FETCH_ASSOC);
echo "Certificates by blockchain_status:\n";
foreach ($rows as $r) echo "  {$r['blockchain_status']}: {$r['cnt']}\n";

echo 'Universities: ' . $db->query('SELECT COUNT(*) FROM universities')->fetchColumn() . "\n";
echo 'Students: '     . $db->query('SELECT COUNT(*) FROM students')->fetchColumn()     . "\n";
echo 'Total certs: '  . $db->query('SELECT COUNT(*) FROM certificates')->fetchColumn() . "\n";
echo 'Admin user: '   . $db->query("SELECT email FROM users WHERE role='admin' LIMIT 1")->fetchColumn() . "\n";

echo "\nLast 5 certificates:\n";
$certs = $db->query("SELECT certificate_id, blockchain_status, blockchain_tx_hash, blockchain_attempts, blockchain_error FROM certificates ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($certs as $c) {
    $tx  = substr($c['blockchain_tx_hash'] ?? 'NULL', 0, 20);
    $err = substr($c['blockchain_error'] ?? 'none', 0, 80);
    echo "  [{$c['blockchain_status']}] {$c['certificate_id']} | tx: {$tx}... | attempts: {$c['blockchain_attempts']} | err: {$err}\n";
}
