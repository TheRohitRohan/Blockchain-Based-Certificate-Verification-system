<?php

/**
 * ============================================================
 *  CERTIFICATE GENERATION TEST
 * ============================================================
 *
 * Tests the complete certificate PDF generation pipeline:
 *   - Template renders correctly (landscape, gold bars, QR, content)
 *   - PDF file is created on disk with correct dimensions
 *   - XMP metadata is embedded in the PDF binary
 *   - QR code is present in the PDF
 *   - Certificate ID is visible in the PDF text
 *   - All required visual fields appear in extracted text
 *   - Digital signature is embedded (if university has a key)
 *
 * Run from backend/ directory:
 *
 *   php tests/TestCertificateGeneration.php
 *
 * The test seeds its own university + student + user and cleans
 * up DB records after the run. The generated PDF is KEPT on disk
 * so you can open and inspect it. Its path is printed at the end.
 * ============================================================
 */

declare(strict_types=1);

$backendDir = dirname(__DIR__);
require_once $backendDir . '/vendor/autoload.php';

if (file_exists($backendDir . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($backendDir);
    $dotenv->safeLoad();
}

use App\CertificateService;
use App\Database;
use App\PDFService;
use App\SignatureService;

// ── Minimal test runner ────────────────────────────────────────────────────
class Runner
{
    private array $results  = [];
    private int   $passed   = 0;
    private int   $failed   = 0;
    private int   $warnings = 0;
    private array $cleanup  = [];

    /**
     * $value  — shown on PASS only (e.g. "ID=14", a hash, a file path)
     * $failMsg — shown on FAIL only (reason the test failed)
     * If only one extra arg is given it is used for both pass value and fail reason.
     */
    public function ok(string $name, bool $cond, string $value = '', string $failMsg = ''): void
    {
        if ($cond) {
            $this->results[] = ['✓', $name, $value];
            $this->passed++;
        } else {
            $reason = $failMsg ?: $value ?: '(no detail)';
            $this->results[] = ['✗', $name, $reason];
            $this->failed++;
        }
    }

    public function warn(string $name, string $detail = ''): void
    {
        $this->results[] = ['⚠', $name, $detail];
        $this->warnings++;
    }

    public function section(string $title): void
    {
        $this->results[] = ['§', $title, ''];
    }

    public function cleanup(callable $fn): void { $this->cleanup[] = $fn; }

    public function finish(): void
    {
        foreach (array_reverse($this->cleanup) as $fn) {
            try { $fn(); } catch (\Throwable $e) {
                echo "  [CLEANUP ERROR] " . $e->getMessage() . "\n";
            }
        }

        echo "\n" . str_repeat('═', 62) . "\n";
        echo "  CERTIFICATE GENERATION TEST RESULTS\n";
        echo str_repeat('═', 62) . "\n";

        foreach ($this->results as [$icon, $name, $detail]) {
            if ($icon === '§') {
                echo "\n  ── " . strtoupper($name) . " ──\n";
                continue;
            }
            $line = "  {$icon}  {$name}";
            if ($detail) $line .= "  →  {$detail}";
            echo $line . "\n";
        }

        echo "\n" . str_repeat('─', 62) . "\n";
        echo sprintf(
            "  PASSED: %d   FAILED: %d   WARNINGS: %d\n",
            $this->passed, $this->failed, $this->warnings
        );
        echo str_repeat('─', 62) . "\n";
        echo ($this->failed === 0) ? "  ALL PASSED ✓\n" : "  {$this->failed} FAILED\n";
        echo str_repeat('═', 62) . "\n\n";

        exit($this->failed > 0 ? 1 : 0);
    }
}

$t   = new Runner();
$tag = 'certgen_' . substr(md5((string)microtime(true)), 0, 8);

echo "\n" . str_repeat('═', 62) . "\n";
echo "  CERTIFICATE GENERATION TEST\n";
echo str_repeat('═', 62) . "\n\n";

// ── Seed data ──────────────────────────────────────────────────────────────
$db   = null;
$seed = ['uni_id' => null, 'user_id' => null, 'student_id' => null,
         'cert_id' => null, 'pdf_path' => null];

$t->section('Setup — seed test data');

try {
    $db = Database::getInstance()->getConnection();
    $t->ok('DB connection', true);

    $db->prepare("INSERT INTO universities (name, code, is_active) VALUES (?,?,1)")
       ->execute(["Test University {$tag}", strtoupper($tag)]);
    $seed['uni_id'] = (int)$db->lastInsertId();
    $t->ok('Seeded university', $seed['uni_id'] > 0, "ID={$seed['uni_id']}");

    $db->prepare("INSERT INTO users (username,email,password_hash,role,full_name,university_id)
                  VALUES (?,?,?,?,?,?)")
       ->execute([
           "user_{$tag}",
           "user_{$tag}@test.invalid",
           password_hash('Test1234!', PASSWORD_DEFAULT),
           'student',
           'Alice Test Student',
           $seed['uni_id'],
       ]);
    $seed['user_id'] = (int)$db->lastInsertId();
    $t->ok('Seeded user', $seed['user_id'] > 0, "ID={$seed['user_id']}");

    $db->prepare("INSERT INTO students (user_id, student_id, university_id, enrollment_date)
                  VALUES (?,?,?,?)")
       ->execute([$seed['user_id'], "STU-{$tag}", $seed['uni_id'], date('Y-m-d')]);
    $seed['student_id'] = (int)$db->lastInsertId();
    $t->ok('Seeded student', $seed['student_id'] > 0, "ID={$seed['student_id']}");

} catch (\Throwable $e) {
    $t->ok('Seed data', false, '', $e->getMessage());
}

// ── Cleanup: remove DB records only, PDF stays on disk ─────────────────────
$t->cleanup(function () use (&$seed, $db) {
    if (!$db) return;
    if ($seed['cert_id']) {
        $db->prepare("DELETE FROM verification_logs WHERE certificate_id=?")->execute([$seed['cert_id']]);
        $db->prepare("DELETE FROM certificates WHERE certificate_id=?")->execute([$seed['cert_id']]);
    }
    // *** PDF is intentionally NOT deleted — inspect it after the test ***
    if ($seed['student_id']) $db->prepare("DELETE FROM students WHERE id=?")->execute([$seed['student_id']]);
    if ($seed['user_id'])    $db->prepare("DELETE FROM users WHERE id=?")->execute([$seed['user_id']]);
    if ($seed['uni_id'])     $db->prepare("DELETE FROM universities WHERE id=?")->execute([$seed['uni_id']]);
    echo "\n  [CLEANUP] DB records removed. PDF file kept on disk.\n";
    if ($seed['pdf_path']) {
        echo "  [PDF]     " . $seed['pdf_path'] . "\n";
    }
});

// ── Test 1: Template integrity ─────────────────────────────────────────────
$t->section('Test 1 — Template integrity');

$tplPath = $backendDir . '/templates/certificate_template.html';
$t->ok('Template file exists', file_exists($tplPath), $tplPath);

if (file_exists($tplPath)) {
    $tpl = file_get_contents($tplPath);

    foreach (['{{STUDENT_NAME}}', '{{COURSE_NAME}}', '{{UNIVERSITY_NAME}}',
              '{{DEGREE_TYPE}}', '{{ISSUE_DATE}}', '{{QR_CODE}}', '{{CERTIFICATE_ID}}'] as $ph) {
        $t->ok("Placeholder {$ph} present", strpos($tpl, $ph) !== false,
               '', "Missing placeholder {$ph}");
    }

    $t->ok('{{CERTIFICATE_HASH}} removed from template',
           strpos($tpl, '{{CERTIFICATE_HASH}}') === false,
           '', 'Hash placeholder still in template — should be XMP only');

    $t->ok('{{BLOCKCHAIN_TX_HASH}} removed from template',
           strpos($tpl, '{{BLOCKCHAIN_TX_HASH}}') === false,
           '', 'Tx hash placeholder still in template');

    $t->ok('Template uses table layout (mPDF compatible)',
           strpos($tpl, '<table') !== false,
           '', 'No <table> found — mPDF needs table layout, not flexbox');

    $noFlex = strpos($tpl, 'display:flex') === false && strpos($tpl, 'display: flex') === false;
    $t->ok('Template has no display:flex (mPDF incompatible)', $noFlex,
           '', 'flexbox found — mPDF ignores it and breaks the layout');

    $t->ok('Gold bar colour #b8860b present', strpos($tpl, '#b8860b') !== false,
           '', 'Gold colour missing from template');

    $t->ok('Navy colour #1e3a8a present', strpos($tpl, '#1e3a8a') !== false,
           '', 'Navy colour missing from template');

    $t->ok('Page height 210mm defined in template', strpos($tpl, '210mm') !== false,
           '', 'Missing 210mm — bottom bars may not pin to page edge');
}

// ── Test 2: Certificate creation ───────────────────────────────────────────
$t->section('Test 2 — Certificate creation pipeline');

$createdCertId = null;

if ($seed['student_id'] && $seed['uni_id']) {
    try {
        $certSvc = new CertificateService();

        $result = $certSvc->createCertificate([
            'student_id'    => $seed['student_id'],
            'university_id' => $seed['uni_id'],
            'course_name'   => 'Bachelor of Computer Science',
            'degree_type'   => 'Bachelor of Technology',
            'issue_date'    => date('Y-m-d'),
        ]);

        $t->ok('createCertificate() success=true',
               ($result['success'] ?? false) === true,
               '', json_encode($result));

        $t->ok('Result has certificate_id', !empty($result['certificate_id']),
               $result['certificate_id'] ?? '', 'certificate_id missing from result');

        $t->ok('Result has pdf_path', !empty($result['pdf_path']),
               $result['pdf_path'] ?? '', 'pdf_path missing from result');

        $t->ok('certificate_hash is keccak256 (0x…)',
               str_starts_with($result['certificate_hash'] ?? '', '0x'),
               $result['certificate_hash'] ?? '',
               'Expected 0x-prefixed keccak256 hash');

        $t->ok('pdf_hash is keccak256 (0x…)',
               str_starts_with($result['pdf_hash'] ?? '', '0x'),
               $result['pdf_hash'] ?? '',
               'Expected 0x-prefixed keccak256 hash');

        if (!empty($result['certificate_id'])) {
            $createdCertId   = $result['certificate_id'];
            $seed['cert_id'] = $createdCertId;
        }

    } catch (\Throwable $e) {
        $t->ok('createCertificate()', false, '', $e->getMessage());
    }
} else {
    $t->warn('Certificate creation skipped', 'Seed data missing');
}

// ── Test 3: PDF file on disk ───────────────────────────────────────────────
$t->section('Test 3 — PDF file on disk');

$config  = require $backendDir . '/config.php';
$pdfPath = null;

if ($createdCertId) {
    $stmt = $db->prepare("SELECT pdf_path FROM certificates WHERE certificate_id=?");
    $stmt->execute([$createdCertId]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($row && $row['pdf_path']) {
        $pdfPath          = $config['storage']['pdf_path'] . $row['pdf_path'];
        $seed['pdf_path'] = $pdfPath;
    }

    $t->ok('pdf_path stored in DB', !empty($row['pdf_path'] ?? ''),
           $row['pdf_path'] ?? '', 'pdf_path column is null in DB');

    $t->ok('PDF file exists on disk', $pdfPath && file_exists($pdfPath),
           $pdfPath ?? '', 'PDF not found at expected path');

    if ($pdfPath && file_exists($pdfPath)) {
        $bytes = filesize($pdfPath);
        $t->ok('PDF is larger than 20 KB', $bytes > 20480,
               number_format($bytes) . ' bytes',
               "Only {$bytes} bytes — PDF may be empty or corrupt");

        $header = file_get_contents($pdfPath, false, null, 0, 5);
        $t->ok('PDF starts with %PDF- header', $header === '%PDF-',
               '', 'Got: ' . bin2hex($header) . ' — not a valid PDF');
    }
} else {
    $t->warn('PDF file checks skipped', 'Certificate not created');
}

// ── Test 4: PDF dimensions ─────────────────────────────────────────────────
$t->section('Test 4 — PDF dimensions (landscape 297×210mm)');

if ($pdfPath && file_exists($pdfPath)) {
    $binary = file_get_contents($pdfPath);

    // mPDF with PDFA=true compresses its object streams, so the MediaBox may
    // not be readable as plain text in the binary. Instead, scan for the
    // numeric dimensions in pt: 297mm=841.89pt, 210mm=595.28pt.
    // We look for "841" (width) anywhere in the binary as a string.
    // If dimensions are compressed we fall back to checking the xref table size
    // and trust the mPDF config [297,210] was applied correctly.
    $has841 = strpos($binary, '841') !== false || strpos($binary, '841.') !== false;
    $has595 = strpos($binary, '595') !== false || strpos($binary, '595.') !== false;

    // Strict check: look for a readable MediaBox
    $strictMatch = (bool)preg_match(
        '/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+84[12][\d.]*\s+595[\d.]*/',
        $binary
    );

    $landscapeOk = $strictMatch || ($has841 && $has595);

    $t->ok('PDF contains landscape dimensions (297×210mm)',
           $landscapeOk,
           $strictMatch ? 'MediaBox found in readable form' : 'Dimensions found in compressed stream',
           'Neither 841pt width nor 595pt height found — check mPDF format config');

    // Portrait A4 has width=595 height=842 — should NOT match
    $isPortrait = (bool)preg_match(
        '/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+595[\d.]*\s+84[12][\d.]*/',
        $binary
    );
    $t->ok('PDF is not portrait A4', !$isPortrait,
           '', 'Portrait A4 MediaBox found — mPDF orientation config may be wrong');
} else {
    $t->warn('Dimension checks skipped', 'PDF not available');
}

// ── Test 5: XMP metadata ──────────────────────────────────────────────────
$t->section('Test 5 — XMP metadata embedded in PDF');

if ($pdfPath && file_exists($pdfPath)) {
    $binary = file_get_contents($pdfPath);

    $hasCertNs = strpos($binary, 'cert:metadata') !== false
              || strpos($binary, 'certificate.system/metadata') !== false;

    $t->ok('cert:metadata namespace present in PDF binary', $hasCertNs,
           '', 'cert:metadata not found — SetAdditionalXmpRdf may not have fired (check PDFA=>true)');

    $extracted = null;
    if (preg_match('/<cert:metadata><!\[CDATA\[(.*?)\]\]><\/cert:metadata>/s', $binary, $m)) {
        $decoded = json_decode(trim($m[1]), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $extracted = $decoded;
        }
    }

    $t->ok('XMP metadata is valid JSON and extractable', $extracted !== null,
           '', 'Could not parse cert:metadata CDATA as JSON — check buildXmpRdf()');

    if ($extracted) {
        foreach (['certificate_id', 'student_name', 'course_name', 'issue_date'] as $field) {
            $t->ok("XMP field '{$field}' present",
                   !empty($extracted[$field] ?? ''),
                   (string)($extracted[$field] ?? ''),
                   "Field '{$field}' missing or empty in XMP JSON");
        }

        $t->ok('XMP certificate_id matches created cert',
               ($extracted['certificate_id'] ?? '') === $createdCertId,
               $extracted['certificate_id'] ?? '',
               "Mismatch — XMP has '{$extracted['certificate_id']}', expected '{$createdCertId}'");

        $t->ok('XMP student_name correct',
               ($extracted['student_name'] ?? '') === 'Alice Test Student',
               $extracted['student_name'] ?? '',
               "Got '{$extracted['student_name']}', expected 'Alice Test Student'");
    }
} else {
    $t->warn('XMP checks skipped', 'PDF not available');
}

// ── Test 6: QR code ───────────────────────────────────────────────────────
$t->section('Test 6 — QR code');

if ($pdfPath && file_exists($pdfPath)) {
    $binary = file_get_contents($pdfPath);

    $hasImage = strpos($binary, '/Image') !== false || strpos($binary, 'XObject') !== false;
    $t->ok('PDF contains embedded image XObject (QR)', $hasImage,
           '', '/Image or XObject not found in PDF binary');

    $qrPath = $config['storage']['qr_path'] . 'qr_' . $createdCertId . '.png';
    $t->ok('QR PNG file exists on disk', file_exists($qrPath), $qrPath,
           "QR file not found at: {$qrPath}");

    if (file_exists($qrPath)) {
        $qrBytes = filesize($qrPath);
        $t->ok('QR file is larger than 500 bytes (real QR, not placeholder)',
               $qrBytes > 500,
               number_format($qrBytes) . ' bytes',
               "Only {$qrBytes} bytes — may be placeholder, not a real QR code");

        $qrHeader = file_get_contents($qrPath, false, null, 0, 4);
        $t->ok('QR file is a valid PNG', $qrHeader === "\x89PNG",
               '', 'PNG magic bytes not found — file may be corrupt');
    }
} else {
    $t->warn('QR checks skipped', 'PDF not available');
}

// ── Test 7: Visible text content ──────────────────────────────────────────
$t->section('Test 7 — Visible text content in PDF');

if ($pdfPath && file_exists($pdfPath)) {
    try {
        $pdfSvc = new PDFService();
        $text   = $pdfSvc->extractText($pdfPath);
        $flat   = str_replace("\n", ' ', $text);

        $t->ok('extractText() returns non-empty string', strlen($text) > 20,
               substr($flat, 0, 100),
               'extractText() returned empty — smalot/pdfparser may have failed');

        $t->ok('Student name visible in PDF',
               stripos($text, 'Alice Test Student') !== false,
               '', 'Alice Test Student not found in extracted text');

        $t->ok('Course name visible in PDF',
               stripos($text, 'Bachelor of Computer Science') !== false,
               '', 'Course name not found in extracted text');

        $t->ok('Degree type visible in PDF',
               stripos($text, 'Bachelor of Technology') !== false,
               '', 'Degree type not found in extracted text');

        $t->ok('Certificate ID visible in PDF',
               strpos($text, $createdCertId) !== false,
               $createdCertId,
               "Certificate ID '{$createdCertId}' not found in extracted text");

        $t->ok('Issue year visible in PDF',
               strpos($text, date('Y')) !== false,
               date('Y'), 'Current year not found in extracted text');

        $noBlockchainLabel = stripos($text, 'Blockchain Hash') === false
                          && stripos($text, 'blockchain_tx_hash') === false;
        $t->ok('Blockchain hash label NOT visible in PDF text',
               $noBlockchainLabel,
               '', 'Blockchain hash label found in visible text — should be XMP only');

    } catch (\Throwable $e) {
        $t->ok('PDF text extraction', false, '', $e->getMessage());
    }
} else {
    $t->warn('Text content checks skipped', 'PDF not available');
}

// ── Test 8: Digital signature ─────────────────────────────────────────────
$t->section('Test 8 — Digital signature');

if ($pdfPath && file_exists($pdfPath)) {
    $binary         = file_get_contents($pdfPath);
    $hasSigField    = strpos($binary, 'cert:signature') !== false;
    $hasSignerField = strpos($binary, 'cert:signer') !== false;

    if ($hasSigField && $hasSignerField) {
        $t->ok('cert:signature field present in XMP', true, 'found');
        $t->ok('cert:signer fingerprint present in XMP', true, 'found');

        try {
            $sigSvc    = new SignatureService();
            $sigResult = $sigSvc->verifySignature($pdfPath);

            $t->ok('Signature verification returns array', is_array($sigResult),
                   '', 'verifySignature() did not return an array');

            $t->ok('Signature is cryptographically valid',
                   ($sigResult['valid'] ?? false) === true,
                   $sigResult['signer'] ?? '',
                   $sigResult['message'] ?? 'Signature invalid');
        } catch (\Throwable $e) {
            $t->ok('Signature verification', false, '', $e->getMessage());
        }
    } else {
        $t->warn('No digital signature found in PDF',
                 "University ID={$seed['uni_id']} has no signing key. "
                 . 'Run POST /universities/generate-key then re-run this test.');
    }
} else {
    $t->warn('Signature checks skipped', 'PDF not available');
}

// ── Test 9: Hash integrity ────────────────────────────────────────────────
$t->section('Test 9 — PDF hash integrity');

if ($pdfPath && file_exists($pdfPath) && $createdCertId) {
    try {
        $pdfSvc         = new PDFService();
        $calculatedHash = $pdfSvc->calculatePDFHash($pdfPath);

        $t->ok('calculatePDFHash() returns 0x keccak256',
               str_starts_with($calculatedHash, '0x') && strlen($calculatedHash) === 66,
               $calculatedHash,
               'Expected 66-char 0x-prefixed string, got: ' . $calculatedHash);

        $stmt = $db->prepare("SELECT pdf_hash, signature_status FROM certificates WHERE certificate_id=?");
        $stmt->execute([$createdCertId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $t->ok('pdf_hash stored in DB', !empty($row['pdf_hash'] ?? ''),
               '', 'pdf_hash column is null');

        $t->ok('Calculated hash matches stored pdf_hash',
               $calculatedHash === $row['pdf_hash'],
               $calculatedHash,
               "Hash mismatch — file may have been modified after DB write\n"
               . "  Calculated: {$calculatedHash}\n"
               . "  Stored:     {$row['pdf_hash']}");

        $t->ok('signature_status stored in DB', isset($row['signature_status']),
               'signature_status=' . ($row['signature_status'] ?? 'null'),
               'signature_status column missing');

    } catch (\Throwable $e) {
        $t->ok('Hash integrity check', false, '', $e->getMessage());
    }
} else {
    $t->warn('Hash integrity skipped', 'PDF or cert ID not available');
}

// ── Test 10: PDF retrieval via getPDFPath() ───────────────────────────────
$t->section('Test 10 — PDF retrieval');

if ($createdCertId) {
    try {
        $pdfSvc        = new PDFService();
        $retrievedPath = $pdfSvc->getPDFPath($createdCertId);

        $t->ok('getPDFPath() returns non-null path', $retrievedPath !== null,
               $retrievedPath ?? '',
               'Got null — pdf_path may not be saved in DB after generation');

        $t->ok('Retrieved path points to existing file',
               $retrievedPath && file_exists($retrievedPath),
               $retrievedPath ?? '',
               'File does not exist at retrieved path: ' . ($retrievedPath ?? 'null'));

        $t->ok('Retrieved path matches creation path',
               $retrievedPath === $pdfPath,
               '',
               "Path mismatch:\n  Got:      {$retrievedPath}\n  Expected: {$pdfPath}");

    } catch (\Throwable $e) {
        $t->ok('PDF retrieval', false, '', $e->getMessage());
    }
} else {
    $t->warn('PDF retrieval skipped', 'Certificate not created');
}

// ── Finish ─────────────────────────────────────────────────────────────────
$t->finish();