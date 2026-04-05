<?php

// Load composer autoloader first
require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap phpdotenv (safeLoad does not throw if .env is missing)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

header('Content-Type: application/json');

// Prevent PHP from timing out during slow blockchain transactions
set_time_limit(0);

// CORS Configuration
$allowedOrigins = [
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost',
    'http://localhost:8000',
    'http://127.0.0.1',
    'http://127.0.0.1:8000',
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    getenv('FRONTEND_URL') ?: '',
];

// Remove empty strings from array
$allowedOrigins = array_values(array_filter($allowedOrigins));

$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$appEnv = getenv('APP_ENV') ?: 'production';
$allowedOriginsList = getenv('ALLOWED_ORIGINS') ? explode(',', getenv('ALLOWED_ORIGINS')) : $allowedOrigins;

// Trim whitespace from allowed origins
$allowedOriginsList = array_map('trim', $allowedOriginsList);

$isLocalhost = preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $requestOrigin);

// In development, allow all origins.
if ($appEnv === 'local' || $appEnv === 'development') {
    header('Access-Control-Allow-Origin: *');
} elseif (empty($requestOrigin)) {
    // No origin header (e.g., Postman, curl, server-to-server) - allow
    header('Access-Control-Allow-Origin: *');
} elseif ($isLocalhost) {
    // Always allow localhost in any environment (for local development)
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
} elseif (in_array($requestOrigin, $allowedOriginsList, true)) {
    // Request has valid origin and it's whitelisted
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
} else {
    // Request has origin but not whitelisted - allow first in list
    header('Access-Control-Allow-Origin: ' . (reset($allowedOriginsList) ?: '*'));
}

header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');

if (extension_loaded('zlib')) {
    ini_set('zlib.output_compression', 4096);
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}



use App\Auth;
use App\CertificateService;
use App\Database;
use App\EmailService;
use App\FileService;
use App\PublicVerificationService;
use App\SignatureService;
use App\StudentService;
use App\UniversityService;

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($path, PHP_URL_PATH);
$path = str_replace('/api', '', $path);

$auth = new Auth();
$certService = new CertificateService();
$signatureService = new SignatureService();
$studentService = new StudentService();
$universityService = new UniversityService();

// Extract token from Authorization header
$token = null;

// Try multiple methods to get Authorization header (robust across different server configs)
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
} elseif (isset($_SERVER['CONTENT_TYPE']) && function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    } elseif (isset($headers['authorization'])) {
        $token = str_replace('Bearer ', '', $headers['authorization']);
    }
} else {
    // Fallback: check if running on Apache with mod_php
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    }
}

function requireAuth($token, $auth, $allowedRoles = []) {
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Unauthorized',
            'debug' => getenv('APP_DEBUG') === 'true' ? 'No token provided' : null
        ]);
        exit;
    }

    $payload = $auth->verifyToken($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Invalid token',
            'debug' => getenv('APP_DEBUG') === 'true' ? 'Token verification failed' : null
        ]);
        exit;
    }

    if (!empty($allowedRoles) && !in_array($payload['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    return $payload;
}

function validatePasswordStrength(string $password): ?string {
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
        return 'Password must be at least 8 characters and contain both letters and numbers';
    }
    return null;
}

/** Unique username for new users (university self-registration, etc.). */
function allocateUniqueUsername(\PDO $db, string $email): string {
    $local = strstr(strtolower(trim($email)), '@', true) ?: 'univ';
    $local = preg_replace('/[^a-z0-9_]/', '_', $local);
    $local = trim($local, '_') ?: 'univ';
    $base = substr($local, 0, 80);
    $username = $base;
    $n = 0;
    while (true) {
        $chk = $db->prepare('SELECT 1 FROM users WHERE username = ?');
        $chk->execute([$username]);
        if (!$chk->fetch()) {
            return $username;
        }
        $n++;
        $username = $base . '_' . $n;
    }
}

// Route handling

// ─── Debug endpoint (shows what headers are received) ─────────────────────
if ($path === '/debug/headers' && $method === 'GET') {
    echo json_encode([
        'php_version' => phpversion(),
        'headers_received' => [
            'Authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET',
            'All $_SERVER HTTP_* vars' => array_filter($_SERVER, function($k) { return strpos($k, 'HTTP_') === 0; }, ARRAY_FILTER_USE_KEY)
        ],
        'token_extracted' => $token ? 'YES (length: ' . strlen($token) . ')' : 'NO',
        'env_debug' => getenv('APP_DEBUG')
    ], JSON_PRETTY_PRINT);
    exit;
}

// ─── Dynamic-path routes (must be checked before the switch) ─────────────────

// GET /students/:id
if ($method === 'GET' && preg_match('#^/students/(\d+)$#', $path, $m)) {
    $user = requireAuth($token, $auth, ['admin', 'university', 'student']);
    $studentId = (int)$m[1];
    // Admins can view inactive students; others only see active ones
    $includeInactive = ($user['role'] === 'admin');
    $student = $studentService->getStudentById($studentId, $includeInactive);
    if (!$student) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit;
    }
    if (!$studentService->checkStudentAuthorization(
        $user['user_id'], $studentId, 'view', $user['role'], $user['university_id'] ?? null
    )) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $student]);
    exit;
}

