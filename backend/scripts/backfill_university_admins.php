<?php
/**
 * Backfill users (role = university) for every active university that has none yet.
 *
 * Uses the same DB as backend/.env. Safe to re-run: skips universities that already
 * have at least one user with role university and matching university_id.
 *
 * Usage (from backend/):
 *   php scripts/backfill_university_admins.php
 *
 * Optional .env:
 *   UNIVERSITY_ADMIN_DEFAULT_PASSWORD=YourSecurePass1
 *
 * Login emails are deterministic: admin.uni.{university_id}@portal.local
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$defaultPassword = getenv('UNIVERSITY_ADMIN_DEFAULT_PASSWORD') ?: 'UnivAdmin@123';

echo "=== Backfill university portal users (users.role = university) ===\n\n";

$db = Database::getInstance()->getConnection();

$uniStmt = $db->query('
    SELECT u.id, u.name, u.code
    FROM universities u
    WHERE u.is_active = TRUE
      AND NOT EXISTS (
          SELECT 1 FROM users usr
          WHERE usr.university_id = u.id AND usr.role = \'university\'
      )
    ORDER BY u.id
');
$missing = $uniStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($missing)) {
    echo "Nothing to do: every active university already has at least one university-role user.\n";
    exit(0);
}

$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
$insert = $db->prepare('
    INSERT INTO users (username, email, password_hash, role, full_name, university_id)
    VALUES (?, ?, ?, \'university\', ?, ?)
');

echo 'Creating ' . count($missing) . " portal account(s). Default password for all: {$defaultPassword}\n\n";

foreach ($missing as $row) {
    $id = (int) $row['id'];
    $name = trim((string) $row['name']);
    $adminName = $name !== '' ? "{$name} Portal Admin" : "University #{$id} Portal Admin";
    $email = 'admin.uni.' . $id . '@portal.local';
    $username = 'portal_uni_' . $id;

    try {
        $insert->execute([$username, $email, $hash, $adminName, $id]);
        echo "- University ID {$id} ({$name}) → {$email}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "FAILED university_id={$id}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "\nDone. Sign in at /login with any email above.\n";
echo "Change passwords after first login.\n";
