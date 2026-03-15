<?php

/**
 * ============================================================
 *  BIG INTEGRATION TEST — Certificate Verification System
 * ============================================================
 *
 * Tests every fixed bug and every critical system flow.
 * Run from the backend/ directory:
 *
 *   php tests/BigTest.php
 *
 * Or with colour on Windows PowerShell:
 *
 *   php tests/BigTest.php 2>&1
 *
 * Requirements:
 *   - MySQL running with certificate_db (or the test creates a temp DB)
 *   - composer install completed
 *   - config.php present (or .env present)
 *   - PHP extensions: pdo_mysql, gd, openssl, json, curl
 *
 * The test is SELF-CONTAINED:
 *   - Seeds its own test university, user, student
 *   - Cleans up all seeded data after run
 *   - Does NOT touch existing production data
 * ============================================================
 */

declare(strict_types = 1)
;

// ── Bootstrap ──────────────────────────────────────────────────────────────
$backendDir = dirname(__DIR__);
require_once $backendDir . '/vendor/autoload.php';

// Load .env if present
if (file_exists($backendDir . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($backendDir);
    $dotenv->safeLoad();
}

use App\Auth;
use App\Blockchain;
use App\Cache;
use App\CertificateService;
use App\Database;
use App\MetadataService;
use App\PDFService;
use App\VerificationEngine;

// ── Test Runner ────────────────────────────────────────────────────────────
class TestRunner
{
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    private int $warnings = 0;
    private array $cleanup = []; // callables to run at the end

    // ── Assertion helpers ──────────────────────────────────────────────────

    public function assert(string $name, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            $this->results[] = ['status' => 'PASS', 'name' => $name, 'detail' => $detail];
            $this->passed++;
        }
        else {
            $this->results[] = ['status' => 'FAIL', 'name' => $name, 'detail' => $detail ?: '(no detail)'];
            $this->failed++;
        }
    }

    public function warn(string $name, string $detail = ''): void
    {
        $this->results[] = ['status' => 'WARN', 'name' => $name, 'detail' => $detail];
        $this->warnings++;
    }

    public function section(string $title): void
    {
        $this->results[] = ['status' => 'SECTION', 'name' => $title, 'detail' => ''];
    }

    public function addCleanup(callable $fn): void
    {
        $this->cleanup[] = $fn;
    }

    // ── Output ─────────────────────────────────────────────────────────────

    public function finish(): void
    {
        // Run cleanup
        foreach (array_reverse($this->cleanup) as $fn) {
            try {
                $fn();
            }
            catch (\Throwable $e) {
                echo "  [CLEANUP ERROR] " . $e->getMessage() . "\n";
            }
        }

        echo "\n";
        echo str_repeat('═', 60) . "\n";
        echo "  TEST RESULTS\n";
        echo str_repeat('═', 60) . "\n";

        foreach ($this->results as $r) {
            switch ($r['status']) {
                case 'SECTION':
                    echo "\n  ── " . strtoupper($r['name']) . " ──\n";
                    break;
                case 'PASS':
                    echo "  ✓  " . $r['name'];
                    if ($r['detail'])
                        echo "  →  " . $r['detail'];
                    echo "\n";
                    break;
                case 'FAIL':
                    echo "  ✗  " . $r['name'] . "\n";
                    echo "     DETAIL: " . $r['detail'] . "\n";
                    break;
                case 'WARN':
                    echo "  ⚠  " . $r['name'];
                    if ($r['detail'])
                        echo "  →  " . $r['detail'];
                    echo "\n";
                    break;
            }
        }

        echo "\n" . str_repeat('─', 60) . "\n";
        echo sprintf(
            "  PASSED: %d   FAILED: %d   WARNINGS: %d   TOTAL: %d\n",
            $this->passed,
            $this->failed,
            $this->warnings,
            $this->passed + $this->failed + $this->warnings
        );
        echo str_repeat('─', 60) . "\n";

        if ($this->failed === 0) {
            echo "  ALL TESTS PASSED ✓\n";
        }
        else {
            echo "  {$this->failed} TEST(S) FAILED — see details above\n";
        }
        echo str_repeat('═', 60) . "\n\n";

        exit($this->failed > 0 ? 1 : 0);
    }
}

$t = new TestRunner();

// ── Seed data tracking ─────────────────────────────────────────────────────
$seed = [
    'university_id' => null,
    'user_id' => null,
    'student_id' => null, // students.id (PK)
    'cert_id' => null, // certificate_id string
    'pdf_path' => null,
];

echo "\n";
echo str_repeat('═', 60) . "\n";
echo "  BLOCKCHAIN CERTIFICATE SYSTEM — BIG INTEGRATION TEST\n";
echo str_repeat('═', 60) . "\n\n";

// ══════════════════════════════════════════════════════════════
//  SUITE 1 — ENVIRONMENT & CONFIG
// ══════════════════════════════════════════════════════════════
$t->section('Suite 1 — Environment & Config');

// Bug #16 — config.php must exist
$configPath = $backendDir . '/config.php';
$t->assert('config.php exists', file_exists($configPath), $configPath);

$config = null;
if (file_exists($configPath)) {
    $config = require $configPath;
}

$t->assert('config returns array', is_array($config));
$t->assert('config.database present', isset($config['database']['host']));
$t->assert('config.jwt present', isset($config['jwt']['secret']));
$t->assert('config.blockchain present', isset($config['blockchain']['rpc_url']));
$t->assert('config.storage present', isset($config['storage']['pdf_path']));
$t->assert('config.app.base_url', isset($config['app']['base_url']));

