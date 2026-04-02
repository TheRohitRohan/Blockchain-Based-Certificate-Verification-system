-- Migration: Add soft delete column to students table
-- Run this migration to enable student deactivation without data loss

ALTER TABLE students 
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER enrollment_date;

-- Add index for faster active-student queries
ALTER TABLE students
ADD INDEX idx_is_active (is_active);

-- Add index on certificates.issue_date for date-range filtering performance
-- (student_id, university_id, status are already indexed in the base schema)
ALTER TABLE certificates
ADD INDEX idx_issue_date (issue_date);

-- Composite index to speed up filtered listing by university + date
ALTER TABLE certificates
ADD INDEX idx_university_issue_date (university_id, issue_date);

-- Composite index to speed up filtered listing by student + date
ALTER TABLE certificates
ADD INDEX idx_student_issue_date (student_id, issue_date);
