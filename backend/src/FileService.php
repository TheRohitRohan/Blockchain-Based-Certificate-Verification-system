<?php

namespace App;

class FileService {

    private string $avatarDir;
    private const MAX_AVATAR_SIZE = 2 * 1024 * 1024; // 2 MB
    private const ALLOWED_MIME_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function __construct() {
        $this->avatarDir = __DIR__ . '/../storage/avatars';
        if (!is_dir($this->avatarDir)) {
            mkdir($this->avatarDir, 0755, true);
        }
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

        $maxSize = self::MAX_AVATAR_SIZE;
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds 2MB limit'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            return ['valid' => false, 'error' => 'Only JPG and PNG files are allowed'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Upload an avatar for the given user ID.
     * Deletes any existing avatar for that user first.
     *
     * @param int   $userId
     * @param array $file   $_FILES entry
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public function uploadAvatar(int $userId, array $file): array {
        $validation = $this->validateImageFile($file);
        if (!$validation['valid']) {
            return ['success' => false, 'path' => null, 'error' => $validation['error']];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $ext = self::ALLOWED_MIME_TYPES[$mimeType];

        // Delete old avatar if it exists
        $this->deleteOldAvatar($userId);

        $filename = $userId . '.' . $ext;
        $destPath = $this->avatarDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'path' => null, 'error' => 'Failed to save avatar'];
        }

        // Return relative path for storage in DB
        $relativePath = 'storage/avatars/' . $filename;
        return ['success' => true, 'path' => $relativePath, 'error' => null];
    }

    /**
     * Delete the existing avatar file(s) for a user.
     *
     * @param int $userId
     */
    public function deleteOldAvatar(int $userId): void {
        foreach (array_values(self::ALLOWED_MIME_TYPES) as $ext) {
            $path = $this->avatarDir . '/' . $userId . '.' . $ext;
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