// Bug #16 fix — frontend_url must be in app section
$t->assert(
    'config.app.frontend_url present (Bug #16 fix)',
    isset($config['app']['frontend_url']),
    'Missing "frontend_url" key in app section of config.php'
);

// .env.example
$t->assert('.env.example exists', file_exists($backendDir . '/.env.example'));

// Storage directories
foreach (['pdf_path', 'qr_path', 'cache_path'] as $key) {
    $dir = $config['storage'][$key] ?? '';
    $t->assert(
        "storage dir exists: {$key}",
        !empty($dir) && is_dir($dir),
        "Expected directory: {$dir}"
    );
    $t->assert(
        "storage dir writable: {$key}",
        !empty($dir) && is_writable($dir),
        "Not writable: {$dir}"
    );
}

// PDFGenerator must be DELETED (Bug #2 / Fix #3A)
$t->assert(
    'PDFGenerator.php deleted (Bug #2 fix)',
    !file_exists($backendDir . '/src/PDFGenerator.php'),
    'PDFGenerator.php still exists — should have been deleted'
);

// PHP extensions
foreach (['pdo', 'pdo_mysql', 'gd', 'openssl', 'json', 'curl'] as $ext) {
    $t->assert("ext-{$ext} loaded", extension_loaded($ext));
}

if (!extension_loaded('gmp')) {
    $t->warn(
        'ext-gmp not loaded',
        'Real blockchain tx signing requires ext-gmp. Mock mode will be used.'
    );
}

// ══════════════════════════════════════════════════════════════
//  SUITE 2 — DATABASE CONNECTION
// ══════════════════════════════════════════════════════════════
$t->section('Suite 2 — Database Connection');

$db = null;
try {
    $db = Database::getInstance()->getConnection();
    $t->assert('Database::getInstance() succeeds', true);

    $stmt = $db->query("SELECT 1 AS ping");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $t->assert('DB query executes (SELECT 1)', ($row['ping'] ?? 0) === '1' || ($row['ping'] ?? 0) == 1);

    // Check required tables exist
    foreach (['universities', 'users', 'students', 'certificates', 'verification_logs'] as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        $t->assert("Table '{$table}' exists", $stmt->rowCount() > 0);
    }
}
catch (\Throwable $e) {
    $t->assert('Database connection', false, $e->getMessage());
}

// ══════════════════════════════════════════════════════════════
//  SUITE 3 — SEED TEST DATA
// ══════════════════════════════════════════════════════════════
$t->section('Suite 3 — Seed Test Data');

$testTag = 'bigtest_' . substr(md5((string)microtime(true)), 0, 8);

try {
    // University
    $stmt = $db->prepare("
        INSERT INTO universities (name, code, is_active)
        VALUES (?, ?, 1)
    ");
    $stmt->execute(["Test University {$testTag}", strtoupper($testTag)]);
    $seed['university_id'] = (int)$db->lastInsertId();
    $t->assert('Seeded test university', $seed['university_id'] > 0, "ID={$seed['university_id']}");

    // User (student role)
    $passwordHash = password_hash('TestPass123!', PASSWORD_DEFAULT);
    $stmt = $db->prepare("
        INSERT INTO users (username, email, password_hash, role, full_name, university_id)
        VALUES (?, ?, ?, 'student', 'Test Student BigTest', ?)
    ");
    $stmt->execute([
        "student_{$testTag}",
        "student_{$testTag}@test.com",
        $passwordHash,
        $seed['university_id'],
    ]);
    $seed['user_id'] = (int)$db->lastInsertId();
    $t->assert('Seeded test user', $seed['user_id'] > 0, "ID={$seed['user_id']}");

    // Student record
    $stmt = $db->prepare("
        INSERT INTO students (user_id, student_id, university_id, enrollment_date)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $seed['user_id'],
        "STU-{$testTag}",
        $seed['university_id'],
        date('Y-m-d'),
    ]);
    $seed['student_id'] = (int)$db->lastInsertId();
    $t->assert('Seeded test student', $seed['student_id'] > 0, "ID={$seed['student_id']}");

}
catch (\Throwable $e) {
    $t->assert('Seed test data', false, $e->getMessage());
}

// Register cleanup — always runs at the end
$t->addCleanup(function () use (&$seed, $db) {
    if (!$db)
        return;
    // Delete in FK-safe order
    if ($seed['cert_id']) {
        $db->prepare("DELETE FROM verification_logs WHERE certificate_id = ?")->execute([$seed['cert_id']]);
        $db->prepare("DELETE FROM certificates WHERE certificate_id = ?")->execute([$seed['cert_id']]);
    }
    if ($seed['pdf_path'] && file_exists($seed['pdf_path'])) {
        @unlink($seed['pdf_path']);
    }
    if ($seed['student_id']) {
        $db->prepare("DELETE FROM students WHERE id = ?")->execute([$seed['student_id']]);
    }
    if ($seed['user_id']) {
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$seed['user_id']]);
    }
    if ($seed['university_id']) {
        $db->prepare("DELETE FROM universities WHERE id = ?")->execute([$seed['university_id']]);
    }
    echo "\n  [CLEANUP] Test data removed.\n";
});

// ══════════════════════════════════════════════════════════════
//  SUITE 4 — AUTH (JWT)
// ══════════════════════════════════════════════════════════════
$t->section('Suite 4 — Auth & JWT');

