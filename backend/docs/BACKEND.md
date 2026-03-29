# Backend Documentation

## Overview

The backend is a PHP REST API that handles certificate issuance, verification, and management. It integrates with MySQL database and Ethereum blockchain.

---

## Project Structure

```
backend/
├── api/
│   └── index.php          # Main API router
├── src/
│   ├── Auth.php           # JWT authentication
│   ├── Blockchain.php     # Ethereum blockchain interaction
│   ├── Cache.php          # File/Redis caching
│   ├── CertificateService.php  # Certificate business logic
│   ├── ComparisonEngine.php   # Certificate comparison
│   ├── Database.php       # MySQL singleton connection
│   ├── MetadataService.php    # Certificate metadata handling
│   ├── PDFGenerator.php   # PDF creation
│   ├── PDFService.php     # PDF manipulation
│   ├── PublicVerificationService.php  # Public verification
│   ├── SignatureService.php   # Digital signatures
│   └── VerificationEngine.php # Verification logic
├── storage/
│   ├── certificates/      # Generated PDFs
│   ├── qr_codes/         # QR code images
│   └── cache/            # Verification cache
├── database/
│   └── schema.sql        # Database schema
└── config.php            # Configuration
```

---

## Core Classes

### 1. Database.php

**Purpose**: MySQL database connection singleton

**Features:**
- Singleton pattern for single connection instance
- PDO persistent connections for connection pooling
- Prepared statements for performance and security

**Key Methods:**
```php
// Get singleton instance
$db = Database::getInstance()->getConnection();

// Usage example
$stmt = $db->prepare("SELECT * FROM certificates WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

### 2. Auth.php

**Purpose**: User authentication and JWT token management

**Key Methods:**
```php
// Login - returns user data or null
$user = $auth->login($email, $password);

// Register new user
$auth->register($data); // data: username, email, password, role, full_name

// Generate JWT token
$token = $auth->generateToken($user);

// Verify JWT token - returns payload or null
$payload = $auth->verifyToken($token);
```

**JWT Structure:**
- Header: `{"typ":"JWT","alg":"HS256"}`
- Payload: `{"user_id","email","role","exp"}`
- Signature: HMAC-SHA256

---

### 3. CertificateService.php

**Purpose**: Main certificate business logic - creation, retrieval, revocation

**Key Methods:**

#### Create Certificate
```php
$certService = new CertificateService();
$result = $certService->createCertificate([
    'student_id' => 1,
    'university_id' => 1,
    'course_name' => 'Computer Science',
    'degree_type' => 'Bachelor',
    'issue_date' => '2024-12-01'
]);

// Returns:
// [
//     'success' => true,
//     'certificate_id' => 'CERT-ABC123',
//     'certificate_hash' => '0x...',
//     'tx_hash' => '0x...'
// ]
```

**Creation Process:**
1. Get student info from database
2. Generate unique certificate ID
3. Build metadata (JSON)
4. Generate PDF using mPDF
5. Calculate hashes (Keccak256)
6. Sign PDF (optional)
7. Store on blockchain
8. Save to database

#### Verify Certificate
```php
$result = $certService->verifyCertificate($certificateId, $hash);

// Returns: valid, status, message, certificate details
```

#### Revoke Certificate
```php
$certService->revokeCertificate($certificateId, $adminUserId);
// Updates status to 'revoked', clears cache
```

---

### 4. Blockchain.php

**Purpose**: Interact with Ethereum smart contract

**Key Methods:**

```php
$bc = new Blockchain();

// Issue certificate on blockchain
$result = $bc->issueCertificate([
    'certificate_id' => 'CERT-ABC',
    'student_name' => 'John Doe',
    'university_name' => 'Test University',
    'course_name' => 'Computer Science',
    'issue_date' => '2024-12-01'
]);

// Verify certificate on blockchain
$isValid = $bc->verifyCertificate($certificateId, $hash);

// Get current block number
$blockNumber = $bc->getCurrentBlock();

// Revoke certificate
$bc->revokeCertificate($certificateId);
```

**Mock Mode:** If blockchain is unavailable, returns mock data for testing.

---

### 5. VerificationEngine.php

**Purpose**: Complete verification flow for certificates

**Verification Process:**
1. Extract metadata from PDF (if uploaded)
2. Check cache for previous verification result
3. Fetch database record (uses lightweight query for quick verification)
4. Compare metadata with database record
5. Calculate and compare PDF hash
6. Verify on blockchain (with 5-minute cache)
7. Check revocation status (uses boolean flag)
8. Cache result

**Performance Optimizations:**
- **Lightweight queries**: Quick ID-based verification uses only 6 fields instead of 30+
- **Blockchain caching**: Results cached for 5 minutes (configurable via `BLOCKCHAIN_CACHE_TTL`)
- **Cache warming**: New certificates are pre-cached on creation to avoid cold-start
- **Smart invalidation**: All cache keys cleared on revocation

**Key Method:**
```php
$result = $verificationEngine->verifyUploadedPDF($pdfPath);
// or
$result = $verificationEngine->verifyByCertificateId($certificateId);
```

---

### 6. PDFService.php

**Purpose**: Generate and manipulate PDF certificates

**Key Methods:**
```php
$pdf = new PDFService();

// Generate PDF from template
$path = $pdf->generateCertificatePDF($certificateId, $data);

// Embed metadata into PDF
$pdf->embedMetadata($pdfPath, $metadata);

// Extract metadata from PDF
$metadata = $pdf->extractMetadata($pdfPath);

