<?php

namespace App;

use PDO;

class Auth {
    private $db;

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
        $stmt = $this->db->prepare("SELECT id, username, email, role, full_name, university_id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
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