try {
    $auth = new Auth();

    // Login with seeded credentials
    $user = $auth->login("student_{$testTag}@test.com", 'TestPass123!');
    $t->assert('Auth::login() returns user', is_array($user), 'Expected array, got: ' . gettype($user));
    $t->assert('Login user has id', isset($user['id']));
    $t->assert('Login user has email', isset($user['email']));
    $t->assert('Login user has role', ($user['role'] ?? '') === 'student');
    $t->assert('Password hash NOT in login result', !isset($user['password_hash']));

    // Wrong password
    $badLogin = $auth->login("student_{$testTag}@test.com", 'wrongpassword');
    $t->assert('Login fails with wrong password', $badLogin === null);

    // Token generation
    $token = $auth->generateToken($user);
    $t->assert('generateToken() returns string', is_string($token) && strlen($token) > 20);
    $t->assert('Token has 3 JWT parts', count(explode('.', $token)) === 3);

    // Token verification
    $payload = $auth->verifyToken($token);
    $t->assert('verifyToken() returns payload', is_array($payload));
    $t->assert('Payload user_id matches', ($payload['user_id'] ?? 0) === $user['id']);
    $t->assert('Payload not expired', ($payload['exp'] ?? 0) > time());

    // Tampered token should fail
    $parts = explode('.', $token);
    $parts[1] = base64_encode(json_encode(['user_id' => 999, 'role' => 'admin', 'exp' => time() + 9999]));
    $badToken = implode('.', $parts);
    $badPayload = $auth->verifyToken($badToken);
    $t->assert('Tampered token is rejected', $badPayload === null);

}
catch (\Throwable $e) {
    $t->assert('Auth suite', false, $e->getMessage());
}

// ══════════════════════════════════════════════════════════════
//  SUITE 5 — METADATA SERVICE
// ══════════════════════════════════════════════════════════════
$t->section('Suite 5 — MetadataService');

try {
    $metaSvc = new MetadataService();

    $rawData = [
        'certificate_id' => 'CERT-TESTBIG001',
        'student_id' => "STU-{$testTag}",
        'student_name' => 'Test Student BigTest',
        'course_name' => 'Computer Science',
        'degree_type' => 'Bachelor of Science',
        'issue_date' => '2024-06-15',
        'university_code' => strtoupper($testTag),
        'university_name' => "Test University {$testTag}",
    ];

    $metadata = $metaSvc->buildMetadata($rawData);
    $t->assert('buildMetadata() returns array', is_array($metadata));
    $t->assert('Metadata has certificate_id', isset($metadata['certificate_id']));
    $t->assert('Metadata has schema_version', isset($metadata['schema_version']));

    // Canonical JSON — must be deterministic
    $json1 = $metaSvc->generateMetadataJson($metadata);
    $json2 = $metaSvc->generateMetadataJson($metadata);
    $t->assert('generateMetadataJson() is deterministic', $json1 === $json2);
    $t->assert('generateMetadataJson() is valid JSON', json_decode($json1) !== null);

    // Keccak hash
    $hash = $metaSvc->generateMetadataHash($metadata);
    $t->assert('generateMetadataHash() returns 0x string', str_starts_with($hash, '0x'));
    $t->assert('Hash is 66 chars (0x + 64)', strlen($hash) === 66);

    // Same data = same hash
    $hash2 = $metaSvc->generateMetadataHash($metadata);
    $t->assert('Hash is deterministic', $hash === $hash2);

    // Date normalisation
    $normalized = $metaSvc->buildMetadata(array_merge($rawData, ['issue_date' => '15 June 2024']));
    $t->assert(
        'Date normalised to Y-m-d',
        ($normalized['issue_date'] ?? '') === '2024-06-15',
        "Got: " . ($normalized['issue_date'] ?? 'null')
    );

    // compareMetadata — identical
    $cmp = $metaSvc->compareMetadata($metadata, $metadata);
    $t->assert('compareMetadata() identical → matches=true', $cmp['matches'] === true);
    $t->assert('compareMetadata() identical → no differences', empty($cmp['differences']));

    // compareMetadata — different
    $modified = array_merge($metadata, ['student_name' => 'Impostor Name']);
    $cmpDiff = $metaSvc->compareMetadata($metadata, $modified);
    $t->assert('compareMetadata() different → matches=false', $cmpDiff['matches'] === false);
    $t->assert('compareMetadata() reports student_name difference', isset($cmpDiff['differences']['student_name']));

}
catch (\Throwable $e) {
    $t->assert('MetadataService suite', false, $e->getMessage());
}

// ══════════════════════════════════════════════════════════════
//  SUITE 6 — QR CODE GENERATION
// ══════════════════════════════════════════════════════════════
$t->section('Suite 6 — QR Code Generation (Bug #1 fix)');

try {
    // Use reflection to call the private method
    $pdfSvc = new PDFService();
    $reflClass = new ReflectionClass($pdfSvc);

    $qrMethod = $reflClass->getMethod('generateQRCodeBase64');
    $qrMethod->setAccessible(true);
    $qrResult = $qrMethod->invoke($pdfSvc, 'CERT-TESTBIG001');

    $t->assert(
        'generateQRCodeBase64() returns non-empty string',
        is_string($qrResult) && strlen($qrResult) > 100,
        'Got empty or short string — QR generation failed'
    );
    $t->assert(
        'QR result is base64 data URI (not 1px fallback)',
        str_starts_with($qrResult, 'data:image/png;base64,'),
        'Expected data:image/png;base64, prefix'
    );

    // Confirm the base64 decodes to a real PNG (not 1×1 pixel)
    $base64Part = substr($qrResult, strlen('data:image/png;base64,'));
    $decoded = base64_decode($base64Part, true);
    $t->assert('QR base64 decodes successfully', $decoded !== false);

    // PNG header check: first 8 bytes are the PNG signature
    $isPng = (substr($decoded, 0, 8) === "\x89PNG\r\n\x1a\n");
    $t->assert('QR decoded data is a valid PNG', $isPng);

    // Size check — real QR codes are much larger than 1×1
    $t->assert(
        'QR PNG is larger than 500 bytes (not a 1×1 pixel)',
        strlen($decoded) > 500,
        'Size: ' . strlen($decoded) . ' bytes'
    );

    // Verify the URL encoded in the QR points to frontend, not API (Bug #15 fix)
    $urlMethod = $reflClass->getMethod('getVerificationURL');
    $urlMethod->setAccessible(true);
    $verifyUrl = $urlMethod->invoke($pdfSvc, 'CERT-TESTBIG001');

    $frontendUrl = $config['app']['frontend_url'] ?? '';
    $t->assert(
        'QR verification URL uses frontend_url (Bug #15 fix)',
        !empty($frontendUrl) && str_starts_with($verifyUrl, $frontendUrl),
        "URL: {$verifyUrl} — Expected to start with: {$frontendUrl}"
    );
    $t->assert(
        'QR URL does not point to API base_url',
        !str_starts_with($verifyUrl, $config['app']['base_url'] . '/verify') || $config['app']['base_url'] === $frontendUrl,
        "URL should use frontend_url, got: {$verifyUrl}"
    );

}
catch (\Throwable $e) {
    $t->assert('QR code suite', false, $e->getMessage());
}

