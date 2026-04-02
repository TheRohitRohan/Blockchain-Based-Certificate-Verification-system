<?php

namespace App;

use PDO;

class StudentService {
    private $db;

    /** @var string[] Allowed certificate status values */
    private static $allowedStatuses = ['active', 'revoked', 'expired'];

    /** @var string[] Allowed sort columns for certificate listing */
    private static $allowedSortCols = ['issue_date', 'course_name', 'status'];

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get student details by student row ID, joining users table.
     *
     * @param bool $includeInactive When true, returns the record even if is_active = FALSE (admin use).
     */
    public function getStudentById(int $id, bool $includeInactive = false): ?array {
        $activeClause = $includeInactive ? '' : 'AND s.is_active = TRUE';
        $stmt = $this->db->prepare("
            SELECT s.id, s.user_id, s.student_id, s.university_id, s.is_active, s.enrollment_date, s.date_of_birth,
                   u.full_name, u.email
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.id = ? $activeClause
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Update allowed student fields: full_name (users table) and date_of_birth (students table).
     * Returns false when student is not found OR nothing was actually updated.
     */
    public function updateStudent(int $studentId, array $data): bool {
        $student = $this->getStudentById($studentId, true);
        if (!$student) {
            return false;
        }

        $updated = false;

        // Update users.full_name if provided, non-empty, and actually changed
        if (isset($data['full_name'])) {
            $newFullName = trim($data['full_name']);
            if ($newFullName !== '' && $newFullName !== $student['full_name']) {
                $stmt = $this->db->prepare("
                    UPDATE users
                    SET full_name = ?
                    WHERE id = ?
                ");
                $stmt->execute([$newFullName, $student['user_id']]);
                if ($stmt->rowCount() > 0) {
                    $updated = true;
                }
            }
        }

        // Update students.date_of_birth if provided with a valid Y-m-d date and actually changed
        if (isset($data['date_of_birth'])) {
            $newDob = trim($data['date_of_birth']);
            if ($newDob !== '') {
                if (!self::isValidDate($newDob)) {
                    return false;
                }
                if ($newDob !== $student['date_of_birth']) {
                    $stmt = $this->db->prepare("UPDATE students SET date_of_birth = ? WHERE id = ?");
                    $stmt->execute([$newDob, $studentId]);
                    if ($stmt->rowCount() > 0) {
                        $updated = true;
                    }
                }
            }
        }

        return $updated;
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
     * Optional sort: sort (column), order (asc|desc)
     */
    public function getStudentCertificates(int $studentId, array $filters, int $page, int $limit): array {
        $page   = max(1, $page);
        $limit  = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $conditions = ['c.student_id = ?'];
        $params     = [$studentId];

        if (!empty($filters['status'])) {
            if (in_array($filters['status'], self::$allowedStatuses, true)) {
                $conditions[] = 'c.status = ?';
                $params[]     = $filters['status'];
            }
        }

        if (!empty($filters['course_name'])) {
            $courseName   = substr($filters['course_name'], 0, 100);
            $conditions[] = 'c.course_name LIKE ?';
            $params[]     = '%' . $courseName . '%';
        }

        if (!empty($filters['issue_date_from'])) {
            if (self::isValidDate($filters['issue_date_from'])) {
                $conditions[] = 'c.issue_date >= ?';
                $params[]     = $filters['issue_date_from'];
            }
        }

        if (!empty($filters['issue_date_to'])) {
            if (self::isValidDate($filters['issue_date_to'])) {
                $conditions[] = 'c.issue_date <= ?';
                $params[]     = $filters['issue_date_to'];
            }
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $sortCol = (isset($filters['sort']) && in_array($filters['sort'], self::$allowedSortCols, true))
            ? $filters['sort']
            : 'issue_date';
        $sortDir = (isset($filters['order']) && strtolower($filters['order']) === 'asc') ? 'ASC' : 'DESC';

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
            ORDER BY c.$sortCol $sortDir
            LIMIT ? OFFSET ?
        ");
        $dataStmt->execute($params);
        $certificates = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'       => $certificates,
            'pagination' => [
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

        // Use includeInactive = true so authorization works regardless of active status
        $student = $this->getStudentById($studentId, true);
        if (!$student) {
            return false;
        }

        $ownerUserId = isset($student['user_id']) ? (int)$student['user_id'] : null;

        $isSelf           = ($ownerUserId !== null && $ownerUserId === $userId);
        $isSameUniversity = ($callerUniversityId !== null && (int)$student['university_id'] === $callerUniversityId);

        switch ($requiredRole) {
            case 'view':
                // University-role callers can view students in their own university.
                // Students may only view their own profile (self).
                return $isSelf || ($callerRole === 'university' && $isSameUniversity);
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

    /**
     * Validate a date string matches Y-m-d format and is a real calendar date.
     */
    public static function isValidDate(string $date): bool {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }
}
