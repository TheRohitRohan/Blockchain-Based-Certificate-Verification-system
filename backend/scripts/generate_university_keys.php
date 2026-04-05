<?php
/**
 * Generate Signing Keys for All Universities
 * 
 * This script:
 * 1. Gets all active universities from database
 * 2. Generates RSA 2048-bit key pairs for each
 * 3. Creates self-signed certificates
 * 4. Stores keys in university_keys table with encryption
 * 5. Saves certificate files to storage
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$config = require __DIR__ . '/../config.php';
$db = Database::getInstance()->getConnection();

echo "=== Generating Signing Keys for All Universities ===\n\n";

// Get all active universities
$stmt = $db->query("SELECT id, name, code FROM universities WHERE is_active = 1 ORDER BY id");
$universities = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (empty($universities)) {
    echo "❌ No active universities found in database.\n";
    exit(1);
}

echo "Found " . count($universities) . " active universities.\n\n";

$keysCreated = 0;
$keysFailed = 0;
$storageDir = __DIR__ . '/../storage/certs';

// Ensure storage directory exists
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
    echo "✓ Created storage directory: $storageDir\n\n";
}

// Process each university
foreach ($universities as $university) {
    $univId = $university['id'];
    $univName = $university['name'];
    $univCode = $university['code'];

    echo "Processing: [$univCode] $univName (ID: $univId)...\n";

    try {
        // Step 1: Generate private key using openssl command (more reliable on Windows)
        $tempDir = sys_get_temp_dir();
        $tempKeyFile = "{$tempDir}/cert_key_{$univId}_" . uniqid() . ".key";
        $tempCertFile = "{$tempDir}/cert_crt_{$univId}_" . uniqid() . ".crt";
        $p12Path = "{$storageDir}/{$univCode}.p12";
        $p12Password = bin2hex(random_bytes(16));

        // Generate private key (RSA 2048-bit)
        $cmd = "openssl genrsa -out " . escapeshellarg($tempKeyFile) . " 2048 2>&1";
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \Exception("Failed to generate private key: " . implode("\n", $output));
        }

        // Read private key
        $privateKeyPem = file_get_contents($tempKeyFile);
        if (!$privateKeyPem) {
            throw new \Exception("Failed to read generated private key");
        }

        // Step 2: Generate self-signed certificate (valid 10 years = 3650 days)
        $subj = "/C=US/ST=State/L=City/O=" . str_replace("/", "\\/", $univName) . "/CN=cert-signer-{$univCode}/emailAddress=certs@{$univCode}.edu";
        $cmd = "openssl req -new -x509 -days 3650 -key " . escapeshellarg($tempKeyFile) . 
               " -out " . escapeshellarg($tempCertFile) . " -subj " . escapeshellarg($subj) . " 2>&1";
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \Exception("Failed to generate certificate: " . implode("\n", $output));
        }

        // Read certificate
        $certPem = file_get_contents($tempCertFile);
        if (!$certPem) {
            throw new \Exception("Failed to read generated certificate");
        }

        // Step 3: Create PKCS#12 (.p12) from certificate and key
        $cmd = "openssl pkcs12 -export -in " . escapeshellarg($tempCertFile) . 
               " -inkey " . escapeshellarg($tempKeyFile) . 
               " -out " . escapeshellarg($p12Path) . 
               " -name " . escapeshellarg("University Signing Key - {$univName}") .
               " -passout pass:" . escapeshellarg($p12Password) . " 2>&1";
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \Exception("Failed to create PKCS#12: " . implode("\n", $output));
        }

        // Step 4: Extract public key from certificate
        $cmd = "openssl x509 -in " . escapeshellarg($tempCertFile) . 
               " -noout -pubkey 2>&1";
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \Exception("Failed to extract public key: " . implode("\n", $output));
        }
        $publicKeyPem = implode("\n", $output);

        // Step 6: Encrypt the private key using same method as SignatureService
        $encryptedPrivateKey = encryptPrivateKey($privateKeyPem, $config);

        // Step 7: Calculate key fingerprint
        $keyFingerprint = hash('sha256', $publicKeyPem);

        // Step 8: Check if key already exists for this university
        $stmt = $db->prepare("
            SELECT id FROM university_keys 
            WHERE university_id = ? AND is_active = 1
        ");
        $stmt->execute([$univId]);
        $existingKey = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existingKey) {
            // Update existing key
            $stmt = $db->prepare("
                UPDATE university_keys
                SET certificate_path = ?,
                    certificate_password = ?,
                    public_key_pem = ?,
                    key_fingerprint = ?,
                    updated_at = NOW()
                WHERE university_id = ? AND is_active = 1
            ");
            $stmt->execute([
                $p12Path,
                $encryptedPrivateKey,
                $publicKeyPem,
                $keyFingerprint,
                $univId
            ]);
            echo "  ✓ Updated existing key\n";
        } else {
            // Insert new key
            $stmt = $db->prepare("
                INSERT INTO university_keys 
                (university_id, certificate_path, certificate_password, public_key_pem, key_fingerprint, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $univId,
                $p12Path,
                $encryptedPrivateKey,
                $publicKeyPem,
                $keyFingerprint
            ]);
            echo "  ✓ Created new key entry\n";
        }

        echo "  ✓ Key stored: {$p12Path}\n";
        echo "  ✓ Fingerprint: {$keyFingerprint}\n";
        echo "  ✓ P12 Password: {$p12Password} (save this safely!)\n";
        echo "\n";

        $keysCreated++;
        
        // Cleanup temp files
        @unlink($tempKeyFile);
        @unlink($tempCertFile);

    } catch (\Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n\n";
        $keysFailed++;
    }
}

echo "=== Summary ===\n";
echo "✓ Created: $keysCreated\n";
echo ($keysFailed > 0) ? "❌ Failed: $keysFailed\n" : "";
echo "\nDone!\n";

// ============================================================================
// Helper Function: Encrypt private key using AES-256-CBC (matches SignatureService)
// ============================================================================

function encryptPrivateKey(string $privateKeyPem, array $config): string
{
    $secret = $config['signing']['key_encryption_secret'] ?? '';
    
    // Hash to get proper 32-byte key
    if (strlen($secret) < 32) {
        $secret = hash('sha256', $secret, true);
    } else {
        $secret = substr($secret, 0, 32);
    }

    // Generate random IV
    $iv = openssl_random_pseudo_bytes(16);

    // Encrypt
    $encrypted = openssl_encrypt(
        $privateKeyPem,
        'AES-256-CBC',
        $secret,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($encrypted === false) {
        throw new \Exception("Failed to encrypt private key: " . openssl_error_string());
    }

    // Return as base64(iv) . '.' . base64(ciphertext)
    return base64_encode($iv) . '.' . base64_encode($encrypted);
}
