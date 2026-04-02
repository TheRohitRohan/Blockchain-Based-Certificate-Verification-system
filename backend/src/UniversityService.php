<?php

namespace App;

use PDO;

class UniversityService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get university details by ID.
     */
    public function getUniversity(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT id, name, code, address, contact_email, contact_phone, is_active
            FROM universities
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update allowed university fields: name, address, contact_email, contact_phone.
     * Fields 'code' and 'wallet_address' are intentionally excluded.
     */
    public function updateUniversity(int $id, array $data): bool {
        $allowed = ['name', 'address', 'contact_email', 'contact_phone'];
        $sets    = [];
        $params  = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data) && trim((string)$data[$field]) !== '') {
                $sets[]   = "$field = ?";
                $params[] = trim((string)$data[$field]);
            }
        }

        if (empty($sets)) {
            return false;
        }

        $params[] = $id;
        $stmt     = $this->db->prepare("UPDATE universities SET " . implode(', ', $sets) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    /**
     * Soft-delete a university by setting is_active = FALSE.
     */
    public function deactivateUniversity(int $id): bool {
        $stmt = $this->db->prepare("UPDATE universities SET is_active = FALSE WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * List students of a university with pagination and optional filtering.
     *
     * Supported filters: is_active, enrollment_date_from, enrollment_date_to
     */
    public function getUniversityStudents(int $universityId, array $filters, int $page, int $limit): array {
        $page   = max(1, $page);
        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $conditions = ['s.university_id = ?', 's.is_active = TRUE'];
        $params     = [$universityId];

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            // Replace the default is_active = TRUE condition with a parameterised one
            array_pop($conditions);
            $val = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $conditions[] = 's.is_active = ?';
                $params[]     = $val ? 1 : 0;
            } else {
                $conditions[] = 's.is_active = TRUE';
            }
        }

        if (!empty($filters['enrollment_date_from'])) {
            $conditions[] = 's.enrollment_date >= ?';
            $params[]     = $filters['enrollment_date_from'];
        }

        if (!empty($filters['enrollment_date_to'])) {
            $conditions[] = 's.enrollment_date <= ?';
            $params[]     = $filters['enrollment_date_to'];
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM students s $where
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $this->db->prepare("
            SELECT s.id, s.student_id, s.enrollment_date, s.is_active,
                   u.full_name, u.email
            FROM students s
            JOIN users u ON s.user_id = u.id
            $where
            ORDER BY s.enrollment_date DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $limit;
        $params[] = $offset;
        $dataStmt->execute($params);
        $students = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'students'   => $students,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit),
            ],
        ];
    }

    /**
     * List certificates issued by a university with pagination and optional filtering.
     *
     * Supported filters: status, course_name, issue_date_from, issue_date_to
     */
    public function getUniversityCertificates(int $universityId, array $filters, int $page, int $limit): array {
        $page   = max(1, $page);
        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $conditions = ['c.university_id = ?'];
        $params     = [$universityId];

        if (!empty($filters['status'])) {
            $conditions[] = 'c.status = ?';
            $params[]     = $filters['status'];
        }

        if (!empty($filters['course_name'])) {
            $conditions[] = 'c.course_name LIKE ?';
            $params[]     = '%' . $filters['course_name'] . '%';
        }

        if (!empty($filters['issue_date_from'])) {
            $conditions[] = 'c.issue_date >= ?';
            $params[]     = $filters['issue_date_from'];
        }

        if (!empty($filters['issue_date_to'])) {
            $conditions[] = 'c.issue_date <= ?';
            $params[]     = $filters['issue_date_to'];
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM certificates c $where
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $dataStmt = $this->db->prepare("
            SELECT c.certificate_id, c.course_name, c.degree_type, c.issue_date, c.status,
                   u.full_name AS student_name
            FROM certificates c
            JOIN students s ON c.student_id = s.id
            JOIN users u ON s.user_id = u.id
            $where
            ORDER BY c.issue_date DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $limit;
        $params[] = $offset;
        $dataStmt->execute($params);
        $certificates = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'certificates' => $certificates,
            'pagination'   => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit),
            ],
        ];
    }

    /**
     * Return aggregate statistics for a university.
     */
    public function getUniversityStats(int $universityId): array {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)                                       AS total_students,
                SUM(CASE WHEN s.is_active = TRUE THEN 1 ELSE 0 END) AS active_students
            FROM students s
            WHERE s.university_id = ?
        ");
        $stmt->execute([$universityId]);
        $studentStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt2 = $this->db->prepare("
            SELECT
                COUNT(*)                                              AS total_certificates,
                SUM(CASE WHEN c.status = 'active'  THEN 1 ELSE 0 END) AS active_certificates,
                SUM(CASE WHEN c.status = 'revoked' THEN 1 ELSE 0 END) AS revoked_certificates
            FROM certificates c
            WHERE c.university_id = ?
        ");
        $stmt2->execute([$universityId]);
        $certStats = $stmt2->fetch(PDO::FETCH_ASSOC);

        return [
            'total_students'        => (int)($studentStats['total_students']    ?? 0),
            'active_students'       => (int)($studentStats['active_students']   ?? 0),
            'total_certificates'    => (int)($certStats['total_certificates']   ?? 0),
            'active_certificates'   => (int)($certStats['active_certificates']  ?? 0),
            'revoked_certificates'  => (int)($certStats['revoked_certificates'] ?? 0),
        ];
    }

    /**
     * Check whether $userId is authorised for the given action on university $universityId.
     *
     * $requiredRole: 'update' | 'delete' | 'students' | 'certificates' | 'stats'
     *
     * Authorization matrix:
     *   view         – public (always true)
     *   update       – admin only
     *   delete       – admin only
     *   students     – admin, that university
     *   certificates – admin, that university
     *   stats        – admin, that university
     */
    public function checkUniversityAuthorization(
        int    $userId,
        int    $universityId,
        string $requiredRole,
        string $callerRole,
        ?int   $callerUniversityId
    ): bool {
        if ($callerRole === 'admin') {
            return true;
        }

        $isSameUniversity = ($callerUniversityId !== null && $callerUniversityId === $universityId);

        switch ($requiredRole) {
            case 'view':
                return true;
            case 'update':
            case 'delete':
                return false; // admin-only
            case 'students':
            case 'certificates':
            case 'stats':
                return $isSameUniversity;
            default:
                return false;
        }
    }
}
