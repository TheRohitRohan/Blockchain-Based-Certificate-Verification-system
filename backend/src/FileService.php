<?php

namespace App;

/**
 * FileService - Handles user avatar uploads via Supabase Storage.
 *
 * All files are stored in the "upload" bucket.
 * Filename format: avatar_{userId}_{timestamp}.{ext}
 */
class FileService {

    private SupabaseStorage $supabaseStorage;
    private const MAX_AVATAR_SIZE    = 2 * 1024 * 1024; // 2 MB
    private const ALLOWED_MIME_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function __construct() {
        $this->supabaseStorage = new SupabaseStorage();
    }

    /**
     * Validate an uploaded image file (MIME + size).
     *
     * @param array $file  $_FILES entry
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateImageFile(array $file): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error'];
        }

        if ($file['size'] > self::MAX_AVATAR_SIZE) {
            return ['valid' => false, 'error' => 'File size exceeds 2MB limit'];
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            return ['valid' => false, 'error' => 'Only JPG and PNG files are allowed'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Upload an avatar for the given user ID to Supabase Storage ("upload" bucket).
     * Returns the public URL and the stored filename for optional rollback.
     *
     * @param int   $userId
     * @param array $file   $_FILES entry
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null, 'supabase_filename' => string|null]
     */
    public function uploadAvatar(int $userId, array $file): array {
        $validation = $this->validateImageFile($file);
        if (!$validation['valid']) {
            return ['success' => false, 'path' => null, 'error' => $validation['error'], 'supabase_filename' => null];
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'path' => null, 'error' => 'Invalid upload source', 'supabase_filename' => null];
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $ext      = self::ALLOWED_MIME_TYPES[$mimeType];

        // Filename format: avatar_{userId}_{timestamp}.{ext}
        $filename = "avatar_{$userId}_" . time() . ".{$ext}";

        try {
            $publicUrl = $this->supabaseStorage->uploadFile('upload', $file['tmp_name'], $filename, $mimeType);
        } catch (\Exception $e) {
            error_log("Avatar upload to Supabase failed: " . $e->getMessage());
            return ['success' => false, 'path' => null, 'error' => 'Failed to upload avatar: ' . $e->getMessage(), 'supabase_filename' => null];
        }

        return ['success' => true, 'path' => $publicUrl, 'error' => null, 'supabase_filename' => $filename];
    }

    /**
     * Delete an avatar file from Supabase Storage ("upload" bucket).
     * Used for rollback when the DB update fails after a successful upload.
     *
     * @param string $filename Filename in the "upload" bucket
     * @return bool
     */
    public function deleteAvatarFile(string $filename): bool {
        try {
            return $this->supabaseStorage->deleteFile('upload', $filename);
        } catch (\Exception $e) {
            error_log("Avatar delete from Supabase failed: " . $e->getMessage());
            return false;
        }
    }
}
