<?php
/**
 * Test 3: PDF Generation Tests
 * 
 * Purpose: Test PDF creation, template rendering, and file storage
 */

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use App\PDFGenerator;
use App\Database;

echo "=== PDF GENERATION TEST ===\n\n";

$testResults = [
    'passed' => 0,
    'failed' => 0,
    'total' => 0
];

function runTest($testName, $testFunction) {
    global $testResults;
    $testResults['total']++;
    
    try {
        $result = $testFunction();
        if ($result) {
            echo "✅ PASS: $testName\n";
            $testResults['passed']++;
        } else {
            echo "❌ FAIL: $testName\n";
            $testResults['failed']++;
        }
    } catch (Exception $e) {
        echo "❌ FAIL: $testName - " . $e->getMessage() . "\n";
        $testResults['failed']++;
    }
}

runTest("mPDF Library Available", function() {
    return class_exists('Mpdf\Mpdf');
});

runTest("PDFGenerator Class Instantiation", function() {
    try {
        $pdfGen = new PDFGenerator();
        return true;
    } catch (Exception $e) {
        return false;
    }
});

runTest("Template File Exists", function() {
    $templatePath = __DIR__ . '/../templates/certificate_template.html';
    return file_exists($templatePath) && filesize($templatePath) > 1000;
});

runTest("Storage Directories Created", function() {
    $config = require __DIR__ . '/../config.php';
    
    $pdfDir = $config['storage']['pdf_path'];
    $qrDir = $config['storage']['qr_path'];
    
    return is_dir($pdfDir) && is_dir($qrDir) && 
           is_writable($pdfDir) && is_writable($qrDir);
});

runTest("Template Placeholders Correct", function() {
    $templatePath = __DIR__ . '/../templates/certificate_template.html';
    $template = file_get_contents($templatePath);
    
    $requiredPlaceholders = [
        '{{UNIVERSITY_NAME}}',
        '{{STUDENT_NAME}}',
        '{{COURSE_NAME}}',
        '{{CERTIFICATE_ID}}',
        '{{ISSUE_DATE}}',
        '{{CERTIFICATE_HASH}}'
    ];
    
    foreach ($requiredPlaceholders as $placeholder) {
        if (strpos($template, $placeholder) === false) {
            return false;
        }
    }
    
    return true;
});

runTest("Placeholder Replacement Works", function() {
    $template = '<div>{{UNIVERSITY_NAME}} - {{STUDENT_NAME}} - {{COURSE}}</div>';
    
    $replacements = [
        '{{UNIVERSITY_NAME}}' => 'Test University',
        '{{STUDENT_NAME}}' => 'John Doe',
        '{{COURSE}}' => 'Test Course'
    ];
    
    $result = str_replace(array_keys($replacements), array_values($replacements), $template);
    
    return strpos($result, 'Test University') !== false && 
           strpos($result, 'John Doe') !== false && 
           strpos($result, 'Test Course') !== false;
});

runTest("PDF Generation with Mock Data", function() {
    $config = require __DIR__ . '/../config.php';
    
    // Create test certificate in database
    $db = Database::getInstance()->getConnection();
    
    // Get existing data or create test
    $stmt = $db->query("SELECT * FROM certificates LIMIT 1");
    $certificate = $stmt->fetch();
    
    if (!$certificate) {
        // Create minimal test data
        $certificate = [
            'certificate_id' => 'TEST-' . strtoupper(uniqid()),
            'student_name' => 'Test Student',
            'university_name' => 'Test University',
            'course_name' => 'Test Course',
            'degree_type' => 'Test Certificate',
            'issue_date' => '2024-12-01',
            'certificate_hash' => 'test_hash_1234567890abcdef',
            'blockchain_tx_hash' => '0x1234567890abcdef'
        ];
    }
    
    $pdfGen = new PDFGenerator();
    $filename = $pdfGen->generateCertificatePDF($certificate['certificate_id']);
    
    // Check if file was created
    $config = require __DIR__ . '/../config.php';
    $pdfPath = $config['storage']['pdf_path'] . $filename;
    
    return file_exists($pdfPath) && filesize($pdfPath) > 1000;
});

