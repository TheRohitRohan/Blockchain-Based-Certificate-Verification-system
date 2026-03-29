# Full API Reference

The Certificate Verification System API is a RESTful JSON service that manages authentication, university records, student registration, and certificate operations, including verification.

## Base Configuration

**Base URL**: `https://<your-railway-app-url>.railway.app/api` *(replace with actual deployment URL)* 

Local Development Base URL: `http://localhost:8000/`

## Authentication

Most endpoints require a JSON Web Token (JWT). To access protected endpoints, retrieve a token via `/auth/login` and include it in the `Authorization` header as a Bearer token.

**Header Format:**
```
Authorization: Bearer <your_jwt_token_here>
```

## General Error Response Format

Errors are generally returned with standard HTTP status codes (e.g., `400 Bad Request`, `401 Unauthorized`, `403 Forbidden`, `404 Not Found`, `500 Internal Server Error`). 

The standard JSON error payload is:
```json
{
  "error": "Human readable error description"
}
```

---

## Authentication Endpoints

### 1. `POST /auth/login`
Authenticates a user and returns a token.
- **Auth Required**: No
- **Request Body**:
  ```json
  {
    "email": "admin@certificate-system.com",
    "password": "admin"
  }
  ```
- **Success Response** (200 OK):
  ```json
  {
    "success": true,
    "token": "eyJ0eXAi...",
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@...com",
      "role": "admin",
      "full_name": "System Admin",
      "university_id": null
    }
  }
  ```

### 2. `POST /auth/register`
Creates a new user account.
- **Auth Required**: No (Though UI might restrict it)
- **Request Body**:
  ```json
  {
    "username": "jdoe",
    "email": "jdoe@example.com",
    "password": "securepassword123",
    "role": "student",
    "full_name": "John Doe",
    "university_id": 1
  }
  ```
- **Success Response** (200 OK):
  ```json
  {
    "success": true,
    "message": "User registered successfully"
  }
  ```

---

## Certificate Endpoints

### 3. `POST /certificates/create`
Generates a new certificate (Flow 1).
- **Auth Required**: Yes (`admin`, `university`)
- **Request Body**:
  ```json
  {
    "student_id": 5,
    "course_name": "Bachelor of Computer Science",
    "degree_type": "Bachelor",
    "issue_date": "2026-06-15",
    "university_id": 1
  }
  ```
- **Success Response** (200 OK):
  ```json
  {
    "success": true,
    "certificate_id": "CERT-1234ABCD",
    "certificate_hash": "0xabc123...",
    "metadata_hash": "0xdef456...",
    "pdf_hash": "0xghi789...",
    "tx_hash": "0x123abc...",
    "blockchain_mode": "live",
    "signature_status": true,
    "pdf_path": "CERT-1234ABCD_student_BSC_2026-06-15.pdf"
  }
  ```

> **Note on `tx_hash`**: If `blockchain_mode` is `"mock"`, the certificate was NOT anchored on the Ethereum blockchain. The `tx_hash` field will be `null` in the database. The certificate is still stored locally and verifiable via database/hash checks, but blockchain verification will fail. Check `blockchain_mode` in the response to determine if real anchoring occurred.

### 4. `POST /certificates/upload`
Uploads a pre-existing externally generated PDF certificate (Flow 2).
- **Auth Required**: Yes (`admin`, `university`)
- **Headers**: `Content-Type: multipart/form-data`
- **Request Format**: 
  - `certificate`: (File) The PDF document.
- **Success Response** (200 OK):
  ```json
  {
    "success": true,
    "certificate_id": "CERT-XYZ123",
    "signature_status": true,
    "message": "Certificate uploaded and processed successfully"
  }
  ```

> **Note on `tx_hash`**: If `blockchain_mode` is `"mock"`, the certificate was NOT anchored on the Ethereum blockchain. The `tx_hash` field will be `null` in the database. The certificate is still stored locally and verifiable via database/hash checks, but blockchain verification will fail. Check `blockchain_mode` in the response to determine if real anchoring occurred.

### 5. `GET /certificates/list`
Lists certificates, with pagination and optional filters. 
- **Auth Required**: Yes (`admin`, `university`)
- **Query Params**:
  - `page` (optional): Page number (default 1)
  - `per_page` (optional): Items per page (default 10)
  - `university_id` (optional): Filter to specific university (Admins only)
  - `student_id` (optional): Filter to specific student
  - `status` (optional): 'active', 'revoked', 'expired'
  - `course_name` (optional): Text match
- **Success Response** (200 OK):
  ```json
  {
    "success": true,
    "certificates": [
      {
        "certificate_id": "CERT-1234",
        "course_name": "BSc IT",
        "status": "active",
        "signature_status": 1,
        "student_name": "Jane Doe",
        "university_name": "Test Uni"
      }
    ],
    "total": 50,
    "page": 1
  }
  ```

### 6. `GET /certificates`
Lists certificates with behavior defined strictly by the caller's role context:
- If role is `student`: calls `getStudentCertificates()` filtered to their own user ID.
- If role is `university` or `admin`: runs an inline DB query returning all certificates in the system (no pagination on this route).
- **Auth Required**: Yes
- **Success Response** (200 OK)

### 7. `GET /certificates/get`
Fetches all details for a specific certificate.
- **Auth Required**: No (Though practically restricted in UI without token context)
- **Query Params**:
  - `certificate_id` (required): E.g., CERT-XYZ
