<?php

/**
 * Production Seeding Script
 * 
 * Clears database (except admin data) and seeds:
 * - 5 Universities with signing keys
 * - 1 Student per university
 * - 1 Certificate per student
 * 
 * Usage: php seed_production.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

// Configuration
$baseUrl = 'https://blockchain-based-certificate-verification-system-production.up.railway.app/api';
$adminEmail = 'admin@certificate-system.com'; // Actual admin email
$adminPassword = 'admin123';                   // Default admin password

class ProductionSeeder {
    private $baseUrl;
    private $adminToken = null;
    private $universityData = [];
    private $studentData = [];
    private $certificateData = [];

    public function __construct($baseUrl) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Make HTTP request to API
     */
    private function apiCall($endpoint, $method = 'GET', $data = null, $token = null, $retryCount = 0) {
        if ($retryCount > 2) {
            throw new Exception("API call failed after 3 retries: $endpoint");
        }
        
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // Network error - retry
        if ($curlError) {
            echo "  [DEBUG] cURL Error [$curlErrno]: $curlError for $endpoint, retrying...\n";
            sleep(1);  // Wait a bit before retry
            return $this->apiCall($endpoint, $method, $data, $token, $retryCount + 1);
        }

        // Server error with empty response - might be temporary, retry
        if ($httpCode >= 500 && empty($response)) {
            echo "  [DEBUG] HTTP $httpCode with empty response from $endpoint, retrying...\n";
            sleep(1);  // Wait a bit before retry
            return $this->apiCall($endpoint, $method, $data, $token, $retryCount + 1);
        }

        // Debug: Log the raw response for empty responses
        if (empty($response) && $httpCode > 0) {
            echo "  [DEBUG] Empty response from $endpoint ($method), HTTP code: $httpCode\n";
        }
        
        $decoded = json_decode($response, true);
        
        return [
            'status' => $httpCode,
            'body' => $decoded ?? $response,
            'raw' => $response
        ];
    }

    /**
     * Login as admin and get JWT token
     */
    public function loginAdmin($email, $password) {
        $this->log("🔐 Logging in as admin: $email");
        
        try {
            $response = $this->apiCall('auth/login', 'POST', [
                'email' => $email,
                'password' => $password
            ]);

            if ($response['status'] !== 200 || !isset($response['body']['token'])) {
                throw new Exception("Admin login failed. Status: {$response['status']}, Response: " . json_encode($response['body']));
            }

            $this->adminToken = $response['body']['token'];
            $this->log("✅ Admin login successful!");
            return $this->adminToken;
        } catch (Exception $e) {
            $this->log("❌ Admin login error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete all universities (cascades to students/certificates)
     */
    public function clearDatabase() {
        $this->log("\n🗑️  Clearing database (keeping admin)...");
        
        try {
            // Get all universities
            $response = $this->apiCall('universities', 'GET', null, $this->adminToken);
            
            if ($response['status'] !== 200 || !isset($response['body']['universities'])) {
                $this->log("⚠️  Could not fetch universities for deletion");
                return;
            }

            $universities = $response['body']['universities'];
            foreach ($universities as $uni) {
                $delResponse = $this->apiCall("universities/{$uni['id']}", 'DELETE', null, $this->adminToken);
                if ($delResponse['status'] === 200) {
                    $this->log("  ✓ Deleted university: {$uni['name']}");
                } else {
                    $this->log("  ✗ Failed to delete {$uni['name']}: {$delResponse['status']}");
                }
            }

            $this->log("✅ Database cleared!");
        } catch (Exception $e) {
            $this->log("⚠️  Clear database error: " . $e->getMessage());
        }
    }

    /**
     * Create a university via API and get ID from API response
     */
    public function createUniversity($name, $code, $email) {
        try {
            $this->log("  Creating university: $name ($code)...");
            
            $payload = [
                'name' => $name,
                'code' => $code,
                'address' => $name . ', USA',
                'contact_email' => $email,
                'contact_phone' => '123-456-7890'
            ];
            
            $response = $this->apiCall('universities', 'POST', $payload, $this->adminToken);

            // API returns {"success": true/false} only, not the ID
            if ($response['status'] !== 200 || !($response['body']['success'] ?? false)) {
                throw new Exception("Failed to create university. Status: {$response['status']}, Response: " . json_encode($response['body']));
            }

            // Fetch universities to get the ID of the one we just created
            $uniId = $this->getUniversityIdByCode($code);
            if (!$uniId) {
                throw new Exception("University created but ID not found via API");
            }
            
            $this->log("    ✓ University created (ID: $uniId)");
            
            return $uniId;
        } catch (Exception $e) {
            $this->log("    ✗ Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get university ID by code via API
     */
    private function getUniversityIdByCode($code) {
        try {
            $response = $this->apiCall('universities', 'GET', null);
            
            if ($response['status'] !== 200 || !isset($response['body']['universities'])) {
                return null;
            }
            
            $universities = $response['body']['universities'];
            foreach ($universities as $uni) {
                if ($uni['code'] === $code) {
                    return $uni['id'];
                }
            }
            
            return null;
        } catch (Exception $e) {
            $this->log("Error fetching universities: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get last created university ID by code from DB (deprecated - see getUniversityIdByCode)
     */
    private function getLastUniversityId($code) {
        try {
            // Connect to production database
            $dbHost = 'ballast.proxy.rlwy.net';
            $dbPort = 21041;
            $dbUser = 'railroad';
            $dbPass = 'SjaueyNNGuKYFOYydzpjoUCNjdEwQxsY';
            $dbName = 'railway';
            
            $pdo = new \PDO(
                "mysql:host=$dbHost;port=$dbPort;dbname=$dbName",
                $dbUser,
                $dbPass,
                ['PDO::ATTR_ERRMODE' => \PDO::ERRMODE_EXCEPTION]
            );
            
            $stmt = $pdo->prepare("SELECT id FROM universities WHERE code = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$code]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return $result ? $result['id'] : null;
        } catch (Exception $e) {
            $this->log("Database error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate signing key for university
     */
    public function generateSigningKey($universityId) {
        try {
            $this->log("  Generating signing key for university $universityId...");
            
            $response = $this->apiCall('universities/generate-key', 'POST', [
                'university_id' => $universityId
            ], $this->adminToken);

            // API returns {"success": true, "message": "..."}, not detailed key info
            if ($response['status'] !== 200 || !($response['body']['success'] ?? false)) {
                throw new Exception("Failed to generate key. Response: " . json_encode($response['body']));
            }

            $this->log("    ✓ Key pair generated successfully");
            
            return true;
        } catch (Exception $e) {
            $this->log("    ✗ Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create university user (admin for that university)
     */
    public function createUniversityUser($universityId, $email, $password) {
        try {
            $this->log("  Creating university user: $email...");
            
            $payload = [
                'username' => explode('@', $email)[0] . uniqid(),
                'email' => $email,
                'password' => $password,
                'role' => 'university',
                'full_name' => 'University Admin',
                'university_id' => $universityId
            ];
            
            $this->log("    [DEBUG] Register payload: " . json_encode($payload));
            
            $response = $this->apiCall('auth/register', 'POST', $payload);

            $this->log("    [DEBUG] Register response status: {$response['status']}");
            $this->log("    [DEBUG] Register response: " . json_encode($response['body']));
            
            if ($response['status'] !== 200 && $response['status'] !== 201) {
                throw new Exception("Failed to create university user. Response: " . json_encode($response['body']));
            }

            $this->log("    ✓ University user created");
            
            return true;
        } catch (Exception $e) {
            $this->log("    ✗ Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Login as university user
     */
    public function loginUniversityUser($email, $password) {
        try {
            $response = $this->apiCall('auth/login', 'POST', [
                'email' => $email,
                'password' => $password
            ]);

            if ($response['status'] !== 200 || !isset($response['body']['token'])) {
                throw new Exception("Failed to login. Response: " . json_encode($response['body']));
            }

            return $response['body']['token'];
        } catch (Exception $e) {
            $this->log("    ✗ Login failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a student as admin
     */
    public function createStudentAsAdmin($universityId, $studentId, $studentName) {
        try {
            $this->log("  Creating student: $studentName...");
            
            $studentEmail = strtolower(str_replace(' ', '.', $studentName)) . '@student.edu';
            $uniqueUsername = 'stu' . uniqid();
            
            $payload = [
                'username' => $uniqueUsername,
                'email' => $studentEmail,
                'password' => 'TempPass123!',
                'full_name' => $studentName,
                'student_id' => $studentId,
                'university_id' => $universityId,
                'enrollment_date' => date('Y-m-d')
            ];
            
            $response = $this->apiCall('students', 'POST', $payload, $this->adminToken);

            if ($response['status'] !== 200 && $response['status'] !== 201) {
                throw new Exception("Failed to create student. Response: " . json_encode($response['body']));
            }

            $this->log("    ✓ Student created (ID: $studentId)");
            
            return $studentId;
        } catch (Exception $e) {
            $this->log("    ✗ Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a certificate as admin
     */
    public function createCertificateAsAdmin($studentId, $universityId, $courseName) {
        try {
            $this->log("  Creating certificate for student $studentId...");
            
            $payload = [
                'student_id' => $studentId,
                'university_id' => $universityId,
                'course_name' => $courseName,
                'degree_type' => 'Bachelor of Science',
                'issue_date' => date('Y-m-d')
            ];
            
            $response = $this->apiCall('certificates/create', 'POST', $payload, $this->adminToken);

            if ($response['status'] !== 200 && $response['status'] !== 201) {
                throw new Exception("Failed to create certificate. Response: " . json_encode($response['body']));
            }

            $certId = $response['body']['certificate_id'] ?? 'UNKNOWN';
            $this->log("    ✓ Certificate created (ID: $certId)");
            
            return $certId;
        } catch (Exception $e) {
            $this->log("    ✗ Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Run complete seeding workflow (simplified - use admin for all operations)
     */
    public function run($adminEmail, $adminPassword) {
        try {
            $universities = [
                ['name' => 'Harvard University', 'code' => 'HARV' . substr(uniqid(), -4), 'email' => 'admin@harvard.edu'],
                ['name' => 'MIT', 'code' => 'MIT' . substr(uniqid(), -4), 'email' => 'admin@mit.edu'],
                ['name' => 'Stanford University', 'code' => 'STAN' . substr(uniqid(), -4), 'email' => 'admin@stanford.edu'],
                ['name' => 'Berkeley University', 'code' => 'BERK' . substr(uniqid(), -4), 'email' => 'admin@berkeley.edu'],
                ['name' => 'Yale University', 'code' => 'YALE' . substr(uniqid(), -4), 'email' => 'admin@yale.edu'],
            ];

            $this->log("\n📚 Creating 5 universities with students and certificates...\n");

            foreach ($universities as $index => $uni) {
                // Re-authenticate before each university to ensure fresh token
                $this->loginAdmin($adminEmail, $adminPassword);
                
                $this->log("【 University " . ($index + 1) . " of 5 】");
                
                // Create university
                $uniId = $this->createUniversity($uni['name'], $uni['code'], $uni['email']);
                
                // Generate signing key
                $this->generateSigningKey($uniId);
                
                // Create 1 student (using admin token, no need for university user)
                $studentId = 'STU-' . $uni['code'] . '-001';
                $studentName = 'Student ' . ($index + 1);
                $createdStudentId = $this->createStudentAsAdmin($uniId, $studentId, $studentName);
                
                // Create 1 certificate (using admin token)
                $courseName = $uni['name'] . ' Bachelor Program';
                $certId = $this->createCertificateAsAdmin($createdStudentId, $uniId, $courseName);
                
                // Store data
                $this->universityData[] = [
                    'id' => $uniId,
                    'name' => $uni['name'],
                    'code' => $uni['code'],
                    'email' => $uni['email']
                ];
                
                $this->studentData[] = [
                    'university_id' => $uniId,
                    'student_id' => $createdStudentId,
                    'name' => $studentName
                ];
                
                $this->certificateData[] = [
                    'id' => $certId,
                    'student_id' => $createdStudentId,
                    'course_name' => $courseName
                ];
                
                $this->log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
            }

            $this->printSummary();
            $this->log("\n✅ Seeding completed successfully!");

        } catch (Exception $e) {
            $this->log("\n❌ Seeding failed: " . $e->getMessage());
            exit(1);
        }
    }

    /**
     * Print summary table
     */
    private function printSummary() {
        $this->log("\n═══════════════════════════════════════════════════════════════════");
        $this->log("📊 SEEDING SUMMARY");
        $this->log("═══════════════════════════════════════════════════════════════════");
        
        $this->log("\n📚 UNIVERSITIES:");
        foreach ($this->universityData as $uni) {
            $this->log("  • {$uni['name']} ({$uni['code']})");
            $this->log("    ID: {$uni['id']} | Email: {$uni['email']}");
        }
        
        $this->log("\n👥 STUDENTS:");
        foreach ($this->studentData as $student) {
            $this->log("  • {$student['name']} (ID: {$student['student_id']}) @ University {$student['university_id']}");
        }
        
        $this->log("\n📜 CERTIFICATES:");
        foreach ($this->certificateData as $cert) {
            $this->log("  • {$cert['course_name']} (Cert ID: {$cert['id']})");
            $this->log("    Student: {$cert['student_id']}");
        }
        
        $this->log("\n═══════════════════════════════════════════════════════════════════\n");
    }

    /**
     * Log message with timestamp
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$message}\n";
    }
}

// Run seeder
$seeder = new ProductionSeeder($baseUrl);
$seeder->run($adminEmail, $adminPassword);