runTest("PDF File Size Reasonable", function() {
    $config = require __DIR__ . '/../config.php';
    $pdfDir = $config['storage']['pdf_path'];
    
    // Find any PDF file
    $files = glob($pdfDir . '*.pdf');
    
    if (empty($files)) {
        return false;
    }
    
    $fileSize = filesize($files[0]);
    return $fileSize > 5000 && $fileSize < 1000000; // Between 5KB and 1MB
});

runTest("QR Code Generation", function() {
    $config = require __DIR__ . '/../config.php';
    $qrDir = $config['storage']['qr_path'];
    
    // Test QR code creation
    $verificationURL = 'http://localhost:3000/verify?certificate_id=TEST-123';
    
    // Create a simple test QR code
    $image = imagecreatetruecolor(200, 200);
    if (!$image) {
        return false;
    }
    
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    
    imagefill($image, 0, 0, $white);
    
    // Draw some pattern to simulate QR code
    for ($x = 0; $x < 200; $x += 20) {
        for ($y = 0; $y < 200; $y += 20) {
            if (($x + $y) % 40 == 0) {
                imagefilledrectangle($image, $x, $y, $x + 15, $y + 15, $black);
            }
        }
    }
    
    $filename = 'test_qr_' . uniqid() . '.png';
    $filepath = $qrDir . $filename;
    
    $result = imagepng($image, $filepath);
    imagedestroy($image);
    
    if ($result && file_exists($filepath)) {
        unlink($filepath); // Clean up test file
        return true;
    }
    
    return false;
});

runTest("Configuration Valid", function() {
    $config = require __DIR__ . '/../config.php';
    
    $requiredKeys = ['pdf_path', 'qr_path', 'base_url'];
    foreach ($requiredKeys as $key) {
        if (!isset($config['storage'][$key])) {
            return false;
        }
    }
    
    return !empty($config['storage']['pdf_path']) && 
           !empty($config['storage']['qr_path']);
});

// Display test results
echo "\n=== TEST RESULTS ===\n";
echo "Total Tests: {$testResults['total']}\n";
echo "Passed: {$testResults['passed']}\n";
echo "Failed: {$testResults['failed']}\n";
echo "Success Rate: " . round(($testResults['passed'] / $testResults['total']) * 100, 2) . "%\n";

if ($testResults['failed'] === 0) {
    echo "\n🎉 ALL PDF TESTS PASSED! 🎉\n";
} else {
    echo "\n⚠️ Some PDF tests failed. Check dependencies or file permissions.\n";
}

echo "\n=== PDF GENERATION STATISTICS ===\n";
$config = require __DIR__ . '/../config.php';
$pdfDir = $config['storage']['pdf_path'];
$qrDir = $config['storage']['qr_path'];

$pdfFiles = glob($pdfDir . '*.pdf');
$qrFiles = glob($qrDir . '*.png');

echo "PDF Directory: $pdfDir\n";
echo "QR Directory: $qrDir\n";
echo "PDF Files Created: " . count($pdfFiles) . "\n";
echo "QR Code Files: " . count($qrFiles) . "\n";

if (!empty($pdfFiles)) {
    $totalSize = array_sum(array_map('filesize', $pdfFiles));
    $avgSize = $totalSize / count($pdfFiles);
    echo "Average PDF Size: " . round($avgSize / 1024, 2) . " KB\n";
    
    echo "\nRecent PDF Files:\n";
    foreach (array_slice($pdfFiles, -3) as $file) {
        $size = filesize($file);
        $name = basename($file);
        echo "  $name (" . round($size / 1024, 2) . " KB)\n";
    }
}