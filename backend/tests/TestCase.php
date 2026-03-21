<?php

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = test_create_pdo();
        test_migrate_schema($this->pdo);
        test_set_database_connection($this->pdo);
        test_reset_cache();
    }

    protected function seedUser(array $overrides = []): array
    {
        $data = array_merge([
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            'role' => 'university',
            'full_name' => 'Alice Doe',
            'university_id' => 1,
        ], $overrides);

        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, full_name, university_id)
            VALUES (:username, :email, :password_hash, :role, :full_name, :university_id)
        ");
        $stmt->execute($data);

        $data['id'] = (int) $this->pdo->lastInsertId();
        return $data;
    }

    protected function seedUniversity(array $overrides = []): array
    {
        $data = array_merge([
            'name' => 'Test University',
            'code' => 'TSTU',
        ], $overrides);

        $stmt = $this->pdo->prepare("INSERT INTO universities (name, code) VALUES (:name, :code)");
        $stmt->execute($data);
        $data['id'] = (int) $this->pdo->lastInsertId();
        return $data;
    }

    protected function seedStudent(int $userId, int $universityId, array $overrides = []): array
    {
        $data = array_merge([
            'user_id' => $userId,
            'student_id' => 'STU-123',
            'university_id' => $universityId,
        ], $overrides);

        $stmt = $this->pdo->prepare("
            INSERT INTO students (user_id, student_id, university_id)
            VALUES (:user_id, :student_id, :university_id)
        ");
        $stmt->execute($data);
        $data['id'] = (int) $this->pdo->lastInsertId();
        return $data;
    }

    protected function seedCertificate(array $overrides = []): array
    {
        $data = array_merge([
            'certificate_id' => 'CERT-ABC123',
            'student_id' => null,
            'university_id' => null,
            'course_name' => 'Computer Science',
            'degree_type' => 'Bachelor',
            'issue_date' => '2024-01-01',
            'certificate_hash' => '0xhash',
            'pdf_path' => 'cert.pdf',
            'qr_code_path' => 'qr.png',
            'status' => 'active',
            'metadata_hash' => '0xmeta',
            'pdf_hash' => '0xpdf',
            'onchain_hash' => '0xonchain',
            'metadata_json' => json_encode(['certificate_id' => 'CERT-ABC123']),
            'signature_status' => 0,
            'block_number' => 1,
            'chain_id' => 1337,
            'schema_version' => '1.0',
            'is_revoked' => 0,
        ], $overrides);

        $stmt = $this->pdo->prepare("
            INSERT INTO certificates
            (certificate_id, student_id, university_id, course_name, degree_type, issue_date,
             certificate_hash, blockchain_tx_hash, pdf_path, qr_code_path, status,
             metadata_hash, pdf_hash, onchain_hash, metadata_json, signature_status,
             block_number, chain_id, schema_version, is_revoked)
            VALUES
            (:certificate_id, :student_id, :university_id, :course_name, :degree_type, :issue_date,
             :certificate_hash, :blockchain_tx_hash, :pdf_path, :qr_code_path, :status,
             :metadata_hash, :pdf_hash, :onchain_hash, :metadata_json, :signature_status,
             :block_number, :chain_id, :schema_version, :is_revoked)
        ");

        $stmt->execute($data);
        $data['id'] = (int) $this->pdo->lastInsertId();
        return $data;
    }
}