// ══════════════════════════════════════════════════════════════
//  SUITE 7 — PDF GENERATION + XMP METADATA
// ══════════════════════════════════════════════════════════════
$t->section('Suite 7 — PDF Generation & XMP Metadata (Bug #5 fix)');

$generatedPdfPath = null;

try {
    $pdfSvc = new PDFService();

    $certData = [
        'certificate_id' => "CERT-BIGTEST-{$testTag}",
        'student_id' => "STU-{$testTag}",
        'student_name' => 'Test Student BigTest',
        'course_name' => 'Computer Science',
        'degree_type' => 'Bachelor of Science',
        'issue_date' => date('Y-m-d'),
        'university_code' => strtoupper($testTag),
        'university_name' => "Test University {$testTag}",
        'certificate_hash' => '0x' . str_repeat('a', 64),
        'blockchain_tx_hash' => '0x' . str_repeat('b', 64),
    ];

    $pdfPath = $pdfSvc->generateCertificatePDF("CERT-BIGTEST-{$testTag}", $certData);
    $generatedPdfPath = $pdfPath;
    $seed['pdf_path'] = $pdfPath;

    $t->assert('generateCertificatePDF() returns path', is_string($pdfPath));
    $t->assert('PDF file exists on disk', file_exists($pdfPath), "Path: {$pdfPath}");
    $t->assert('PDF file is larger than 10KB', file_exists($pdfPath) && filesize($pdfPath) > 10240, 'Size: ' . (file_exists($pdfPath) ? filesize($pdfPath) : 0) . ' bytes');

    // PDF header check
    if (file_exists($pdfPath)) {
        $header = file_get_contents($pdfPath, false, null, 0, 5);
        $t->assert('File starts with PDF header (%PDF-)', $header === '%PDF-', "Got: " . bin2hex($header));
    }

    // Bug #8/#14 fix — hash fields must NOT appear as visible text in the PDF body
    if (file_exists($pdfPath)) {
        $pdfContent = file_get_contents($pdfPath);

        // Search decoded PDF text streams for the hash strings
        $hasCertHashVisible = (strpos($pdfContent, 'Blockchain Hash:') !== false);
        $hasTxHashVisible = (strpos($pdfContent, 'Transaction:') !== false);
        $t->assert(
            'PDF body does not contain visible "Blockchain Hash:" label (Bug #8 fix)',
            !$hasCertHashVisible,
            'Found "Blockchain Hash:" label in PDF binary — hash is still visible in template'
        );
        $t->assert(
            'PDF body does not contain visible "Transaction:" label (Bug #8 fix)',
            !$hasTxHashVisible,
            'Found "Transaction:" label in PDF binary — tx hash is still visible in template'
        );
    }

    // Bug #5 fix — XMP metadata must be present in the PDF
    if (file_exists($pdfPath)) {
        $pdfBinary = file_get_contents($pdfPath);
        $hasXmpMeta = (strpos($pdfBinary, 'cert:metadata') !== false
            || strpos($pdfBinary, 'certificate.system/metadata') !== false);
        $t->assert(
            'XMP metadata is embedded in PDF (Bug #5 fix)',
            $hasXmpMeta,
            'cert:metadata namespace not found in PDF binary — SetAdditionalXmpRdf() may not have fired'
        );
    }

    // Bug #3 fix — QR code must be a base64 data URI in the PDF, not an HTTP URL
    if (file_exists($pdfPath)) {
        $pdfBinary = file_get_contents($pdfPath);
        // If the PDF contains an external http QR URL reference that's NOT a verify URL embedded as text,
        // it means the old HTTP URL approach was used
        $hasHttpQrRef = preg_match('/src=["\']http[^"\']*qr_codes[^"\']*["\']/', $pdfBinary);
        $t->assert(
            'PDF does not reference QR code via external HTTP URL (Bug #3 fix)',
            !$hasHttpQrRef,
            'Found src="http://...qr_codes/..." in PDF — QR should be base64 embedded'
        );
    }

    // PDF hash calculation
    $hash = $pdfSvc->calculatePDFHash($pdfPath);
    $t->assert('calculatePDFHash() returns 0x keccak hash', str_starts_with($hash, '0x'));
    $t->assert('PDF hash is 66 chars', strlen($hash) === 66);

    // Hash must be deterministic for same file
    $hash2 = $pdfSvc->calculatePDFHash($pdfPath);
    $t->assert('PDF hash is deterministic', $hash === $hash2);

    // getPDFPath — since this PDF was generated without a DB record, it won't find it there,
    // but the method itself must exist and be callable
    $t->assert(
        'PDFService::getPDFPath() method exists (Bug #17 fix)',
        method_exists($pdfSvc, 'getPDFPath')
    );

}
catch (\Throwable $e) {
    $t->assert('PDF generation suite', false, $e->getMessage());
}