// PUT /students/:id
if ($method === 'PUT' && preg_match('#^/students/(\d+)$#', $path, $m)) {
    $user = requireAuth($token, $auth, ['admin', 'student']);
    $studentId = (int)$m[1];
    // Check existence first so callers get 404, not 403, for unknown IDs
    $student = $studentService->getStudentById($studentId, $user['role'] === 'admin');
    if (!$student) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit;
    }
    if (!$studentService->checkStudentAuthorization(
        $user['user_id'], $studentId, 'update', $user['role'], $user['university_id'] ?? null
    )) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!isset($data['full_name']) && !isset($data['date_of_birth'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No updatable fields provided']);
        exit;
    }
    // Validate date_of_birth format if provided
    if (isset($data['date_of_birth']) && trim($data['date_of_birth']) !== '') {
        if (!\App\StudentService::isValidDate($data['date_of_birth'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid date_of_birth format, expected Y-m-d']);
            exit;
        }
    }
    $result = $studentService->updateStudent($studentId, $data);
    if ($result === false) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
    exit;
}

// DELETE /students/:id
if ($method === 'DELETE' && preg_match('#^/students/(\d+)$#', $path, $m)) {
    $user = requireAuth($token, $auth, ['admin']);
    $studentId = (int)$m[1];
    $student = $studentService->getStudentById($studentId, true);
    if (!$student) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit;
    }
    $studentService->softDeleteStudent($studentId);
    echo json_encode(['success' => true, 'message' => 'Student deactivated successfully']);
    exit;
}

// GET /students/:id/certificates
if ($method === 'GET' && preg_match('#^/students/(\d+)/certificates$#', $path, $m)) {
    $user = requireAuth($token, $auth, ['admin', 'university', 'student']);
    $studentId = (int)$m[1];
    // Check existence first; non-admins only see active students
    $includeInactive = ($user['role'] === 'admin');
    $student = $studentService->getStudentById($studentId, $includeInactive);
    if (!$student) {
        http_response_code(404);
        echo json_encode(['error' => 'Student not found']);
        exit;
    }
    if (!$studentService->checkStudentAuthorization(
        $user['user_id'], $studentId, 'certificates', $user['role'], $user['university_id'] ?? null
    )) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $filters = [
        'status'          => $_GET['status']          ?? '',
        'course_name'     => $_GET['course_name']     ?? '',
        'issue_date_from' => $_GET['issue_date_from'] ?? '',
        'issue_date_to'   => $_GET['issue_date_to']   ?? '',
        'sort'            => $_GET['sort']             ?? '',
        'order'           => $_GET['order']            ?? '',
    ];
    $page  = max(1, (int)($_GET['page']  ?? 1));
    $limit = min(100, (int)($_GET['limit'] ?? 10));
    $result = $studentService->getStudentCertificates($studentId, $filters, $page, $limit);
    echo json_encode(['success' => true] + $result);
    exit;
}

// GET /universities/:id
if ($method === 'GET' && preg_match('#^/universities/(\d+)$#', $path, $m)) {
    $universityId = (int)$m[1];
    // Public endpoint — hide inactive universities (only admin should see inactive via other routes)
    $university = $universityService->getUniversity($universityId, false);
    if (!$university) {
        http_response_code(404);
        echo json_encode(['error' => 'University not found']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $university]);
    exit;
}

// PUT /universities/:id
if ($method === 'PUT' && preg_match('#^/universities/(\d+)$#', $path, $m)) {
    $user = requireAuth($token, $auth, ['admin', 'university']);
    $universityId = (int)$m[1];
    // Check existence first so callers get 404, not 403, for unknown IDs.
    // Admins can update inactive universities; others cannot.
    $university = $universityService->getUniversity($universityId, $user['role'] === 'admin');
    if (!$university) {
        http_response_code(404);
        echo json_encode(['error' => 'University not found']);
        exit;
    }
    if (!$universityService->checkUniversityAuthorization($universityId, 'update', $user['role'], $user['university_id'] ?? null)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $result = $universityService->updateUniversity($universityId, $data);
    if ($result === false) {
        http_response_code(400);
        echo json_encode(['error' => 'No updatable fields provided or no changes detected']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'University updated successfully']);
    exit;
}

// DELETE /universities/:id
if ($method === 'DELETE' && preg_match('#^/universities/(\d+)$#', $path, $m)) {
    $user = requireAuth($token, $auth, ['admin']);
    $universityId = (int)$m[1];
    $university = $universityService->getUniversity($universityId, true);
    if (!$university) {
        http_response_code(404);
        echo json_encode(['error' => 'University not found']);
        exit;
    }
    $universityService->deactivateUniversity($universityId);
    echo json_encode(['success' => true, 'message' => 'University deactivated successfully']);
    exit;
}

// GET /universities/:id/students
if ($method === 'GET' && preg_match('#^/universities/(\d+)/students$#', $path, $m)) {
    $user = requireAuth($token, $auth, ['admin', 'university']);
    $universityId = (int)$m[1];
    // Admins may access deactivated universities; others cannot
    $university = $universityService->getUniversity($universityId, $user['role'] === 'admin');
    if (!$university) {
        http_response_code(404);
        echo json_encode(['error' => 'University not found']);
        exit;
    }
    if (!$universityService->checkUniversityAuthorization($universityId, 'students', $user['role'], $user['university_id'] ?? null)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $filters = [
        'enrollment_date_from' => $_GET['enrollment_date_from'] ?? '',
        'enrollment_date_to'   => $_GET['enrollment_date_to']   ?? '',
        'sort'                 => $_GET['sort']                 ?? '',
        'order'                => $_GET['order']                ?? '',
    ];
    // Only admins may request inactive students; others always see active-only
    if ($user['role'] === 'admin') {
        $filters['is_active'] = $_GET['is_active'] ?? '';
    } else {
        $filters['is_active'] = 'true';
    }
    $page  = max(1, (int)($_GET['page']  ?? 1));
    $limit = min(100, (int)($_GET['limit'] ?? 10));
    $result = $universityService->getUniversityStudents($universityId, $filters, $page, $limit);
    echo json_encode(['success' => true] + $result);
    exit;
}

// GET /universities/:id/certificates
if ($method === 'GET' && preg_match('#^/universities/(\d+)/certificates$#', $path, $m)) {
    $user = requireAuth($token, $auth, ['admin', 'university']);
    $universityId = (int)$m[1];
    // Admins may access deactivated universities; others cannot
    $university = $universityService->getUniversity($universityId, $user['role'] === 'admin');
    if (!$university) {
        http_response_code(404);
        echo json_encode(['error' => 'University not found']);
        exit;
    }
    if (!$universityService->checkUniversityAuthorization($universityId, 'certificates', $user['role'], $user['university_id'] ?? null)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $filters = [
        'status'          => $_GET['status']          ?? '',
        'course_name'     => $_GET['course_name']     ?? '',
        'issue_date_from' => $_GET['issue_date_from'] ?? '',
        'issue_date_to'   => $_GET['issue_date_to']   ?? '',
        'sort'            => $_GET['sort']             ?? '',
        'order'           => $_GET['order']            ?? '',
    ];
    $page  = max(1, (int)($_GET['page']  ?? 1));
    $limit = min(100, (int)($_GET['limit'] ?? 10));
    $result = $universityService->getUniversityCertificates($universityId, $filters, $page, $limit);
    echo json_encode(['success' => true] + $result);
    exit;
}

// GET /universities/:id/stats
if ($method === 'GET' && preg_match('#^/universities/(\d+)/stats$#', $path, $m)) {
    $user = requireAuth($token, $auth, ['admin', 'university']);
    $universityId = (int)$m[1];
    $university = $universityService->getUniversity($universityId, $user['role'] === 'admin');
    if (!$university) {
        http_response_code(404);
        echo json_encode(['error' => 'University not found']);
        exit;
    }
    if (!$universityService->checkUniversityAuthorization($universityId, 'stats', $user['role'], $user['university_id'] ?? null)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $stats = $universityService->getUniversityStats($universityId);
    echo json_encode(['success' => true, 'data' => $stats]);
    exit;
}

// ─── University Auth routes (users.role = 'university') ─────────────────────

const UNIVERSITY_CODE_MAX_LENGTH = 8;

// POST /auth/university/register — create a new university + admin account
if ($method === 'POST' && $path === '/auth/university/register') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // Required field validation
    $required = ['university_name', 'university_email', 'university_phone', 'university_address',
                 'admin_name', 'admin_email', 'admin_password'];
    foreach ($required as $field) {
        if (empty(trim($data[$field] ?? ''))) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            exit;
        }
    }

    // Email format validation
    if (!filter_var($data['university_email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid university email format']);
        exit;
    }
    if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid admin email format']);
        exit;
    }

    // Password strength validation
    $pwError = validatePasswordStrength($data['admin_password']);
    if ($pwError) {
        http_response_code(400);
        echo json_encode(['error' => $pwError]);
        exit;
    }

    $db = Database::getInstance()->getConnection();

    $adminEmail = strtolower(trim($data['admin_email']));

    $stmt = $db->prepare('SELECT id FROM users WHERE LOWER(TRIM(email)) = ?');
    $stmt->execute([$adminEmail]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'An account with this admin email already exists']);
        exit;
    }

    // Check for duplicate university email in universities
    $stmt = $db->prepare("SELECT id FROM universities WHERE contact_email = ?");
    $stmt->execute([strtolower(trim($data['university_email']))]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'A university with this email already exists']);
        exit;
    }

    try {
        $db->beginTransaction();

        // Generate a unique university code from the name
        $baseCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($data['university_name'], 0, UNIVERSITY_CODE_MAX_LENGTH)));
        $code = $baseCode;
        $suffix = 1;
        while (true) {
            $stmt = $db->prepare("SELECT id FROM universities WHERE code = ?");
            $stmt->execute([$code]);
            if (!$stmt->fetch()) break;
            $code = $baseCode . $suffix++;
        }

        // Insert university
        $stmt = $db->prepare("
            INSERT INTO universities (name, code, address, contact_email, contact_phone)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($data['university_name']),
            $code,
            trim($data['university_address']),
            strtolower(trim($data['university_email'])),
            trim($data['university_phone'])
        ]);
        $universityId = (int)$db->lastInsertId();

        $passwordHash = password_hash($data['admin_password'], PASSWORD_DEFAULT);
        $username = allocateUniqueUsername($db, $adminEmail);
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, role, full_name, university_id)
            VALUES (?, ?, ?, 'university', ?, ?)
        ");
        $stmt->execute([
            $username,
            $adminEmail,
            $passwordHash,
            trim($data['admin_name']),
            $universityId,
        ]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'university_id' => $universityId,
            'message' => 'University registered successfully'
        ]);
    } catch (\Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Registration failed. Please try again.']);
    }
    exit;
}

