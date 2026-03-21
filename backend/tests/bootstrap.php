<?php

// Test bootstrap: configure environment, autoload classes, and prepare helpers.
date_default_timezone_set('UTC');
error_reporting(E_ALL);

$projectRoot = dirname(__DIR__);

// Minimal env to satisfy config.php requirements
$env = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'true',
    'JWT_SECRET' => 'test_jwt_secret',
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'test_db',
    'DB_USER' => 'test_user',
    'DB_PASS' => 'test_pass',
    'DB_CHARSET' => 'utf8mb4',
    'STORAGE_PDF_PATH' => 'tests/storage/pdf/',
    'STORAGE_QR_PATH' => 'tests/storage/qr/',
    'STORAGE_CERTS_PATH' => 'tests/storage/certs/',
    'STORAGE_CACHE_PATH' => 'tests/storage/cache/',
    'STORAGE_BASE_URL' => 'http://localhost/backend/tests/storage/',
    'APP_URL' => 'http://localhost/backend',
    'FRONTEND_URL' => 'http://localhost/frontend',
    'API_URL' => 'http://localhost/backend',
    'CACHE_DRIVER' => 'file',
    'CACHE_TTL' => '60',
];

foreach ($env as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
}

// Ensure storage directories exist for tests
$storageDirs = [
    "{$projectRoot}/tests/storage/pdf/",
    "{$projectRoot}/tests/storage/qr/",
    "{$projectRoot}/tests/storage/certs/",
    "{$projectRoot}/tests/storage/cache/",
    "{$projectRoot}/tests/reports/coverage/",
];
foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

// Composer autoload
$autoloadPath = "{$projectRoot}/vendor/autoload.php";
if (file_exists($autoloadPath)) {
    require $autoloadPath;
} else {
    // Fallback simple autoloader for App\ classes
    spl_autoload_register(function ($class) use ($projectRoot) {
        if (strpos($class, 'App\\') === 0) {
            $path = $projectRoot . '/src/' . str_replace('App\\', '', $class) . '.php';
            if (file_exists($path)) {
                require $path;
            }
        }
    });
}

use App\Database;

/**
 * Create an in-memory SQLite PDO for integration tests.
 */
function test_create_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

/**
 * Seed minimal schema needed by modules for integration tests.
 */
function test_migrate_schema(PDO $pdo): void
{
    $schema = [
        "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            email TEXT,
            password_hash TEXT,
            role TEXT,
            full_name TEXT,
            university_id INTEGER
        )",
        "CREATE TABLE universities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            code TEXT
        )",
        "CREATE TABLE students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            student_id TEXT,
            university_id INTEGER
        )",
        "CREATE TABLE certificates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            certificate_id TEXT,
            student_id INTEGER,
            university_id INTEGER,
            course_name TEXT,
            degree_type TEXT,
            issue_date TEXT,
            certificate_hash TEXT,
            blockchain_tx_hash TEXT,
            pdf_path TEXT,
            qr_code_path TEXT,
            status TEXT,
            metadata_hash TEXT,
            pdf_hash TEXT,
            onchain_hash TEXT,
            metadata_json TEXT,
            signature_status INTEGER,
            block_number INTEGER,
            chain_id INTEGER,
            schema_version TEXT,
            is_revoked INTEGER DEFAULT 0,
            revoked_at TEXT,
            revoked_by TEXT,
            created_at TEXT,
            updated_at TEXT
        )",
        "CREATE TABLE verification_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            certificate_id TEXT,
            verifier_ip TEXT,
            verification_method TEXT,
            verification_result TEXT,
            verification_details TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE university_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            university_id INTEGER,
            certificate_path TEXT,
            certificate_password TEXT,
            public_key_pem TEXT,
            key_fingerprint TEXT,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT
        )"
    ];

    foreach ($schema as $sql) {
        $pdo->exec($sql);
    }
}

/**
 * Override Database singleton with a provided PDO connection.
 */
function test_set_database_connection(PDO $pdo): void
{
    $stub = new class($pdo) {
        private $connection;
        public function __construct(PDO $pdo)
        {
            $this->connection = $pdo;
        }
        public function getConnection(): PDO
        {
            return $this->connection;
        }
    };

    $ref = new ReflectionClass(Database::class);
    $instanceProp = $ref->getProperty('instance');
    $instanceProp->setAccessible(true);
    $instanceProp->setValue($stub);
}

/**
 * Reset file cache directory for tests.
 */
function test_reset_cache(): void
{
    $cachePath = dirname(__DIR__) . '/tests/storage/cache/';
    foreach (glob($cachePath . '*') as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

// Load base TestCase class for Tests namespace after helpers are declared
require_once __DIR__ . '/TestCase.php';