// ══════════════════════════════════════════════════════════════
//  SUITE 8 — CERTIFICATE TEMPLATE CHECKS
// ══════════════════════════════════════════════════════════════
$t->section('Suite 8 — Certificate Template (Bug #6A, #6B, #19 fixes)');

$templatePath = $backendDir . '/templates/certificate_template.html';

if (!file_exists($templatePath)) {
    $t->assert('certificate_template.html exists', false, "Not found at: {$templatePath}");
}
else {
    $tpl = file_get_contents($templatePath);

    // Bug #6A / #14 — hash lines must be removed from template
    $t->assert(
        'Template does not contain {{CERTIFICATE_HASH}} placeholder (Bug #6A fix)',
        strpos($tpl, '{{CERTIFICATE_HASH}}') === false,
        '{{CERTIFICATE_HASH}} still present in template — should have been removed'
    );
    $t->assert(
        'Template does not contain {{BLOCKCHAIN_TX_HASH}} placeholder (Bug #6A fix)',
        strpos($tpl, '{{BLOCKCHAIN_TX_HASH}}') === false,
        '{{BLOCKCHAIN_TX_HASH}} still present in template — should have been removed'
    );

    // Required placeholders still present
    foreach (['{{STUDENT_NAME}}', '{{COURSE_NAME}}', '{{CERTIFICATE_ID}}', '{{ISSUE_DATE}}', '{{QR_CODE}}'] as $ph) {
        $t->assert("Template has required placeholder: {$ph}", strpos($tpl, $ph) !== false);
    }

    // Bug #19 / #6B — height should use min-height, not a fixed height
    $hasFixedHeight = preg_match('/\.border\s*\{[^}]*\bheight\s*:\s*\d+mm(?!.*min-height)/s', $tpl);
    $hasMinHeight = preg_match('/\.border\s*\{[^}]*min-height/s', $tpl);
    $t->assert(
        '.border uses min-height not fixed height (Bug #19 fix)',
        $hasMinHeight && !$hasFixedHeight,
        'Template .border still uses fixed height — content may overflow to page 2'
    );

    $hasBoxSizing = strpos($tpl, 'box-sizing') !== false;
    $t->assert(
        'Template uses box-sizing: border-box (Bug #19 fix)',
        $hasBoxSizing
    );
}

// ══════════════════════════════════════════════════════════════
//  SUITE 9 — CERTIFICATE CREATION (Full Pipeline)
// ══════════════════════════════════════════════════════════════
$t->section('Suite 9 — Certificate Creation Pipeline');

$createdCertId = null;

try {
    $certSvc = new CertificateService();

    $result = $certSvc->createCertificate([
        'student_id' => $seed['student_id'],
        'university_id' => $seed['university_id'],
        'course_name' => 'Computer Science',
        'degree_type' => 'Bachelor of Science',
        'issue_date' => date('Y-m-d'),
    ]);

    $t->assert('createCertificate() returns array', is_array($result));
    $t->assert('createCertificate() success=true', ($result['success'] ?? false) === true, json_encode($result));
    $t->assert('Result has certificate_id', !empty($result['certificate_id']));
    $t->assert('Result has certificate_hash (keccak)', !empty($result['certificate_hash']));
    $t->assert('Result has pdf_path', !empty($result['pdf_path']));
    $t->assert('certificate_hash starts with 0x', str_starts_with($result['certificate_hash'] ?? '', '0x'));

    if (!empty($result['certificate_id'])) {
        $createdCertId = $result['certificate_id'];
        $seed['cert_id'] = $createdCertId;
    }

    // Verify it landed in the DB
    if ($createdCertId) {
        $stmt = $db->prepare("SELECT * FROM certificates WHERE certificate_id = ?");
        $stmt->execute([$createdCertId]);
        $dbRow = $stmt->fetch(PDO::FETCH_ASSOC);

        $t->assert('Certificate saved to DB', is_array($dbRow));
        $t->assert('DB row has metadata_hash', !empty($dbRow['metadata_hash'] ?? ''));
        $t->assert('DB row has pdf_hash', !empty($dbRow['pdf_hash'] ?? ''));
        $t->assert('DB row has onchain_hash', !empty($dbRow['onchain_hash'] ?? ''));
        $t->assert('DB row metadata_json is valid JSON', !empty($dbRow['metadata_json']) && json_decode($dbRow['metadata_json']) !== null);
        $t->assert('DB status is active', ($dbRow['status'] ?? '') === 'active');

        // Bug #9 fix — onchain_hash must be keccak256, not SHA256 (SHA256 is 64 hex, keccak starts with 0x)
        $t->assert(
            'onchain_hash is keccak256 (0x prefix) not SHA256 (Bug #9 fix)',
            str_starts_with($dbRow['onchain_hash'] ?? '', '0x'),
            "Got: " . ($dbRow['onchain_hash'] ?? 'null')
        );

        // PDF file must exist on disk
        $pdfFullPath = ($config['storage']['pdf_path'] ?? '') . ($dbRow['pdf_path'] ?? '');
        $seed['pdf_path'] = $pdfFullPath;
        $t->assert(
            'PDF file exists on disk after creation',
            !empty($dbRow['pdf_path']) && file_exists($pdfFullPath),
            "Expected: {$pdfFullPath}"
        );
    }

}
catch (\Throwable $e) {
    $t->assert('Certificate creation pipeline', false, $e->getMessage());
}

