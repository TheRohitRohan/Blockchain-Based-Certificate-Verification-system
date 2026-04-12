-- Migration 005: Remove Orphaned/Superseded Fields from Universities Table
-- 
-- These fields are no longer used:
-- - admin_email, admin_password_hash, admin_created_at: Credentials moved to users table (role='university')
-- - signing_cert_path, signing_cert_password_encrypted: Replaced by university_keys table
--
-- wallet_address is kept for potential future blockchain integration

ALTER TABLE universities
DROP COLUMN admin_email,
DROP COLUMN admin_password_hash,
DROP COLUMN admin_created_at,
DROP COLUMN signing_cert_path,
DROP COLUMN signing_cert_password_encrypted;