// POST /auth/verify-token — verify a JWT and return its payload
if ($method === 'POST' && $path === '/auth/verify-token') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $tokenToVerify = $data['token'] ?? '';

    if (empty($tokenToVerify)) {
        http_response_code(400);
        echo json_encode(['error' => 'Token is required']);
        exit;
    }

    $payload = $auth->verifyToken($tokenToVerify);
    if (!$payload) {
        echo json_encode(['valid' => false, 'error' => 'Token is invalid or expired']);
        exit;
    }

    echo json_encode([
        'valid'   => true,
        'user_id' => $payload['user_id'],
        'email'   => $payload['email'],
        'role'    => $payload['role'],
        'university_id'   => $payload['university_id'] ?? null,
        'admin_name'      => $payload['admin_name'] ?? null,
        'university_name' => $payload['university_name'] ?? null,
        'expires_at'      => $payload['exp']
    ]);
    exit;
}

// ─── Static routes via switch ─────────────────────────────────────────────────
switch ($path) {
    case '/auth/login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = $auth->login($data['email'] ?? '', $data['password'] ?? '');
            
            if ($user) {
                $token = $auth->generateToken($user);
                $response = ['success' => true, 'token' => $token, 'user' => $user];
                
                // If user is university role, include university and admin details
                if (($user['role'] ?? '') === 'university') {
                    $response['university'] = [
                        'id'      => (int) $user['university_id'],
                        'name'    => $user['university_name'] ?? null,
                        'email'   => $user['university_contact_email'] ?? null,
                        'phone'   => $user['university_contact_phone'] ?? null,
                        'address' => $user['university_address'] ?? null,
                    ];
                    
                    $response['admin'] = [
                        'id'    => (int) $user['id'],
                        'name'  => $user['full_name'],
                        'email' => $user['email'],
                        'role'  => 'university',
                    ];
                }
                
                echo json_encode($response);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
            }
        }
        break;

    case '/auth/register':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $auth->register($data);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'User registered successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Registration failed']);
            }
        }
        break;

    case '/certificates/create':
        if ($method === 'POST') {
            $user = requireAuth($token, $auth, ['university', 'admin']);
            $data = json_decode(file_get_contents('php://input'), true);
            $data['university_id'] = $user['university_id'] ?? $data['university_id'];
            
            $result = $certService->createCertificate($data);
            echo json_encode($result);
        }
        break;

    case '/certificates/upload':
        if ($method === 'POST') {
            $user = requireAuth($token, $auth, ['university', 'admin']);
            
            if (!isset($_FILES['certificate']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
                break;
            }
            
            $universityId = $user['university_id'] ?? null;
            if (!$universityId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'University ID required']);
                break;
            }
            
            $result = $certService->uploadCertificate($_FILES['certificate'], $universityId);
            echo json_encode($result);
        }
        break;

    case '/certificates/verify':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Check if PDF file is uploaded
            if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
                // Verify uploaded PDF
                $result = $certService->verifyUploadedPDF($_FILES['certificate']);
            } else {
                // Verify by certificate ID
                $result = $certService->verifyCertificate(
                    $data['certificate_id'] ?? '',
                    $data['certificate_hash'] ?? null
                );
            }
            
            echo json_encode($result);
        }
        break;

    // Public verification endpoint (no authentication required)
    case '/public/verify':
        if ($method === 'GET' || $method === 'POST') {
            $publicVerification = new PublicVerificationService();
            
            // Get certificate ID from query, POST data, or JSON body
            $certificateId = $_GET['certificate_id'] ?? 
                           ($_POST['certificate_id'] ?? 
                           (json_decode(file_get_contents('php://input'), true)['certificate_id'] ?? null));
            
            // Check for uploaded file
            $uploadedFile = null;
            if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['certificate'];
            }
            
            // Perform verification
            $result = $publicVerification->verifyPublic($certificateId, $uploadedFile);
            
            echo json_encode($result, JSON_PRETTY_PRINT);
        }
        break;

    // Public certificate download endpoint
    case '/public/certificate/download':
        if ($method === 'GET') {
            $certificateId = $_GET['certificate_id'] ?? '';
            
            if (empty($certificateId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Certificate ID required']);
                exit;
            }
            
            try {
                $publicVerification = new PublicVerificationService();
                $pdfData = $publicVerification->getStoredCertificatePDF($certificateId);
                
                if (!$pdfData) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Certificate PDF not found']);
                    exit;
                }
                
                // Check if view mode (inline) or download mode
                $viewMode = isset($_GET['view']) && $_GET['view'] == '1';
                
                // Decode base64 PDF
                $pdfContent = base64_decode($pdfData['base64']);
                
                // Set headers
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($pdfContent));
                
                if ($viewMode) {
                    header('Content-Disposition: inline; filename="' . basename($pdfData['filename']) . '"');
                } else {
                    header('Content-Disposition: attachment; filename="' . basename($pdfData['filename']) . '"');
                }
                
                header('Cache-Control: public, max-age=3600');
                
                echo $pdfContent;
                exit;
                
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to retrieve certificate: ' . $e->getMessage()]);
            }
        }
        break;

    case '/certificates':
        if ($method === 'GET') {
            $user = requireAuth($token, $auth);
            
            if ($user['role'] === 'student') {
                // Get student's certificates
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id FROM students WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                $student = $stmt->fetch();
                
                if ($student) {
                    $certificates = $certService->getStudentCertificates($student['id']);
                    echo json_encode(['success' => true, 'certificates' => $certificates]);
                } else {
                    echo json_encode(['success' => true, 'certificates' => []]);
                }
            } else {
                // Admin/University can see all certificates
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    SELECT c.*, u.full_name as student_name, un.name as university_name
                    FROM certificates c
                    JOIN students s ON c.student_id = s.id
                    JOIN users u ON s.user_id = u.id
                    JOIN universities un ON c.university_id = un.id
                    ORDER BY c.created_at DESC
                ");
                $stmt->execute();
                $certificates = $stmt->fetchAll();
                echo json_encode(['success' => true, 'certificates' => $certificates]);
            }
        }
        break;

    case '/certificates/revoke':
        if ($method === 'POST') {
            $user = requireAuth($token, $auth, ['admin', 'university']);
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $certService->revokeCertificate($data['certificate_id'], $user['user_id']);
            echo json_encode(['success' => $result]);
        }
        break;

    case '/certificates/download':
        if ($method === 'GET') {
            // Student dashboard uses <a target="_blank"> with token in query string (no Authorization header in new tab)
            $downloadToken = $token;
            if (!$downloadToken && !empty($_GET['token']) && is_string($_GET['token'])) {
                $downloadToken = trim($_GET['token']);
            }
            $user = requireAuth($downloadToken, $auth);
            $certificateId = $_GET['certificate_id'] ?? '';
            
            if (empty($certificateId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Certificate ID required']);
                exit;
            }
            
            try {
                // FIX 3C: Use PDFService instead of deleted generator
                $pdfSvc = new \App\PDFService();
                $existingPDF = $pdfSvc->getPDFPath($certificateId);

                if (!$existingPDF || !file_exists($existingPDF)) {
                    $pdfSvc->generateCertificatePDF($certificateId, []);
                    $existingPDF = $pdfSvc->getPDFPath($certificateId);
                }
                
                if (!$existingPDF || !file_exists($existingPDF)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'PDF generation failed']);
                    exit;
                }
                
                // Serve PDF file
                $filename = basename($existingPDF);
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($existingPDF));
                header('Cache-Control: private, no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                readfile($existingPDF);
                exit;
                
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'PDF generation failed: ' . $e->getMessage()]);
            }
        }
        break;

    case '/universities':
        if ($method === 'GET') {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT * FROM universities WHERE is_active = TRUE");
            $universities = $stmt->fetchAll();
            echo json_encode(['success' => true, 'universities' => $universities]);
        } elseif ($method === 'POST') {
            $user = requireAuth($token, $auth, ['admin']);
            $data = json_decode(file_get_contents('php://input'), true);
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO universities (name, code, address, contact_email, contact_phone)
                VALUES (?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $data['name'],
                $data['code'],
                $data['address'] ?? null,
                $data['contact_email'] ?? null,
                $data['contact_phone'] ?? null
            ]);
            if (!$result) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to create university']);
                break;
            }
            $newUniversityId = (int) $db->lastInsertId();
            $uniName = trim($data['name'] ?? 'University');
            $keyInfo = $signatureService->generateUniversityKeyPair($newUniversityId, $uniName);
            echo json_encode([
                'success' => true,
                'university_id' => $newUniversityId,
                'signing_key_generated' => $keyInfo !== null,
            ]);
        }
        break;

    case '/students':
        if ($method === 'GET') {
            $user = requireAuth($token, $auth, ['university', 'admin']);
            $db = Database::getInstance()->getConnection();
            
            if ($user['role'] === 'university') {
                $stmt = $db->prepare("
                    SELECT s.*, u.full_name, u.email
                    FROM students s
                    JOIN users u ON s.user_id = u.id
                    WHERE s.university_id = ?
                ");
                $stmt->execute([$user['university_id']]);
            } else {
                $stmt = $db->query("
                    SELECT s.*, u.full_name, u.email, un.name as university_name
                    FROM students s
                    JOIN users u ON s.user_id = u.id
                    JOIN universities un ON s.university_id = un.id
                ");
            }
            
            $students = $stmt->fetchAll();
            echo json_encode(['success' => true, 'students' => $students]);
        } elseif ($method === 'POST') {
            $user = requireAuth($token, $auth, ['university', 'admin']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Create user first
            $userData = [
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'student',
                'full_name' => $data['full_name']
            ];
            
            $auth->register($userData);
            $newUser = $auth->login($data['email'], $data['password']);

            // FIX 14B: Null-check — user registered but login failed
            if (!$newUser) {
                http_response_code(500);
                echo json_encode(['error' => 'User registered but login failed. Please log in manually.']);
                break;
            }

            // Create student record
            $db = Database::getInstance()->getConnection();
            
            // For admin, require university_id in request; for university, use their own
            $universityId = null;
            if ($user['role'] === 'admin') {
                $universityId = $data['university_id'] ?? null;
            } else {
                $universityId = $user['university_id'] ?? null;
            }
            
            if (!$universityId) {
                http_response_code(400);
                echo json_encode(['error' => 'University ID required']);
                break;
            }
            
            $stmt = $db->prepare("
                INSERT INTO students (user_id, student_id, university_id, enrollment_date)
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $newUser['id'],
                $data['student_id'],
                $universityId,
                $data['enrollment_date'] ?? date('Y-m-d')
            ]);
            
            echo json_encode(['success' => $result]);
        }
        break;

    case '/certificates/get':
        if ($method === 'GET') {
            $certificateId = $_GET['certificate_id'] ?? null;
            if (!$certificateId) {
                http_response_code(400);
                echo json_encode(['error' => 'certificate_id required']);
                break;
            }
            try {
                $cert = $certService->getCertificate($certificateId);
                if (!$cert) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Certificate not found']);
                } else {
                    echo json_encode(['success' => true, 'certificate' => $cert]);
                }
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to retrieve certificate']);
            }
        }
        break;

    case '/certificates/update':
        if ($method === 'PUT' || $method === 'POST') {
            $user = requireAuth($token, $auth, ['university', 'admin']);
            $data = json_decode(file_get_contents('php://input'), true);
            $certificateId = $data['certificate_id'] ?? null;
            
            if (!$certificateId) {
                http_response_code(400);
                echo json_encode(['error' => 'certificate_id required']);
                break;
            }
            
            try {
                $certService->updateCertificate($certificateId, $data, $user['university_id'] ?? null);
                echo json_encode(['success' => true, 'message' => 'Certificate updated']);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update certificate: ' . $e->getMessage()]);
            }
        }
        break;

    case '/certificates/delete':
        if ($method === 'DELETE' || $method === 'POST') {
            $user = requireAuth($token, $auth, ['admin']);
            $data = json_decode(file_get_contents('php://input'), true);
            $certificateId = $data['certificate_id'] ?? null;
            
            if (!$certificateId) {
                http_response_code(400);
                echo json_encode(['error' => 'certificate_id required']);
                break;
            }
            
            try {
                $certService->deleteCertificate($certificateId);
                echo json_encode(['success' => true, 'message' => 'Certificate deleted']);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete certificate']);
            }
        }
        break;

    case '/certificates/list':
        if ($method === 'GET') {
            $user = requireAuth($token, $auth, ['university', 'admin']);
            
            $filters = [];
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 10);
            
            // Auto-scope to university if role is university
            if ($user['role'] === 'university') {
                $filters['university_id'] = $user['university_id'];
            } elseif (isset($_GET['university_id'])) {
                $filters['university_id'] = $_GET['university_id'];
            }
            
            if (isset($_GET['student_id'])) {
                $filters['student_id'] = $_GET['student_id'];
            }
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (isset($_GET['course_name'])) {
                $filters['course_name'] = $_GET['course_name'];
            }
            
            try {
                $result = $certService->listCertificates($filters, $page, $perPage);
                echo json_encode([
                    'success'      => true,
                    'certificates' => $result['certificates'],
                    'total'        => $result['pagination']['total'] ?? 0,
                    'page'         => $page,
                    'per_page'     => $perPage,
                    'pages'        => $result['pagination']['pages'] ?? 1,
                ]);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to list certificates']);
            }
        }
        break;

    case '/universities/generate-key':
        if ($method === 'POST') {
            $user = requireAuth($token, $auth, ['admin']);
            $data = json_decode(file_get_contents('php://input'), true);
            $universityId = $data['university_id'] ?? null;
            
            if (!$universityId) {
                http_response_code(400);
                echo json_encode(['error' => 'university_id required']);
                break;
            }
            
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT name FROM universities WHERE id = ?");
                $stmt->execute([$universityId]);
                $university = $stmt->fetch();
                
                if (!$university) {
                    http_response_code(404);
                    echo json_encode(['error' => 'University not found']);
                    break;
                }
                
                $signatureService->generateUniversityKeyPair($universityId, $university['name']);
                echo json_encode(['success' => true, 'message' => 'Key pair generated successfully']);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to generate key pair: ' . $e->getMessage()]);
            }
        }
        break;

    case '/auth/profile':
        if ($method === 'GET') {
            $user = requireAuth($token, $auth);
            $profile = $auth->getUserById($user['user_id']);
            if (!$profile) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
                break;
            }
            echo json_encode(['success' => true, 'data' => $profile]);
        } elseif ($method === 'PUT') {
            $user = requireAuth($token, $auth);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $result = $auth->updateProfile($user['user_id'], $data);
            if ($result) {
                $profile = $auth->getUserById($user['user_id']);
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully', 'data' => $profile]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields to update or update failed']);
            }
        }
        break;

    case '/auth/change-password':
        if ($method === 'POST') {
            $user = requireAuth($token, $auth);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $currentPassword = $data['current_password'] ?? '';
            $newPassword     = $data['new_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword)) {
                http_response_code(400);
                echo json_encode(['error' => 'current_password and new_password are required']);
                break;
            }

            $pwError = validatePasswordStrength($newPassword);
            if ($pwError) {
                http_response_code(400);
                echo json_encode(['error' => $pwError]);
                break;
            }

            $result = $auth->changePassword((int) $user['user_id'], $currentPassword, $newPassword);
            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => $result['error']]);
            }
        }
        break;

    case '/auth/forgot-password':
        if ($method === 'POST') {
            $data  = json_decode(file_get_contents('php://input'), true) ?? [];
            $email = trim($data['email'] ?? '');

            if (empty($email)) {
                http_response_code(400);
                echo json_encode(['error' => 'email is required']);
                break;
            }

            $resetData = $auth->createPasswordResetToken($email);

            // Always respond with success to prevent user enumeration
            if ($resetData) {
                $emailService = new EmailService();
                if (!$emailService->sendPasswordResetEmail($resetData['email'], $resetData['token'], $resetData['expires_at'])) {
                    error_log("Failed to send password reset email to " . $resetData['email']);
                }
            }

            echo json_encode(['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent']);
        }
        break;

    case '/auth/reset-password':
        if ($method === 'POST') {
            $data        = json_decode(file_get_contents('php://input'), true) ?? [];
            $token_value = $data['token'] ?? '';
            $newPassword = $data['new_password'] ?? '';

            if (empty($token_value) || empty($newPassword)) {
                http_response_code(400);
                echo json_encode(['error' => 'token and new_password are required']);
                break;
            }

            $pwError = validatePasswordStrength($newPassword);
            if ($pwError) {
                http_response_code(400);
                echo json_encode(['error' => $pwError]);
                break;
            }

            $result = $auth->resetPassword($token_value, $newPassword);
            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => 'Password has been reset successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => $result['error']]);
            }
        }
        break;

    case '/auth/logout':
        if ($method === 'POST') {
            requireAuth($token, $auth);
            // Stateless JWT: token invalidation is handled client-side
            echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        }
        break;

    case '/auth/profile/avatar':
        if ($method === 'PUT') {
            $user = requireAuth($token, $auth);

            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
                http_response_code(400);
                echo json_encode(['error' => 'No file uploaded']);
                break;
            }

            $fileService = new FileService();
            $result = $fileService->uploadAvatar($user['user_id'], $_FILES['avatar']);

            if (!$result['success']) {
                http_response_code(400);
                echo json_encode(['error' => $result['error']]);
                break;
            }

            $auth->updateAvatar($user['user_id'], $result['path']);
            $profile = $auth->getUserById($user['user_id']);
            echo json_encode(['success' => true, 'message' => 'Avatar updated successfully', 'data' => $profile]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        break;
}