<?php
/**
 * Simple Sequential Seeding Script  
 * Creates 5 universities, students, and certificates via production API
 */

$baseUrl = 'https://blockchain-based-certificate-verification-system-production.up.railway.app/api';
$adminEmail = 'admin@certificate-system.com';
$adminPassword = 'admin123';

echo "=== PRODUCTION DATABASE SEEDING ===\n\n";

// Step 1: Login
echo "[Step 1] Logging in as admin...\n";
$loginData = [
    'email' => $adminEmail,
    'password' => $adminPassword
];

$response = httpPost("$baseUrl/auth/login", $loginData);
if (!isset($response['token'])) {
    die("❌ Login failed: " . json_encode($response) . "\n");
}
$adminToken = $response['token'];
echo "✅ Admin authenticated\n\n";

// Universities to create
$universities = [
    ['name' => 'University of Technology', 'code' => 'UOT' . substr(uniqid(), -4), 'email' => 'admin@uot.edu'],
    ['name' => 'Harvard University', 'code' => 'HARV' . substr(uniqid(), -4), 'email' => 'admin@harvard.edu'],
    ['name' => 'MIT', 'code' => 'MIT' . substr(uniqid(), -4), 'email' => 'admin@mit.edu'],
    ['name' => 'Stanford University', 'code' => 'STAN' . substr(uniqid(), -4), 'email' => 'admin@stanford.edu'],
    ['name' => 'Berkeley University', 'code' => 'BERK' . substr(uniqid(), -4), 'email' => 'admin@berkeley.edu'],
    ['name' => 'Yale University', 'code' => 'YALE' . substr(uniqid(), -4), 'email' => 'admin@yale.edu'],
];

$allData = [];

// Step 2: Create 5 universities
echo "[Step 2] Creating 5 universities...\n";
foreach ($universities as $index => $uni) {
    echo "  Creating {$uni['name']} ({$uni['code']})...\n";
    
    $createData = [
        'name' => $uni['name'],
        'code' => $uni['code'],
        'address' => $uni['name'] . ', USA',
        'contact_email' => $uni['email'],
        'contact_phone' => '123-456-7890'
    ];
    
    $response = httpPost("$baseUrl/universities", $createData, $adminToken);
    if (!($response['success'] ?? false)) {
        die("    ❌ Failed to create university: " . json_encode($response) . "\n");
    }
    
    // Get the ID by fetching all universities
    $unis = httpGet("$baseUrl/universities");
    $uniId = null;
    foreach ($unis['universities'] as $u) {
        if ($u['code'] === $uni['code']) {
            $uniId = $u['id'];
            break;
        }
    }
    
    if (!$uniId) {
        die("    ❌ Could not find created university: {$uni['code']}\n");
    }
    
    echo "    ✅ University created (ID: $uniId)\n";
   
    // Generate key
    echo "  Generating signing key for university {$uniId}...\n";
    $keyResponse = httpPost("$baseUrl/universities/generate-key", ['university_id' => $uniId], $adminToken);
    if (!($keyResponse['success'] ?? false)) {
        die("    ❌ Failed to generate key: " . json_encode($keyResponse) . "\n");
    }
    echo "    ✅ Key generated\n";
    
    // Create student
    echo "  Creating student for university {$uniId}...\n";
    $studentData = [
        'username' => 'stu' . uniqid(),
        'email' => 'student.1@' . strtolower($uni['code']) . '.edu',
        'password' => 'StudentPass@123',
        'full_name' => 'Student 1',
        'student_id' => 'STU-' . $uni['code'] . '-001',
        'university_id' => $uniId,
        'enrollment_date' => date('Y-m-d')
    ];
    
    $studentResponse = httpPost("$baseUrl/students", $studentData, $adminToken);
    
    if (!($studentResponse['success'] ?? false)) {
        die("    ❌ Failed to create student: " . json_encode($studentResponse) . "\n");
    }
    echo "    ✅ Student created\n";
    
    // Get student database ID by querying all students for this university
    echo "  Fetching student database ID...\n";
    $studentsResponse = httpGet("$baseUrl/students", $adminToken);
    $studentDbId = null;
    if (isset($studentsResponse['students'])) {
        foreach ($studentsResponse['students'] as $s) {
            if ($s['student_id'] === $studentData['student_id'] && $s['university_id'] === $uniId) {
                $studentDbId = $s['id'] ?? null;  // Use 'id' if available
                break;
            }
        }
    }
    
    if (!$studentDbId) {
        // Try using the student_id as fallback (might be the database ID)
        $studentDbId = null;
        echo "    ⚠️  Could not find student database ID, trying alternative methods...\n";
    }
    
    // Create certificate using the database student ID
    echo "  Creating certificate (student DB ID: $studentDbId)...\n";
    $certData = [
        'student_id' => $studentDbId ?? $studentData['student_id'],  // Fallback to custom ID
        'university_id' => $uniId,
        'course_name' => $uni['name'] . ' Bachelor Program',
        'degree_type' => 'Bachelor of Science',
        'issue_date' => date('Y-m-d')
    ];
    
    $certResponse = httpPost("$baseUrl/certificates/create", $certData, $adminToken);
    if (!($certResponse['success'] ?? false)) {
        die("    ❌ Failed to create certificate: " . json_encode($certResponse) . "\n");
    }
    $certId = $certResponse['certificate_id'] ?? 'UNKNOWN';
    echo "    ✅ Certificate created (ID: $certId)\n\n";
    
    $allData[] = [
        'university' => $uni,
        'university_id' => $uniId,
        'student_id' => $studentData['student_id'],
        'certificate_id' => $certId
    ];
}

echo "\n✅ All sampledata created successfully!\n\n";
echo "Summary:\n";
foreach ($allData as $data) {
    echo "- {$data['university']['name']}: Uni#{$data['university_id']}, Student={$data['student_id']}, Cert={$data['certificate_id']}\n";
}

function httpGet($url, $token = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return json_decode($response, true) ?? ['error' => "HTTP $code: $response"];
}

function httpPost($url, $data, $token = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($errno) {
        return ['error' => "cURL Error: $error"];
    }
    
    if ($code >= 500) {
        return ['error' => "HTTP $code", 'response' => $response];
    }
    
    return json_decode($response, true) ?? ['error' => 'No JSON response'];
}
