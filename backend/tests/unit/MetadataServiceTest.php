<?php

use PHPUnit\Framework\TestCase;
use App\MetadataService;

/**
 * Suite 2 — Unit: MetadataService
 *
 * Pure logic tests — no DB, no network.
 */
class MetadataServiceTest extends TestCase
{
    private MetadataService $svc;

    protected function setUp(): void
    {
        $this->svc = new MetadataService();
    }

    // ─────────────────────────────────────────────────────────────────

    public function testBuildMetadataHasAllRequiredKeys(): void
    {
        $meta = $this->svc->buildMetadata([
            'certificate_id'  => 'CERT-TEST-001',
            'student_id'      => 'STU-001',
            'student_name'    => 'Ahmed Al-Rashidi',
            'course_name'     => 'Bachelor of Computer Science',
            'degree_type'     => 'Bachelor',
            'issue_date'      => '2024-06-15',
            'university_code' => 'UOT',
            'university_name' => 'University of Technology',
        ]);

        $required = [
            'certificate_id', 'student_id', 'student_name',
            'course_name', 'degree_type', 'issue_date',
            'university_code', 'university_name', 'schema_version',
        ];

        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $meta, "Missing key: {$key}");
        }
    }

    public function testBuildMetadataFiltersEmptyFields(): void
    {
        $meta = $this->svc->buildMetadata([
            'certificate_id'  => 'CERT-TEST-002',
            'student_id'      => 'STU-001',
            'student_name'    => 'Ahmed Al-Rashidi',
            'course_name'     => 'Computer Science',
            'degree_type'     => '',           // empty — should be filtered
            'issue_date'      => '2024-06-15',
            'university_code' => 'UOT',
            'university_name' => 'University of Technology',
        ]);

        $this->assertArrayNotHasKey('degree_type', $meta);
    }

    public function testGenerateMetadataJsonIsDeterministic(): void
    {
        $data = [
            'certificate_id'  => 'CERT-DET-001',
            'student_id'      => 'STU-001',
            'student_name'    => 'Ahmed Al-Rashidi',
            'course_name'     => 'Computer Science',
            'degree_type'     => 'Bachelor',
            'issue_date'      => '2024-06-15',
            'university_code' => 'UOT',
            'university_name' => 'University of Technology',
        ];

        $json1 = $this->svc->generateMetadataJson($this->svc->buildMetadata($data));
        $json2 = $this->svc->generateMetadataJson($this->svc->buildMetadata($data));

        $this->assertSame($json1, $json2, 'Metadata JSON is not deterministic');
    }

    public function testGenerateMetadataJsonKeysAreSorted(): void
    {
        // Pass fields in deliberately unsorted order
        $meta = $this->svc->buildMetadata([
            'university_name' => 'University of Technology',
            'certificate_id'  => 'CERT-SORT-001',
            'student_name'    => 'Ahmed Al-Rashidi',
            'student_id'      => 'STU-001',
            'course_name'     => 'Computer Science',
            'degree_type'     => 'Bachelor',
            'issue_date'      => '2024-06-15',
            'university_code' => 'UOT',
        ]);

        $json    = $this->svc->generateMetadataJson($meta);
        $decoded = json_decode($json, true);
        $keys    = array_keys($decoded);
        $sorted  = $keys;
        sort($sorted);

        $this->assertSame($sorted, $keys, 'Keys are not alphabetically sorted');
    }

    public function testGenerateMetadataHashFormat(): void
    {
        $meta = $this->svc->buildMetadata([
            'certificate_id'  => 'CERT-HASH-001',
            'student_id'      => 'STU-001',
            'student_name'    => 'Ahmed Al-Rashidi',
            'course_name'     => 'Computer Science',
            'issue_date'      => '2024-06-15',
            'university_code' => 'UOT',
            'university_name' => 'University of Technology',
        ]);

        $hash = $this->svc->generateMetadataHash($meta);

        $this->assertStringStartsWith('0x', $hash);
        $this->assertSame(66, strlen($hash), 'Hash must be 66 chars (0x + 64 hex)');
    }

    public function testCompareMetadataFindsNoDifferenceOnIdentical(): void
    {
        $data = [
            'certificate_id'  => 'CERT-CMP-001',
            'student_id'      => 'STU-001',
            'student_name'    => 'Ahmed Al-Rashidi',
            'course_name'     => 'Computer Science',
            'issue_date'      => '2024-06-15',
            'university_code' => 'UOT',
            'university_name' => 'University of Technology',
        ];

        $meta1 = $this->svc->buildMetadata($data);
        $meta2 = $this->svc->buildMetadata($data);

        $result = $this->svc->compareMetadata($meta1, $meta2);

        $this->assertTrue($result['matches']);
        $this->assertEmpty($result['differences']);
    }

    public function testCompareMetadataFindsDifferenceOnChange(): void
    {
        $base = [
            'certificate_id'  => 'CERT-CMP-002',
            'student_id'      => 'STU-001',
            'student_name'    => 'Ahmed Al-Rashidi',
            'course_name'     => 'Computer Science',
            'issue_date'      => '2024-06-15',
            'university_code' => 'UOT',
            'university_name' => 'University of Technology',
        ];

        $meta1 = $this->svc->buildMetadata($base);

        $changed = $base;
        $changed['course_name'] = 'Mechanical Engineering';
        $meta2 = $this->svc->buildMetadata($changed);

        $result = $this->svc->compareMetadata($meta1, $meta2);

        $this->assertFalse($result['matches']);
        $this->assertArrayHasKey('course_name', $result['differences']);
    }

    public function testNormalizeDateConvertsFormats(): void
    {
        $meta = $this->svc->buildMetadata([
            'certificate_id'  => 'CERT-DATE-001',
            'student_id'      => 'STU-001',
            'student_name'    => 'Ahmed Al-Rashidi',
            'course_name'     => 'Computer Science',
            'issue_date'      => 'January 15, 2024',
            'university_code' => 'UOT',
            'university_name' => 'University of Technology',
        ]);

        $this->assertSame('2024-01-15', $meta['issue_date']);
    }

    public function testSchemaVersionIsSet(): void
    {
        $meta = $this->svc->buildMetadata([
            'certificate_id'  => 'CERT-VER-001',
            'student_id'      => 'STU-001',
            'student_name'    => 'Ahmed Al-Rashidi',
            'course_name'     => 'Computer Science',
            'issue_date'      => '2024-06-15',
            'university_code' => 'UOT',
            'university_name' => 'University of Technology',
        ]);

        $this->assertSame('1.0', $meta['schema_version']);
    }
}
