-- Migration: Add avatar_path column to users table
ALTER TABLE users 
ADD COLUMN avatar_path VARCHAR(500) NULL AFTER full_name;
