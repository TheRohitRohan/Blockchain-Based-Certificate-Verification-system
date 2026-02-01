# REST API Documentation

Complete API reference for the Certificate Verification System backend.

## 🔗 Base URL
```
Development: http://localhost:8000/api
Production:  https://your-domain.com/api
```

## 🔐 Authentication

The API uses JWT (JSON Web Token) authentication.

### Login Endpoint
```http
POST /auth/login
```

**Request Body:**
```json
{
    "email": "admin@certificate-system.com",
    "password": "admin123"
}
```

**Response:**
```json
{
    "success": true,
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
        "id": 1,
        "username": "admin",
        "email": "admin@certificate-system.com",
        "role": "admin",
        "full_name": "System Administrator"
    }
}
```

### Using the Token
Include the token in the Authorization header:
```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

## 👥 User Roles & Permissions

| Role | Permissions | Can Access |
|------|-------------|------------|
| `admin` | Full system access | All endpoints |
| `university` | Certificate management | Students, certificates (own) |
| `student` | View own certificates | Certificates (own) |

---

## 🎓 Authentication Endpoints

### User Login
```http
POST /auth/login
```

**Request:**
```json
{
    "email": "user@example.com",
    "password": "password123"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "token": "jwt_token_here",
    "user": {
        "id": 1,
        "username": "username",
        "email": "user@example.com",
        "role": "university",
        "full_name": "User Name",
        "university_id": 1
    }
}
```

**Error Response (401):**
```json
{
    "error": "Invalid credentials"
}
```

### User Registration
```http
POST /auth/register
```

**Request:**
```json
{
    "username": "newuser",
    "email": "newuser@example.com",
    "password": "password123",
    "role": "student",
    "full_name": "New User"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "User registered successfully"
}
```

**Error Response (400):**
```json
{
    "error": "Registration failed"
}
```

---

## 📜 Certificate Endpoints

### Create Certificate
```http
POST /certificates/create
```
**Required Role:** `university` or `admin`

**Request:**
```json
{
    "student_id": 5,
    "university_id": 1,
    "course_name": "Computer Science Fundamentals",
    "degree_type": "Bachelor",
    "issue_date": "2024-12-01"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "certificate_id": "CERT-697EF0010427F",
    "certificate_hash": "d44ebb87676afcb45708c9e4a16c5be848a1a52c8b5ed7085b3f65b1a0d3ed",
    "tx_hash": "0x5fa9eedb38aa66b319423c7468708f2d5e4c9b3b4a0e588e51e4848b4e8c4"
}
```

**Error Response (400):**
```json
{
    "success": false,
    "error": "Student not found"
}
```

### Verify Certificate
```http
POST /certificates/verify
```
**Public Access:** No authentication required

**Request:**
```json
{
    "certificate_id": "CERT-697EF0010427F",
    "certificate_hash": "d44ebb87676afcb45708c9e4a16c5be848a1a52c8b5ed7085b3f65b1a0d3ed"
}
```

**Success Response (200):**
```json
{
    "valid": true,
    "status": "valid",
    "certificate": {
        "id": 1,
        "certificate_id": "CERT-697EF0010427F",
        "student_name": "John Doe",
        "university_name": "Tech University",
        "course_name": "Computer Science Fundamentals",
        "degree_type": "Bachelor",
        "issue_date": "2024-12-01",
        "certificate_hash": "d44ebb87676afcb45708c9e4a16c5be848a1a52c8b5ed7085b3f65b1a0d3ed",
        "blockchain_tx_hash": "0x5fa9eedb38aa66b319423c7468708f2d5e4c9b3b4a0e588e51e4848b4e8c4",
        "is_revoked": false,
        "created_at": "2024-12-01T10:30:00.000Z"
    }
}
```

**Certificate Not Found (200):**
```json
{
    "valid": false,
    "status": "not_found"
}
```

**Certificate Revoked (200):**
```json
{
    "valid": false,
    "status": "revoked"
}
```

**Hash Mismatch (200):**
```json
{
    "valid": false,
    "status": "invalid"
}
```

### Get All Certificates
```http
GET /certificates
```
**Required Role:** Any authenticated user

**Response based on role:**

**Admin/University (200):**
```json
{
    "success": true,
    "certificates": [
        {
            "id": 1,
            "certificate_id": "CERT-697EF0010427F",
            "student_name": "John Doe",
            "university_name": "Tech University",
            "course_name": "Computer Science Fundamentals",
            "issue_date": "2024-12-01",
            "is_revoked": false,
            "created_at": "2024-12-01T10:30:00.000Z"
        }
    ]
}
```

**Student (200):**
```json
{
    "success": true,
    "certificates": [
        {
            "id": 1,
            "certificate_id": "CERT-697EF0010427F",
            "university_name": "Tech University",
            "course_name": "Computer Science Fundamentals",
            "issue_date": "2024-12-01",
            "is_revoked": false,
            "created_at": "2024-12-01T10:30:00.000Z"
        }
    ]
}
```

### Revoke Certificate
```http
POST /certificates/revoke
```
**Required Role:** `admin`

**Request:**
```json
{
    "certificate_id": "CERT-697EF0010427F"
}
```

**Success Response (200):**
```json
{
    "success": true
}
```

**Error Response (404):**
```json
{
    "success": false,
    "error": "Certificate not found"
}
```

---

## 🏫 University Endpoints

### Get All Universities
```http
GET /universities
```
**Required Role:** Any authenticated user

**Success Response (200):**
```json
{
    "success": true,
    "universities": [
        {
            "id": 1,
            "name": "Tech University",
            "code": "TECH001",
            "address": "123 Tech Street, Silicon Valley, CA 94000",
            "contact_email": "admin@techuniversity.edu",
            "contact_phone": "+1-555-0123",
            "wallet_address": "0x6a0a163275Bf814ABE557b29Cf27c1dd85f68683",
            "is_active": true,
            "created_at": "2024-01-01T00:00:00.000Z"
        }
    ]
}
```

### Create University
```http
POST /universities
```
**Required Role:** `admin`

**Request:**
```json
{
    "name": "New University",
    "code": "UNIV001",
    "address": "456 University Ave, Boston, MA 02115",
    "contact_email": "admin@newuniversity.edu",
    "contact_phone": "+1-555-0456"
}
```

**Success Response (200):**
```json
{
    "success": true
}
```

---

## 👨‍🎓 Student Endpoints

### Get All Students
```http
GET /students
```
**Required Role:** `university` or `admin`

**Response based on role:**

**University (200):**
```json
{
    "success": true,
    "students": [
        {
            "id": 1,
            "user_id": 5,
            "student_id": "STU2024001",
            "full_name": "John Doe",
            "email": "john.doe@techuniversity.edu",
            "university_id": 1,
            "enrollment_date": "2020-09-01",
            "created_at": "2020-09-01T00:00:00.000Z"
        }
    ]
}
```

**Admin (200):**
```json
{
    "success": true,
    "students": [
        {
            "id": 1,
            "user_id": 5,
            "student_id": "STU2024001",
            "full_name": "John Doe",
            "email": "john.doe@techuniversity.edu",
            "university_name": "Tech University",
            "university_id": 1,
            "enrollment_date": "2020-09-01",
            "created_at": "2020-09-01T00:00:00.000Z"
        }
    ]
}
```

### Create Student
```http
POST /students
```
**Required Role:** `university` or `admin`

**Request:**
```json
{
    "username": "johndoe",
    "email": "john.doe@techuniversity.edu",
    "password": "securePassword123",
    "full_name": "John Doe",
    "student_id": "STU2024001",
    "enrollment_date": "2020-09-01"
}
```

**Success Response (200):**
```json
{
    "success": true
}
```

---

## 📊 Error Responses

### Standard Error Format
```json
{
    "error": "Error message describing what went wrong"
}
```

### HTTP Status Codes
| Code | Meaning |
|------|---------|
| `200` | Success |
| `400` | Bad Request (invalid data) |
| `401` | Unauthorized (missing/invalid token) |
| `403` | Forbidden (insufficient permissions) |
| `404` | Not Found |
| `500` | Internal Server Error |

### Common Error Messages

#### Authentication Errors
- `"Unauthorized"`
- `"Invalid token"`
- `"Invalid credentials"`

#### Permission Errors
- `"Forbidden"`
- `"Insufficient permissions"`

#### Validation Errors
- `"Student not found"`
- `"University not found"`
- `"Certificate ID already exists"`
- `"Invalid certificate data"`

#### System Errors
- `"Database connection failed"`
- `"Blockchain transaction failed"`
- `"Certificate verification failed"`

---

## 🔧 Usage Examples

### JavaScript/Frontend
```javascript
// Login
const loginResponse = await fetch('/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
});
const { token } = await loginResponse.json();

