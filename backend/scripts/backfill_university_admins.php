<?php
/**
 * Backfill university_admins for every university that has no portal admin yet.
 *
 * Uses the same DB as backend/.env (production or local). Safe to re-run: skips
 * universities that already have at least one row in university_admins.
 *
 * Usage (from backend/):
 *   php scripts/backfill_university_admins.php
 *
 * Optional .env:
 *   UNIVERSITY_ADMIN_DEFAULT_PASSWORD=YourSecurePass1
 *
 * Login emails are deterministic: admin.uni.{university_id}@portal.local
 * (unique, avoids clashing with real inboxes).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$defaultPassword = getenv('UNIVERSITY_ADMIN_DEFAULT_PASSWORD') ?: 'UnivAdmin@123';

echo "=== Backfill university_admins ===\n\n";

$db = Database::getInstance()->getConnection();

$uniStmt = $db->query('
    SELECT u.id, u.name, u.code
    FROM universities u
    WHERE u.is_active = TRUE
      AND NOT EXISTS (
          SELECT 1 FROM university_admins ua WHERE ua.university_id = u.id
      )
    ORDER BY u.id
');
$missing = $uniStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($missing)) {
    echo "Nothing to do: every active university already has at least one admin in university_admins.\n";
    exit(0);
}

$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
$insert = $db->prepare('
    INSERT INTO university_admins (university_id, name, email, password_hash)
    VALUES (?, ?, ?, ?)
');

echo "Creating " . count($missing) . " portal account(s). Default password for all: {$defaultPassword}\n\n";

foreach ($missing as $row) {
    $id = (int) $row['id'];
    $name = trim((string) $row['name']);
    $code = preg_replace('/[^A-Za-z0-9]/', '', (string) ($row['code'] ?? ''));
    $adminName = $name !== '' ? "{$name} Portal Admin" : "University #{$id} Portal Admin";
    $email = 'admin.uni.' . $id . '@portal.local';

    try {
        $insert->execute([$id, $adminName, $email, $hash]);
        echo "- University ID {$id} ({$name}) → {$email}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "FAILED university_id={$id}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "\nDone. Sign in at /university/login with any email above.\n";
echo "Change passwords after first login.\n";