// Extract text from PDF
$text = $pdf->extractText($pdfPath);

// Calculate PDF hash (Keccak256)
$hash = $pdf->calculatePDFHash($pdfPath);
```

**Dependencies:**
- mPDF: PDF generation
- FPDI: PDF manipulation
- Smalot/PdfParser: Text extraction

---

### 7. MetadataService.php

**Purpose**: Handle certificate metadata

**Key Methods:**
```php
$meta = new MetadataService();

// Build normalized metadata
$metadata = $meta->buildMetadata([
    'certificate_id' => 'CERT-ABC',
    'student_id' => 'STU001',
    'student_name' => 'John Doe',
    'course_name' => 'Computer Science',
    'degree_type' => 'Bachelor',
    'issue_date' => '2024-12-01',
    'university_code' => 'UNI001',
    'university_name' => 'Test University'
]);

// Generate JSON
$json = $meta->generateMetadataJson($metadata);

// Generate hash
$hash = $meta->generateMetadataHash($metadata);
```

---

### 8. Cache.php

**Purpose**: Cache verification results (file or Redis)

**Features:**
- File-based or Redis backend (configurable)
- Automatic fallback from Redis to file cache
- TTL (Time-To-Live) support
- Cache warming on certificate creation

**Key Methods:**
```php
$cache = Cache::getInstance();

// Set cache
$cache->set("verify:CERT-ABC", $result, 3600); // 1 hour TTL

// Get cache
$result = $cache->get("verify:CERT-ABC");

// Delete cache
$cache->delete("verify:CERT-ABC");

// Flush all
$cache->flush();

// Remember (cache-aside pattern)
$value = $cache->remember("key", function() {
    return expensiveOperation();
}, 3600);
```

**Configuration** (`config.php`):
```php
'cache' => [
    'driver' => 'file',           // 'file' or 'redis'
    'ttl' => 3600,               // Default TTL (1 hour)
    'verification_ttl' => 3600   // Verification result TTL
]
```

---

## API Endpoints

### Authentication

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/auth/login` | POST | No | User login |
| `/api/auth/register` | POST | No | User registration |

### Certificates

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/certificates` | GET | JWT | List certificates |
| `/api/certificates/create` | POST | JWT | Issue certificate |
| `/api/certificates/verify` | POST | No | Verify certificate |
| `/api/certificates/revoke` | POST | JWT | Revoke certificate |
| `/api/certificates/download` | GET | JWT | Download PDF |

### Public

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/public/verify` | GET | No | Public verification |
| `/api/public/certificate/download` | GET | No | Download PDF |

### Universities

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/universities` | GET | No | List universities |
| `/api/universities` | POST | JWT | Create university |

### Students

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/api/students` | GET | JWT | List students |
| `/api/students` | POST | JWT | Create student |

---

## Database Schema

### users
```sql
- id, username, email, password_hash
- role: admin/university/student
- full_name, university_id
```

### universities
```sql
- id, name, code, address
- contact_email, contact_phone
- wallet_address, is_active
```

### students
```sql
- id, user_id, student_id
- university_id, enrollment_date
```

### certificates
```sql
- id, certificate_id (unique)
- student_id, university_id
- course_name, degree_type, issue_date
- certificate_hash, blockchain_tx_hash
- pdf_path, qr_code_path
- status (active/revoked)
- metadata_hash, pdf_hash, onchain_hash
- metadata_json, signature_status
- block_number, chain_id, schema_version
- is_revoked (boolean for fast lookup)

-- Indexes for performance:
- idx_certificate_id, idx_student, idx_university
- idx_hash, idx_status
- idx_onchain_hash, idx_pdf_hash, idx_metadata_hash
- idx_block_number
- idx_cert_status (compound)
```

### verification_logs
```sql
- id, certificate_id, verifier_ip
- verification_method, verification_result
- verification_details (JSON)
```

---

## Security Features

1. **JWT Authentication**: Token-based stateless auth
2. **Password Hashing**: PHP's password_hash()
3. **Role-Based Access**: Middleware checks roles
4. **Input Validation**: Sanitized inputs
5. **SQL Injection Prevention**: Prepared statements
6. **CORS Headers**: Configured for frontend

---

## Performance Optimizations

The system includes several optimizations for fast certificate verification:

| Feature | Description |
|---------|-------------|
| **Database Indexes** | Optimized indexes on certificate_id, onchain_hash, pdf_hash, status |
| **Lightweight Queries** | Quick verification uses 6 fields instead of 30+ |
| **PDO Persistent Connections** | Connection pooling for faster DB access |
| **Blockchain Caching** | 5-minute TTL for blockchain verification results |
| **Cache Warming** | New certificates pre-cached on creation |
| **API Compression** | Gzip compression for JSON responses |
| **Smart Cache Invalidation** | All cache keys cleared on revocation |

### Cache Keys

| Key Pattern | Purpose |
|-------------|---------|
| `verify:{id}` | Full verification result |
| `cert_light:{id}` | Lightweight verification result |
| `blockchain_verify:{id}:{hash}` | Blockchain verification cache |

---

## Error Handling

All errors return JSON:
```json
{
    "error": "Error message"
}
```

HTTP Status Codes:
- 200: Success
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 500: Server Error

---

## Testing

Run tests:
```bash
cd backend/tests
php run_all_tests.php
```

Individual tests:
```bash
php test_database.php
php test_authentication.php
php test_api_endpoints.php
```