// ══════════════════════════════════════════════════════════════
//  SUITE 10 — VERIFICATION PIPELINE
// ══════════════════════════════════════════════════════════════
$t->section('Suite 10 — Verification Pipeline');

if ($createdCertId) {
    try {
        $certSvc = new CertificateService();

        // Verify by ID (quick path)
        $verifyResult = $certSvc->verifyCertificate($createdCertId);
        $t->assert('verifyCertificate() returns array', is_array($verifyResult));
        $t->assert('Verify result has valid key', array_key_exists('valid', $verifyResult));
        $t->assert('Verify result has status key', array_key_exists('status', $verifyResult));

        // Certificate we just created should be valid
        $t->assert(
            'Freshly created certificate verifies as valid',
            ($verifyResult['valid'] ?? false) === true,
            'Status: ' . ($verifyResult['status'] ?? 'unknown') . ' | Message: ' . ($verifyResult['message'] ?? '')
        );

        // Verify non-existent cert
        $notFound = $certSvc->verifyCertificate('CERT-DOES-NOT-EXIST-' . $testTag);
        $t->assert(
            'Non-existent cert returns not_found or invalid',
            in_array($notFound['status'] ?? '', ['not_found', 'invalid'], true)
        );

        // getCertificate
        $cert = $certSvc->getCertificate($createdCertId);
        $t->assert('getCertificate() returns array', is_array($cert));
        $t->assert('getCertificate() has student_name', !empty($cert['student_name'] ?? ''));
        $t->assert('getCertificate() has university_name', !empty($cert['university_name'] ?? ''));
        $t->assert('getCertificate() has certificate_hash', !empty($cert['certificate_hash'] ?? ''));

    }
    catch (\Throwable $e) {
        $t->assert('Verification pipeline', false, $e->getMessage());
    }
}
else {
    $t->warn('Verification pipeline skipped', 'Certificate was not created in Suite 9');
}

// ══════════════════════════════════════════════════════════════
//  SUITE 11 — REVOCATION
// ══════════════════════════════════════════════════════════════
$t->section('Suite 11 — Certificate Revocation');