- **Success Response** (200 OK):
  ```json
  {
    "success": true,
    "certificate": {
      "certificate_id": "CERT-XYZ",
      ...
    }
  }
  ```

### 8. `POST /certificates/revoke`
Revokes an active certificate from the blockchain and database.
- **Auth Required**: Yes (`admin`, `university`)
- **Request Body**:
  ```json
  {
    "certificate_id": "CERT-1234XYZ"
  }
  ```
- **Success Response** (200 OK):
  ```json
  {
    "success": true
  }
  ```

### 9. `POST /certificates/verify`
Internal validation mechanism checking hashes and database records structurally.
- **Auth Required**: No
- **Request Body**:
  - Requires `multipart/form-data` with `certificate` (File) OR
  - raw JSON payload containing `{"certificate_id": "...", "certificate_hash": "..."}`
- **Success Response** (200 OK):
  ```json
  {
    "valid": true,
    "status": "valid",
    "message": "Certificate is valid"
  }
  ```

### 10. `GET /certificates/download`
Downloads a PDF document directly utilizing Auth middleware scopes.
- **Auth Required**: Yes
- **Query Params**:
  - `certificate_id`: (Required) The ID to download.
- **Success Response**: Returns binary file standard `application/pdf`.

### 11. `PUT|POST /certificates/update`
Edits non-cryptographic metadata (course name, degree type, issue date) of an active certificate. Modifying identity fields is strictly blocked.
- **Auth Required**: Yes (`admin`, `university`)
- **Request Body**:
  ```json
  {
    "certificate_id": "CERT-1234XYZ",
    "course_name": "Updated Diploma in AI"
  }
  ```
- **Success Response** (200 OK):
  ```json
  {
    "success": true,
    "message": "Certificate updated"
  }
  ```

### 12. `DELETE|POST /certificates/delete`
Permanently purges a certificate and its PDF from the system. Admin ONLY. **Note: Does NOT revoke on the blockchain.**
- **Auth Required**: Yes (`admin`)
- **Request Body**:
  ```json
  {
    "certificate_id": "CERT-1234XYZ"
  }
  ```

---

## Public Verifications

### 13. `GET|POST /public/verify`
Unified gateway mechanism for unauthenticated guest verifications.
- **Auth Required**: No
- **Request Method**: Standard GET `?certificate_id=X`, POST JSON `{"certificate_id": "X"}`, or `multipart/form-data` containing `$_FILES['certificate']`.
- **Success Response** (200 OK):
  ```json
  {
    "success": true,
    "verification_method": "pdf_upload",
    "matched": true,
    "conclusion": {
        "overall_status": "valid",
        "is_valid": true,
        "summary": "Certificate is valid and matches our records",
        "details": ["All metadata fields match", "PDF hash matches stored record", "Blockchain verification passed"]
    },
    ...
  }
  ```

### 14. `GET /public/certificate/download`
Unauthenticated download portal used universally for sharing PDFs via deep links.
- **Auth Required**: No
- **Query Params**:
  - `certificate_id` (required): The ID of the certificate to fetch
  - `view` (optional, boolean `1` or `0`): Determine `inline` vs `attachment` Content-Disposition handling.
- **Success Response**: Binary PDF Stream.

---

## Universities & Students Management

### 15. `GET /universities`
Lists active universities.
- **Auth Required**: No
- **Success Response** (200 OK):
  ```json
  {
    "success": true,
    "universities": [
      {
        "id": 1,
        "name": "MIT",
        "code": "MIT",
        "is_active": 1
      }
    ]
  }
  ```

### 16. `POST /universities`
Creates a brand new university entity.
- **Auth Required**: Yes (`admin`)
- **Request Body**:
  ```json
  {
    "name": "Harvard",
    "code": "HARV",
    "address": "Cambridge",
    "contact_email": "admin@harvard.edu",
    "contact_phone": "123456789"
  }
  ```

### 17. `POST /universities/generate-key`
Generates a brand new 2048-bit AES-256-CBC encrypted RSA Key Pair for a specific University.
- **Auth Required**: Yes (`admin`)
- **Request Body**:
  ```json
  {
    "university_id": 1
  }
  ```
- **Success Response** (200 OK):
  ```json
  {
    "success": true,
    "message": "Key pair generated successfully"
  }
  ```
  
> **Note**: This endpoint previously failed at runtime because `$signatureService` was never instantiated in `index.php`. This has been fixed by adding `$signatureService = new SignatureService()` and the corresponding `use App\SignatureService` statement.

### 18. `GET /students`
Returns student profiles mapped dynamically by university. Admins see all. Universities see local.
- **Auth Required**: Yes (`admin`, `university`)
- **Success Response** (200 OK): Array of registered Student Entities

### 19. `POST /students`
Registers a new student user and connects their student entity to the DB mapping.
- **Auth Required**: Yes (`admin`, `university`)
- **Request Body**:
  ```json
  {
    "username": "ssmith",
    "email": "ssmith@test.com",
    "password": "pass",
    "full_name": "Samantha Smith",
    "student_id": "STU10101",
    "university_id": 1, 
    "enrollment_date": "2023-09-01"
  }
  ```
- **Success Response** (200 OK):
  ```json
  {
    "success": true
  }
  ```
