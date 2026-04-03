<?php

namespace App;

/**
 * SupabaseStorage - Handles file uploads and deletions via Supabase Storage REST API.
 *
 * Supports the "upload" storage bucket.
 * Uses CURL (no external SDK). Requires SUPABASE_URL and SUPABASE_SERVICE_KEY env vars.
 */
class SupabaseStorage {

    private string $supabaseUrl;
    private string $serviceKey;
    private string $publicBaseUrl;

    public function __construct() {
        $config = require __DIR__ . '/../config.php';

        $url        = $config['supabase']['url']         ?? null;
        $serviceKey = $config['supabase']['service_key'] ?? null;

        if (empty($url) || empty($serviceKey)) {
            throw new \RuntimeException(
                'Supabase credentials not configured. '
                . 'Set SUPABASE_URL and SUPABASE_SERVICE_KEY environment variables.'
            );
        }

        $this->supabaseUrl = rtrim($url, '/');
        $this->serviceKey  = $serviceKey;

        // Use SUPABASE_PUBLIC_URL if set, otherwise build the default from SUPABASE_URL
        $publicUrl = $config['supabase']['public_url'] ?? null;
        $this->publicBaseUrl = !empty($publicUrl)
            ? rtrim($publicUrl, '/')
            : $this->supabaseUrl . '/storage/v1/object/public';
    }

    /**
     * Upload a file to Supabase Storage via REST API using streaming to avoid
     * loading the entire file into memory.
     *
     * @param string $bucket      Bucket name (e.g. "upload")
     * @param string $fileTmpPath Path to the source file (temp or local)
     * @param string $fileName    Destination filename in the bucket
     * @param string $contentType MIME type of the file
     * @return string Public URL of the uploaded file
     * @throws \Exception On upload failure
     */
    public function uploadFile(string $bucket, string $fileTmpPath, string $fileName, string $contentType): string {
        if (!file_exists($fileTmpPath) || !is_readable($fileTmpPath)) {
            throw new \Exception("File not found or not readable: {$fileTmpPath}");
        }

        $fileSize = filesize($fileTmpPath);
        if ($fileSize === false) {
            throw new \Exception("Failed to determine file size: {$fileTmpPath}");
        }

        $fileHandle = fopen($fileTmpPath, 'rb');
        if ($fileHandle === false) {
            throw new \Exception("Failed to open file for reading: {$fileTmpPath}");
        }

        $endpoint = "{$this->supabaseUrl}/storage/v1/object/{$bucket}/{$fileName}";

        $ch = curl_init();
        try {
            curl_setopt_array($ch, [
                CURLOPT_URL            => $endpoint,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_UPLOAD         => true,
                CURLOPT_INFILE         => $fileHandle,
                CURLOPT_INFILESIZE     => $fileSize,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->serviceKey,
                    'Content-Type: ' . $contentType,
                    'Content-Length: ' . $fileSize,
                ],
            ]);

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
        } finally {
            fclose($fileHandle);
            curl_close($ch);
        }

        if ($curlError) {
            throw new \Exception("CURL error during upload: {$curlError}");
        }

        if ($httpCode !== 200) {
            $errMsg = "Supabase upload failed (HTTP {$httpCode})";
            if ($response) {
                $parsed = json_decode($response, true);
                if (isset($parsed['message'])) {
                    $errMsg .= ': ' . $parsed['message'];
                }
            }
            throw new \Exception($errMsg);
        }

        return $this->getPublicUrl($bucket, $fileName);
    }

    /**
     * Get the public URL for a file stored in a Supabase bucket.
     *
     * @param string $bucket   Bucket name
     * @param string $fileName File name
     * @return string Public URL
     */
    public function getPublicUrl(string $bucket, string $fileName): string {
        return "{$this->publicBaseUrl}/{$bucket}/{$fileName}";
    }

    /**
     * Delete a file from Supabase Storage.
     *
     * @param string $bucket   Bucket name
     * @param string $fileName File name
     * @return bool True on success (HTTP 200/204), false otherwise
     */
    public function deleteFile(string $bucket, string $fileName): bool {
        try {
            $endpoint = "{$this->supabaseUrl}/storage/v1/object/{$bucket}/{$fileName}";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $endpoint,
                CURLOPT_CUSTOMREQUEST  => 'DELETE',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->serviceKey,
                ],
            ]);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 200 and 204 both indicate successful deletion
            return in_array($httpCode, [200, 204]);
        } catch (\Exception $e) {
            error_log("SupabaseStorage::deleteFile failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a file from Supabase Storage by its public URL.
     * Extracts the filename from the URL path (last segment after /upload/).
     *
     * @param string $fileUrl Public URL of the file
     * @return bool
     */
    public function deleteFileByUrl(string $fileUrl): bool {
        if (preg_match('#/upload/(.+)$#', $fileUrl, $matches)) {
            return $this->deleteFile('upload', $matches[1]);
        }
        error_log("SupabaseStorage::deleteFileByUrl: cannot parse filename from URL: {$fileUrl}");
        return false;
    }
}
