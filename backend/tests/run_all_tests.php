<?php
/**
 * Test Runner - Execute All Tests
 * 
 * Purpose: Run all system tests and generate comprehensive report
 */

echo "=== CERTIFICATE VERIFICATION SYSTEM - COMPLETE TEST SUITE ===\n\n";

$tests = [
    'Database Connection & Schema' => __DIR__ . '/test_database.php',
    'Blockchain Integration' => __DIR__ . '/test_blockchain.php',
    'PDF Generation' => __DIR__ . '/test_pdf_generation.php',
    'Authentication System' => __DIR__ . '/test_authentication.php',
    'Certificate Service' => __DIR__ . '/test_certificate_service.php',
    'API Endpoints' => __DIR__ . '/test_api_endpoints.php'
];

$results = [];
$overallPassed = 0;
$overallFailed = 0;
$overallTotal = 0;

foreach ($tests as $testName => $testFile) {
    if (!file_exists($testFile)) {
        echo "❌ Test file not found: $testFile\n";
        continue;
    }
    
    echo "🧪 Running: $testName\n";
    echo "File: $testFile\n";
    echo str_repeat("=", 50) . "\n";
    
    $startTime = microtime(true);
    
    // Capture output
    ob_start();
    include $testFile;
    $output = ob_get_clean();
    
    $endTime = microtime(true);
    $duration = round(($endTime - $startTime), 2);
    
    // Parse results from output
    $passed = preg_match('/Passed:\s*(\d+)/', $output, $matches) ? (int)$matches[1] : 0;
    $failed = preg_match('/Failed:\s*(\d+)/', $output, $matches) ? (int)$matches[1] : 0;
    $total = preg_match('/Total Tests:\s*(\d+)/', $output, $matches) ? (int)$matches[1] : 0;
    
    $results[$testName] = [
        'passed' => $passed,
        'failed' => $failed,
        'total' => $total,
        'duration' => $duration,
        'success_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0
    ];
    
    $overallPassed += $passed;
    $overallFailed += $failed;
    $overallTotal += $total;
    
    echo "\n⏱️  Duration: {$duration} seconds\n";
    echo str_repeat("=", 50) . "\n\n";
}

// Generate comprehensive report
echo "=== COMPREHENSIVE TEST REPORT ===\n\n";

echo "📊 OVERALL STATISTICS:\n";
echo "Total Test Suites: " . count($tests) . "\n";
echo "Total Individual Tests: $overallTotal\n";
echo "Total Passed: $overallPassed\n";
echo "Total Failed: $overallFailed\n";
$overallSuccessRate = $overallTotal > 0 ? round(($overallPassed / $overallTotal) * 100, 2) : 0;
echo "Overall Success Rate: $overallSuccessRate%\n\n";

echo "📋 TEST SUITE BREAKDOWN:\n";
foreach ($results as $testName => $result) {
    $status = $result['failed'] === 0 ? '✅ PASS' : '❌ FAIL';
    $rate = $result['success_rate'];
    $time = $result['duration'];
    
    printf("%-35s %s %3d%% (%3.1fs) [%3d/%3d]\n", 
        $testName, $status, $rate, $time, $result['passed'], $result['total']);
}

echo "\n🎯 SYSTEM COMPONENTS STATUS:\n";

foreach ($results as $testName => $result) {
    $componentStatus = $result['failed'] === 0 ? 'OPERATIONAL' : 'NEEDS ATTENTION';
    $icon = $result['failed'] === 0 ? '🟢' : '🔴';
    
    echo "$icon $testName: $componentStatus\n";
}

echo "\n📋 DETAILED RESULTS:\n";
foreach ($results as $testName => $result) {
    echo "\n" . strtoupper($testName) . ":\n";
    echo "  Tests Run: {$result['total']}\n";
    echo "  Passed: {$result['passed']}\n";
    echo "  Failed: {$result['failed']}\n";
    echo "  Success Rate: {$result['success_rate']}%\n";
    echo "  Duration: {$result['duration']} seconds\n";
    
    if ($result['failed'] > 0) {
        echo "  Status: ⚠️  ISSUES DETECTED\n";
    } else {
        echo "  Status: ✅ ALL TESTS PASSED\n";
    }
}

// Recommendations
echo "\n💡 RECOMMENDATIONS:\n";

if ($overallSuccessRate >= 95) {
    echo "🎉 EXCELLENT: System is performing optimally!\n";
    echo "   • All critical components are working\n";
    echo "   • System is ready for production deployment\n";
} elseif ($overallSuccessRate >= 80) {
    echo "✅ GOOD: System is mostly functional\n";
    echo "   • Minor issues detected but core functionality works\n";
    echo "   • Review failed tests for improvements\n";
} else {
    echo "⚠️  NEEDS ATTENTION: System has significant issues\n";
    echo "   • Multiple components not working correctly\n";
    echo "   • Critical issues need immediate resolution\n";
    echo "   • Review failed tests and fix core problems\n";
}

// Critical components check
$criticalComponents = ['Database Connection & Schema', 'Authentication System'];
$criticalPassed = 0;
$criticalTotal = 0;

foreach ($criticalComponents as $component) {
    if (isset($results[$component])) {
        $criticalTotal++;
        if ($results[$component]['failed'] === 0) {
            $criticalPassed++;
        }
    }
}

if ($criticalPassed === $criticalTotal && $criticalTotal > 0) {
    echo "\n🔒 CRITICAL COMPONENTS: All operational\n";
} elseif ($criticalTotal > 0) {
    echo "\n⚠️  CRITICAL COMPONENTS: Some issues detected\n";
}

// Next steps
echo "\n🚀 NEXT STEPS:\n";
echo "1. Run individual test files for detailed error messages\n";
echo "2. Fix any failed tests before production deployment\n";
echo "3. Ensure backend server is running for API tests\n";
echo "4. Verify Ganache blockchain is running for blockchain tests\n";
echo "5. Check database connection if database tests fail\n";
echo "6. Verify mPDF installation if PDF tests fail\n\n";

echo "=== TEST SUITE COMPLETED ===\n";
echo "Generated at: " . date('Y-m-d H:i:s') . "\n";
echo "Total execution time: " . round(array_sum(array_column($results, 'duration')), 2) . " seconds\n";