-- Migration 003: Add Supabase Storage URL columns
-- Adds file_url to certificates for storing Supabase public URLs,
-- and avatar_url to users for storing Supabase avatar URLs.

ALTER TABLE certificates
    ADD COLUMN file_url TEXT NULL;

ALTER TABLE users
    ADD COLUMN avatar_url TEXT NULL;
