-- Supabase Storage Integration Migration
-- Run these statements to add the file URL columns required for Supabase Storage support.
-- All files are stored in the "upload" bucket in Supabase.

-- Add file_url column to certificates table
-- Stores the public URL of the certificate PDF in Supabase Storage
ALTER TABLE certificates
    ADD COLUMN file_url TEXT NULL;

-- Add avatar_url column to users table
-- Stores the public URL of the user avatar in Supabase Storage
ALTER TABLE users
    ADD COLUMN avatar_url TEXT NULL;
