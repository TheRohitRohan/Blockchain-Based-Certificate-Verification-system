<?php

namespace App;

use PDO;

class Auth {
    private $db;
    private const PASSWORD_RESET_TOKEN_LIFETIME = 15 * 60; // 15 minutes in seconds

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT id, username, email, password_hash, role, full_name, university_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            return $user;
        }

        return null;
    }

    public function register($data) {
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash, role, full_name, university_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['username'],
            $data['email'],
            $passwordHash,
            $data['role'],
            $data['full_name'] ?? null,
            $data['university_id'] ?? null
        ]);
    }

    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT id, username, email, role, full_name, avatar_path, university_id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateProfile(int $userId, array $data): bool {
        $allowed = ['username', 'full_name'];
        $fields = [];
        $values = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $userId;
        $stmt = $this->db->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): array {
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);

        return ['success' => true];
    }

    public function createPasswordResetToken(string $email): ?array {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Non-enumeration: return null to indicate no user found (caller still returns success)
            return null;
        }

        // Delete any existing tokens for this user
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        $token = bin2hex(random_bytes(32));
        $expiresAt = time() + self::PASSWORD_RESET_TOKEN_LIFETIME;

        $stmt = $this->db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $token, date('Y-m-d H:i:s', $expiresAt)]);

        return ['token' => $token, 'expires_at' => $expiresAt, 'email' => $email];
    }

    public function resetPassword(string $token, string $newPassword): array {
        $stmt = $this->db->prepare("
            SELECT pr.user_id, pr.expires_at
            FROM password_resets pr
            WHERE pr.token = ?
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            return ['success' => false, 'error' => 'Invalid or expired reset token'];
        }

        if (strtotime($reset['expires_at']) < time()) {
            $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            return ['success' => false, 'error' => 'Reset token has expired'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $reset['user_id']]);

        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);

        return ['success' => true];
    }

    public function updateAvatar(int $userId, string $avatarPath): bool {
        $stmt = $this->db->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        return $stmt->execute([$avatarPath, $userId]);
    }

    public function generateToken($user) {
        $config = require __DIR__ . '/../config.php';
        $secret = $config['jwt']['secret'];
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'exp' => time() + $config['jwt']['expiration']
        ]);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    public function verifyToken($token) {
        $config = require __DIR__ . '/../config.php';
        $secret = $config['jwt']['secret'];

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($base64Header, $base64Payload, $base64Signature) = $parts;

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        if ($base64Signature !== $expectedSignature) {
            return null;
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);

        if ($payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }
}

