<?php

namespace App;

use PDO;

class StudentService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get student details by student row ID, joining users table.
     */
    public function getStudentById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT s.id, s.user_id, s.student_id, s.university_id, s.is_active, s.enrollment_date, s.date_of_birth,
                   u.full_name, u.email
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update allowed student fields: full_name (users table) and date_of_birth (students table).
     * Returns false when there is nothing to update.
     */
    public function updateStudent(int $studentId, array $data): bool {
        $student = $this->getStudentById($studentId);
        if (!$student) {
            return false;
        }

        // Update users.full_name if provided
        if (isset($data['full_name']) && trim($data['full_name']) !== '') {
            $stmt = $this->db->prepare("
                UPDATE users
                SET full_name = ?
                WHERE id = (SELECT user_id FROM students WHERE id = ?)
            ");
            $stmt->execute([trim($data['full_name']), $studentId]);
        }

        // Update students.date_of_birth if provided
        if (isset($data['date_of_birth']) && trim($data['date_of_birth']) !== '') {
            $stmt = $this->db->prepare("UPDATE students SET date_of_birth = ? WHERE id = ?");
            $stmt->execute([trim($data['date_of_birth']), $studentId]);
        }

        return true;
    }

    /**
     * Soft-delete a student by setting is_active = FALSE.
     */
    public function softDeleteStudent(int $studentId): bool {
        $stmt = $this->db->prepare("UPDATE students SET is_active = FALSE WHERE id = ?");
        return $stmt->execute([$studentId]);
    }

    /**
     * Get certificates belonging to a student with pagination and optional filtering.
     *
     * Supported filters: status, course_name, issue_date_from, issue_date_to
     */
    public function getStudentCertificates(int $studentId, array $filters, int $page, int $limit): array {
        $page   = max(1, $page);
        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $conditions = ['c.student_id = ?'];
        $params     = [$studentId];

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

        // Total count
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM certificates c $where
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Data query
        $params[] = $limit;
        $params[] = $offset;
        $dataStmt = $this->db->prepare("
            SELECT c.certificate_id, c.course_name, c.degree_type, c.issue_date, c.status,
                   un.name AS university_name
            FROM certificates c
            JOIN universities un ON c.university_id = un.id
            $where
            ORDER BY c.issue_date DESC
            LIMIT ? OFFSET ?
        ");
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
     * Check whether $userId is authorised to access student $studentId.
     *
     * $requiredRole: 'view' | 'update' | 'delete' | 'certificates'
     *
     * Authorization matrix:
     *   view         – admin, self, same university
     *   update       – admin, self
     *   delete       – admin only
     *   certificates – admin, self, university of that student
     */
    public function checkStudentAuthorization(
        int    $userId,
        int    $studentId,
        string $requiredRole,
        string $callerRole,
        ?int   $callerUniversityId
    ): bool {
        if ($callerRole === 'admin') {
            return true;
        }

        $student = $this->getStudentById($studentId);
        if (!$student) {
            return false;
        }

        $ownerUserId = isset($student['user_id']) ? (int)$student['user_id'] : null;

        $isSelf           = ($ownerUserId !== null && $ownerUserId === $userId);
        $isSameUniversity = ($callerUniversityId !== null && (int)$student['university_id'] === $callerUniversityId);

        switch ($requiredRole) {
            case 'view':
                return $isSelf || $isSameUniversity;
            case 'update':
                return $isSelf;
            case 'delete':
                return false; // admin-only; already handled above
            case 'certificates':
                return $isSelf || ($callerRole === 'university' && $isSameUniversity);
            default:
                return false;
        }
    }
}
