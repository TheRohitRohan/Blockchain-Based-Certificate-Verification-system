<?php

namespace App;

use kornrunner\Keccak;

class MetadataService {
    private const SCHEMA_VERSION = '1.0';
    
    /**
     * Build canonical metadata JSON from certificate data
     */
    public function buildMetadata(array $data): array {
        // Normalize and sort all fields
        $metadata = [
            'schema_version' => self::SCHEMA_VERSION,
            'certificate_id' => $this->normalizeString($data['certificate_id'] ?? ''),
            'student_id' => $this->normalizeString($data['student_id'] ?? ''),
            'student_name' => $this->normalizeString($data['student_name'] ?? ''),
            'course_name' => $this->normalizeString($data['course_name'] ?? ''),
            'degree_type' => $this->normalizeString($data['degree_type'] ?? ''),
            'issue_date' => $this->normalizeDate($data['issue_date'] ?? ''),
            'university_code' => $this->normalizeString($data['university_code'] ?? ''),
            'university_name' => $this->normalizeString($data['university_name'] ?? '')
        ];
        
        // Remove null/empty optional fields
        $metadata = array_filter($metadata, function($value) {
            return $value !== null && $value !== '';
        });
        
        return $metadata;
    }
    
    /**
     * Normalize metadata to canonical form
     */
    public function normalizeMetadata(array $metadata): array {
        // Ensure schema version
        if (!isset($metadata['schema_version'])) {
            $metadata['schema_version'] = self::SCHEMA_VERSION;
        }
        
        // Normalize all string fields
        $normalized = [];
        foreach ($metadata as $key => $value) {
            if (is_string($value)) {
                $normalized[$key] = $this->normalizeString($value);
            } elseif (in_array($key, ['issue_date'])) {
                $normalized[$key] = $this->normalizeDate($value);
            } else {
                $normalized[$key] = $value;
            }
        }
        
        // Sort by key for consistency
        ksort($normalized);
        
        return $normalized;
    }
    
    /**
     * Generate canonical JSON string from metadata
     */
    public function generateMetadataJson(array $metadata): string {
        $normalized = $this->normalizeMetadata($metadata);
        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Generate Keccak256 hash of metadata
     */
    public function generateMetadataHash(array $metadata): string {
        $json = $this->generateMetadataJson($metadata);
        return '0x' . Keccak::hash($json, 256);
    }
    
    /**
     * Extract metadata from JSON string
     */
    public function extractMetadata(string $jsonString): ?array {
        $metadata = json_decode($jsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        // Validate schema version
        if (isset($metadata['schema_version'])) {
            $version = $metadata['schema_version'];
            if (!in_array($version, ['1.0'])) {
                throw new \Exception("Unsupported metadata schema version: {$version}");
            }
        }
        
        return $this->normalizeMetadata($metadata);
    }
    
    /**
     * Compare two metadata arrays
     */
    public function compareMetadata(array $metadata1, array $metadata2): array {
        $differences = [];
        $allKeys = array_unique(array_merge(array_keys($metadata1), array_keys($metadata2)));
        
        foreach ($allKeys as $key) {
            $val1 = $metadata1[$key] ?? null;
            $val2 = $metadata2[$key] ?? null;
            
            if ($val1 !== $val2) {
                $differences[$key] = [
                    'expected' => $val1,
                    'actual' => $val2,
                    'match' => false
                ];
            }
        }
        
        return [
            'matches' => empty($differences),
            'differences' => $differences
        ];
    }
    
    /**
     * Normalize string (trim, uppercase, remove extra spaces)
     */
    private function normalizeString(string $value): string {
        return trim($value);
    }
    
    /**
     * Normalize date to YYYY-MM-DD format
     */
    private function normalizeDate($date): string {
        if (empty($date)) {
            return '';
        }
        
        // Try to parse various date formats
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }
        
        return date('Y-m-d', $timestamp);
    }
    
    /**
     * Get current schema version
     */
    public function getSchemaVersion(): string {
        return self::SCHEMA_VERSION;
    }
}