// Create Certificate
const certResponse = await fetch('/api/certificates/create', {
    method: 'POST',
    headers: { 
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify(certificateData)
});
```

### PHP/Backend
```php
// Using cURL
$ch = curl_init('http://localhost:8000/api/certificates/verify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$result = json_decode($response, true);
```

### Python
```python
import requests

# Verify certificate
response = requests.post(
    'http://localhost:8000/api/certificates/verify',
    json={
        'certificate_id': 'CERT-123',
        'certificate_hash': 'abc123...'
    }
)
result = response.json()
```

---

## 🚦 Rate Limiting

While not currently implemented, recommended rate limits:
- **Authentication**: 5 requests per minute
- **Certificate Creation**: 10 per hour per user
- **Verification**: 100 per hour per IP
- **Other Endpoints**: 60 per minute per user

---

## 🔄 Versioning

API versioning is done via URL paths:
```
/api/v1/certificates/verify  # Version 1
/api/v2/certificates/verify  # Version 2 (future)
```

Current version: **v1** (implicit)

---

## 🧪 Testing API Endpoints

### Manual Testing
```bash
# Test authentication
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@certificate-system.com","password":"admin123"}'

# Test certificate verification (public)
curl -X POST http://localhost:8000/api/certificates/verify \
  -H "Content-Type: application/json" \
  -d '{"certificate_id":"CERT-123","certificate_hash":"abc123..."}'
```

### Automated Testing
```bash
cd backend/tests
php test_api_endpoints.php
```

---

## 📝 Integration Notes

### CORS Configuration
The API includes CORS headers for frontend integration:
```http
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

### File Uploads
For future PDF upload features, use `multipart/form-data`:
```http
POST /certificates/upload
Content-Type: multipart/form-data

--boundary
Content-Disposition: form-data; name="certificate"; filename="cert.pdf"
Content-Type: application/pdf

[pdf file content]
--boundary--
```

### WebSocket Support
Currently not implemented, but planned for real-time updates.

---

**For integration examples and client libraries, see the [Development Guide](DEVELOPMENT.md).**