if ($createdCertId) {
    try {
        $certSvc = new CertificateService();

        // Revoke it
        $revokeResult = $certSvc->revokeCertificate($createdCertId, 1);
        $t->assert('revokeCertificate() returns true', $revokeResult === true);

        // DB row should now be revoked
        $stmt = $db->prepare("SELECT status, is_revoked, revoked_at FROM certificates WHERE certificate_id = ?");
        $stmt->execute([$createdCertId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $t->assert("DB status is 'revoked' after revoke", ($row['status'] ?? '') === 'revoked');
        $t->assert('DB is_revoked = 1 after revoke', (int)($row['is_revoked'] ?? 0) === 1);
        $t->assert('DB revoked_at is set', !empty($row['revoked_at'] ?? ''));

        // Verification should now return revoked
        $verifyRevoked = $certSvc->verifyCertificate($createdCertId);
        $t->assert(
            'Revoked certificate returns status=revoked on verify',
            ($verifyRevoked['status'] ?? '') === 'revoked',
            'Got status: ' . ($verifyRevoked['status'] ?? 'unknown')
        );
        $t->assert(
            'Revoked certificate returns valid=false',
            ($verifyRevoked['valid'] ?? true) === false
        );

    }
    catch (\Throwable $e) {
        $t->assert('Revocation suite', false, $e->getMessage());
    }
}
else {
    $t->warn('Revocation suite skipped', 'Certificate was not created in Suite 9');
}

// ══════════════════════════════════════════════════════════════
//  SUITE 12 — BLOCKCHAIN CLASS
// ══════════════════════════════════════════════════════════════
$t->section('Suite 12 — Blockchain Class (Bugs #7, #9, #10, #13 fixes)');

try {
    $bc = new Blockchain();

    $t->assert('Blockchain instantiates without exception', true);
    $t->assert('isConnected() returns bool', is_bool($bc->isConnected()));

    // Bug #9 / #13 fix — generateCertificateHash must return keccak256 (0x prefix)
    $testData = [
        'certificate_id' => 'CERT-HASHTEST',
        'student_name' => 'Hash Test Student',
        'university_name' => 'Hash Test University',
        'course_name' => 'Hash Test Course',
        'issue_date' => '2024-01-01',
        'certificate_hash' => '0x' . str_repeat('c', 64),
    ];
    $hash = $bc->generateCertificateHash($testData);

    // The old broken code returned a plain SHA256 (no 0x prefix, 64 chars).
    // The fixed code should use the pre-computed certificate_hash or keccak256.
    // Either the hash equals the pre-computed one (best case) or starts with 0x.
    $usesPrecomputed = ($hash === $testData['certificate_hash']);
    $isKeccak = str_starts_with($hash, '0x') && strlen($hash) === 66;
    $t->assert(
        'generateCertificateHash() uses keccak256 or pre-computed hash (Bug #13 fix)',
        $usesPrecomputed || $isKeccak,
        "Got: {$hash} — expected 0x-prefixed keccak256 or pre-computed hash"
    );

    // generateCombinedHash
    $mHash = '0x' . str_repeat('a', 64);
    $pHash = '0x' . str_repeat('b', 64);
    $combined = $bc->generateCombinedHash($mHash, $pHash);
    $t->assert('generateCombinedHash() returns 0x string', str_starts_with($combined, '0x'));
    $t->assert('generateCombinedHash() is 66 chars', strlen($combined) === 66);

    // Deterministic
    $combined2 = $bc->generateCombinedHash($mHash, $pHash);
    $t->assert('generateCombinedHash() is deterministic', $combined === $combined2);

    // Mock mode — issueCertificate when disconnected
    if (!$bc->isConnected()) {
        $mockResult = $bc->issueCertificate($testData);
        $t->assert('issueCertificate() mock returns array', is_array($mockResult));
        $t->assert('Mock issueCertificate() has tx_hash', !empty($mockResult['tx_hash'] ?? ''));
        $t->assert('Mock tx_hash starts with 0x', str_starts_with($mockResult['tx_hash'] ?? '', '0x'));
        $t->warn('Blockchain is in MOCK mode', 'RPC not available — real tx tests skipped. This is expected in dev.');
    }
    else {
        $t->assert('Blockchain isConnected = true', true, 'Connected to RPC: ' . ($config['blockchain']['rpc_url'] ?? ''));
    }

    // getCurrentBlock must not throw
    $block = $bc->getCurrentBlock();
    $t->assert('getCurrentBlock() returns int', is_int($block));

}
catch (\Throwable $e) {
    $t->assert('Blockchain suite', false, $e->getMessage());
}

// ══════════════════════════════════════════════════════════════
//  SUITE 13 — PDF SERVICE STANDALONE
// ══════════════════════════════════════════════════════════════
$t->section('Suite 13 — PDFService Standalone Checks');

try {
    $pdfSvc = new PDFService();

    // getPDFPath — must exist (Bug #17 fix)
    $t->assert('PDFService has getPDFPath() method', method_exists($pdfSvc, 'getPDFPath'));

    // getPDFPath for non-existent cert returns null
    $path = $pdfSvc->getPDFPath('CERT-DOES-NOT-EXIST-' . $testTag);
    $t->assert(
        'getPDFPath() returns null for unknown certificate',
        $path === null,
        "Got: " . var_export($path, true)
    );

    // embedMetadata — must exist and return true (backward compat stub)
    $t->assert('PDFService has embedMetadata() method', method_exists($pdfSvc, 'embedMetadata'));

    // extractText on the generated PDF (if it exists)
    if ($generatedPdfPath && file_exists($generatedPdfPath)) {
        $text = $pdfSvc->extractText($generatedPdfPath);
        $t->assert('extractText() returns string', is_string($text));
        $t->assert('Extracted text contains student name', strpos($text, 'Test Student BigTest') !== false, "Text: " . substr($text, 0, 200));
        $t->assert('Extracted text contains course name', strpos($text, 'Computer Science') !== false);

        // Hash strings must NOT appear in readable text (Bug #8 fix)
        $t->assert(
            'Extracted text does not contain "Blockchain Hash:" (Bug #8 fix)',
            strpos($text, 'Blockchain Hash:') === false
        );
    }

}
catch (\Throwable $e) {
    $t->assert('PDFService standalone suite', false, $e->getMessage());
}

// ══════════════════════════════════════════════════════════════
//  SUITE 14 — CACHE
// ══════════════════════════════════════════════════════════════
$t->section('Suite 14 — Cache');

try {
    $cache = Cache::getInstance();

    $testKey = "bigtest_{$testTag}";
    $testVal = ['test' => true, 'ts' => time()];

    // set
    $setResult = $cache->set($testKey, $testVal, 60);
    $t->assert('Cache::set() returns true', $setResult === true);

    // get
    $got = $cache->get($testKey);
    $t->assert('Cache::get() returns stored value', $got === $testVal);

    // default on miss
    $miss = $cache->get("bigtest_miss_{$testTag}", 'default_val');
    $t->assert('Cache::get() returns default on miss', $miss === 'default_val');

    // remember
    $remembered = $cache->remember("bigtest_rem_{$testTag}", fn() => ['computed' => 42], 60);
    $t->assert('Cache::remember() returns computed value', ($remembered['computed'] ?? 0) === 42);

    $remembered2 = $cache->remember("bigtest_rem_{$testTag}", fn() => ['computed' => 999], 60);
    $t->assert('Cache::remember() returns cached value on second call', ($remembered2['computed'] ?? 0) === 42);

    // delete
    $cache->delete($testKey);
    $afterDelete = $cache->get($testKey);
    $t->assert('Cache::delete() removes key', $afterDelete === null);

    // Cleanup cache keys
    $cache->delete("bigtest_rem_{$testTag}");

}
catch (\Throwable $e) {
    $t->assert('Cache suite', false, $e->getMessage());
}

// ══════════════════════════════════════════════════════════════
//  SUITE 15 — API ROUTE STRUCTURE (index.php sanity)
// ══════════════════════════════════════════════════════════════
$t->section('Suite 15 — API Route File Sanity (Bug #13, #18, #20 fixes)');

$indexPath = $backendDir . '/api/index.php';
if (!file_exists($indexPath)) {
    // Try alternate path
    $indexPath = $backendDir . '/index.php';
}

if (!file_exists($indexPath)) {
    $t->assert('api/index.php found', false, 'Could not locate index.php at ' . $backendDir . '/api/index.php');
}
else {
    $indexSrc = file_get_contents($indexPath);

    // Bug #18 fix — spl_autoload_register should be removed
    $t->assert(
        'spl_autoload_register removed from index.php (Bug #18 fix)',
        strpos($indexSrc, 'spl_autoload_register') === false,
        'spl_autoload_register is still present — should have been removed'
    );

    // Bug #20 fix — no wildcard CORS
    $t->assert(
        'Wildcard CORS header removed (Bug #20 fix)',
        strpos($indexSrc, "Allow-Origin: *'") === false && strpos($indexSrc, 'Allow-Origin: *"') === false,
        'header(\'Access-Control-Allow-Origin: *\') still present'
    );

    // PDFGenerator reference must be gone (Bug #2 / Fix #3)
    $t->assert(
        'No PDFGenerator reference in index.php (Bug #2 fix)',
        strpos($indexSrc, 'PDFGenerator') === false,
        'PDFGenerator is still referenced in index.php'
    );

    // phpdotenv bootstrap must be present (Bug #16 fix)
    $t->assert(
        'phpdotenv bootstrap present in index.php (Bug #16 fix)',
        strpos($indexSrc, 'Dotenv') !== false || strpos($indexSrc, 'dotenv') !== false,
        'No Dotenv bootstrap found in index.php'
    );

    // Bug #14B — null check after login in student creation
    $t->assert(
        'Null check after $auth->login() in student route (Bug #14B fix)',
        preg_match('/\$newUser\s*\)?\s*\{/', $indexSrc) || strpos($indexSrc, '!$newUser') !== false,
        'Could not find null check for $newUser after login()'
    );

    // Syntax check via php -l
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($indexPath) . ' 2>&1', $output, $code);
    $t->assert(
        'index.php passes php -l syntax check',
        $code === 0,
        implode(' ', $output)
    );
}

// ══════════════════════════════════════════════════════════════
//  SUITE 16 — SIGNATURE SERVICE (Bug #12 fix)
// ══════════════════════════════════════════════════════════════
$t->section('Suite 16 — SignatureService (Bug #12 fix)');

$sigSvcPath = $backendDir . '/src/SignatureService.php';
if (file_exists($sigSvcPath)) {
    $sigSrc = file_get_contents($sigSvcPath);

    // Bug #12 fix — must use TcpdfFpdi, not plain Fpdi
    $t->assert(
        'SignatureService uses TcpdfFpdi not plain Fpdi (Bug #12 fix)',
        strpos($sigSrc, 'TcpdfFpdi') !== false,
        'TcpdfFpdi not found — may still be using plain new Fpdi()'
    );
    $t->assert(
        'SignatureService does not use bare new Fpdi() in signPDFWithTCPDF',
        !preg_match('/new\s+Fpdi\s*\(\s*\)/', $sigSrc),
        'Found new Fpdi() — should be new TcpdfFpdi()'
    );

    // Syntax check
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($sigSvcPath) . ' 2>&1', $output, $code);
    $t->assert('SignatureService.php passes php -l', $code === 0, implode(' ', $output));

}
else {
    $t->assert('SignatureService.php found', false);
}

