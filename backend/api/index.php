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
    getenv('FRONTEND_URL') ?: '',
];

// Remove empty strings from array
$allowedOrigins = array_values(array_filter($allowedOrigins));

$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$appEnv = getenv('APP_ENV') ?: 'production';

$isLocalhost = preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $requestOrigin);

// In development, allow all origins. In production, be strict.
if ($appEnv === 'local' || $appEnv === 'development') {
    header('Access-Control-Allow-Origin: *');
} elseif (!empty($requestOrigin) && (in_array($requestOrigin, $allowedOrigins, true) || $isLocalhost)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
} elseif (empty($requestOrigin)) {
    // For local/development without origin (e.g., Postman, direct API calls)
    header('Access-Control-Allow-Origin: *');
} else {
    // Default to first allowed origin
    header('Access-Control-Allow-Origin: ' . (reset($allowedOrigins) ?: '*'));
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
use App\PublicVerificationService;
use App\SignatureService;

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($path, PHP_URL_PATH);
$path = str_replace('/api', '', $path);

$auth = new Auth();
$certService = new CertificateService();
$signatureService = new SignatureService();

// Extract token from Authorization header
$headers = getallheaders();
$token = null;
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
}

function requireAuth($token, $auth, $allowedRoles = []) {
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $payload = $auth->verifyToken($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }

    if (!empty($allowedRoles) && !in_array($payload['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    return $payload;
}

// Route handling
switch ($path) {
    case '/auth/login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = $auth->login($data['email'] ?? '', $data['password'] ?? '');
            
            if ($user) {
                $token = $auth->generateToken($user);
                echo json_encode(['success' => true, 'token' => $token, 'user' => $user]);
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
            $user = requireAuth($token, $auth);
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
            echo json_encode(['success' => $result]);
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

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        break;
}

