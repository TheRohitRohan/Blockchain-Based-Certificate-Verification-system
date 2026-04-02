-- Migration: Add soft delete column to students table
-- Run this migration to enable student deactivation without data loss

ALTER TABLE students 
ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER enrollment_date;

-- Add index for faster active-student queries
ALTER TABLE students
ADD INDEX idx_is_active (is_active);
