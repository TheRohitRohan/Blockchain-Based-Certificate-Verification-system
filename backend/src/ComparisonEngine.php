<?php

namespace App;

use PDO;

class ComparisonEngine {
    private $db;
    private $metadataService;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->metadataService = new MetadataService();
    }
    
    /**
     * Compare PDF with database record
     */
    public function comparePDFWithDatabase(string $pdfPath, string $certificateId): array {
        try {
            // Get database record
            $dbRecord = $this->getCertificateRecord($certificateId);
            
            if (!$dbRecord) {
                return [
                    'match' => false,
                    'message' => 'Certificate not found in database',
                    'differences' => []
                ];
            }
            
            // Extract metadata from PDF
            $pdfService = new PDFService();
            $pdfMetadata = $pdfService->extractMetadata($pdfPath);
            
            if (!$pdfMetadata) {
                return [
                    'match' => false,
                    'message' => 'Could not extract metadata from PDF',
                    'differences' => []
                ];
            }
            
            // Build database metadata
            $dbMetadata = $this->metadataService->buildMetadata([
                'certificate_id' => $dbRecord['certificate_id'],
                'student_id' => $dbRecord['student_id'] ?? '',
                'student_name' => $dbRecord['student_name'] ?? '',
                'course_name' => $dbRecord['course_name'],
                'degree_type' => $dbRecord['degree_type'] ?? '',
                'issue_date' => $dbRecord['issue_date'],
                'university_code' => $dbRecord['university_code'] ?? '',
                'university_name' => $dbRecord['university_name'] ?? ''
            ]);
            
            // Compare metadata
            $comparison = $this->metadataService->compareMetadata($dbMetadata, $pdfMetadata);
            
            // Compare PDF hash
            $pdfHash = $pdfService->calculatePDFHash($pdfPath);
            $pdfHashMatch = ($pdfHash === $dbRecord['pdf_hash']);
            
            // Build result
            $allMatch = $comparison['matches'] && $pdfHashMatch;
            
            return [
                'match' => $allMatch,
                'message' => $allMatch ? 'PDF matches database record' : 'PDF differs from database record',
                'metadata_match' => $comparison['matches'],
                'metadata_differences' => $comparison['differences'] ?? [],
                'pdf_hash_match' => $pdfHashMatch,
                'pdf_hash_db' => $dbRecord['pdf_hash'],
                'pdf_hash_calculated' => $pdfHash,
                'field_matches' => $this->getFieldMatches($dbMetadata, $pdfMetadata)
            ];
            
        } catch (\Exception $e) {
            error_log("Comparison failed: " . $e->getMessage());
            return [
                'match' => false,
                'message' => 'Comparison error: ' . $e->getMessage(),
                'differences' => []
            ];
        }
    }
    
    /**
     * Get field-by-field match status
     */
    private function getFieldMatches(array $dbMetadata, array $pdfMetadata): array {
        $matches = [];
        $allKeys = array_unique(array_merge(array_keys($dbMetadata), array_keys($pdfMetadata)));
        
        foreach ($allKeys as $key) {
            if ($key === 'schema_version') {
                continue; // Skip schema version in comparison
            }
            
            $dbValue = $dbMetadata[$key] ?? null;
            $pdfValue = $pdfMetadata[$key] ?? null;
            
            $matches[$key] = [
                'match' => ($dbValue === $pdfValue),
                'db_value' => $dbValue,
                'pdf_value' => $pdfValue
            ];
        }
        
        return $matches;
    }
    
    /**
     * Get certificate record
     */
    private function getCertificateRecord(string $certificateId): ?array {
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                s.student_id,
                u.full_name as student_name,
                un.name as university_name,
                un.code as university_code
            FROM certificates c
            JOIN students s ON c.student_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN universities un ON c.university_id = un.id
            WHERE c.certificate_id = ?
        ");
        $stmt->execute([$certificateId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;  // Convert false to null for type safety
    }
}