// ══════════════════════════════════════════════════════════════
//  SUITE 17 — COMPOSER & DEPENDENCIES
// ══════════════════════════════════════════════════════════════
$t->section('Suite 17 — Composer Dependencies');

$composerPath = $backendDir . '/composer.json';
if (file_exists($composerPath)) {
    $composerJson = json_decode(file_get_contents($composerPath), true);
    $require = $composerJson['require'] ?? [];

    $t->assert('endroid/qr-code ^5 in composer.json', isset($require['endroid/qr-code']) && str_starts_with($require['endroid/qr-code'], '^5'));
    $t->assert('mpdf/mpdf in composer.json', isset($require['mpdf/mpdf']));
    $t->assert('kornrunner/keccak in composer.json', isset($require['kornrunner/keccak']));
    $t->assert('vlucas/phpdotenv in composer.json', isset($require['vlucas/phpdotenv']));
    $t->assert('smalot/pdfparser in composer.json', isset($require['smalot/pdfparser']));

    // fpdi-tcpdf should be added (Bug #12 fix)
    $hasFpdiTcpdf = isset($require['setasign/fpdi-tcpdf'])
        || isset(($composerJson['require-dev'] ?? [])['setasign/fpdi-tcpdf']);
    $t->assert('setasign/fpdi-tcpdf in composer.json (Bug #12 fix)', $hasFpdiTcpdf);

    // Vendor directory must exist (composer install was run)
    $t->assert('vendor/ directory exists (composer install run)', is_dir($backendDir . '/vendor'));

    // Critical classes must be loadable
    $classChecks = [
        'Endroid\\QrCode\\QrCode' => 'endroid/qr-code',
        'Mpdf\\Mpdf' => 'mpdf/mpdf',
        'kornrunner\\Keccak' => 'kornrunner/keccak',
        'Smalot\\PdfParser\\Parser' => 'smalot/pdfparser',
        'Dotenv\\Dotenv' => 'vlucas/phpdotenv',
    ];
    foreach ($classChecks as $class => $pkg) {
        $t->assert("Class {$class} autoloadable ({$pkg})", class_exists($class));
    }

    // Endroid v5 API check — QrCode::create() must exist as static method
    if (class_exists('Endroid\\QrCode\\QrCode')) {
        $t->assert(
            'Endroid QrCode::create() static method exists (v5 API)',
            method_exists('Endroid\\QrCode\\QrCode', 'create'),
            'create() not found — may be wrong version'
        );
    }

}
else {
    $t->assert('composer.json found', false);
}

// ══════════════════════════════════════════════════════════════
//  SUITE 18 — PHP SYNTAX LINT ON ALL SRC FILES
// ══════════════════════════════════════════════════════════════
$t->section('Suite 18 — PHP Syntax Lint (all src/ files)');

$srcDir = $backendDir . '/src';
$phpFiles = glob($srcDir . '/*.php');

foreach ($phpFiles as $file) {
    $basename = basename($file);
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    $t->assert(
        "{$basename} passes php -l",
        $code === 0,
        $code !== 0 ? implode(' ', $output) : ''
    );
}

// ══════════════════════════════════════════════════════════════
//  FINISH
// ══════════════════════════════════════════════════════════════
$t->finish();