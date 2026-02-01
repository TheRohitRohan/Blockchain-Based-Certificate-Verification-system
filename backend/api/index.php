<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Simple autoloader since we're not using Composer extensively
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

use App\Auth;
use App\CertificateService;
use App\Database;
use App\PDFGenerator;

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($path, PHP_URL_PATH);
$path = str_replace('/api', '', $path);

$auth = new Auth();
$certService = new CertificateService();

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

    case '/certificates/verify':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $certService->verifyCertificate(
                $data['certificate_id'] ?? '',
                $data['certificate_hash'] ?? null
            );
            echo json_encode($result);
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
            $user = requireAuth($token, $auth, ['admin']);
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
                $pdfGenerator = new PDFGenerator();
                
                // Check if PDF already exists
                $existingPDF = $pdfGenerator->getPDFPath($certificateId);
                
                if ($existingPDF && file_exists($existingPDF)) {
                    // Serve existing PDF
                    $filename = basename($existingPDF);
                } else {
                    // Generate new PDF
                    $filename = $pdfGenerator->generateCertificatePDF($certificateId);
                    $existingPDF = $pdfGenerator->getPDFPath($certificateId);
                }
                
                if (!$existingPDF || !file_exists($existingPDF)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'PDF generation failed']);
                    exit;
                }
                
                // Serve PDF file
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
            
            // Create student record
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO students (user_id, student_id, university_id, enrollment_date)
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $newUser['id'],
                $data['student_id'],
                $user['university_id'] ?? $data['university_id'],
                $data['enrollment_date'] ?? date('Y-m-d')
            ]);
            
            echo json_encode(['success' => $result]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        break;
}

