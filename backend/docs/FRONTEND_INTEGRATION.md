# Frontend Integration Guide

This document provides practical guidance for Frontend Developers integrating with the Certificate Verification System Backend API. The backend is a REST API that returns JSON for all operations.

## 1. Base Configuration

**Local Development:**
```javascript
const API_BASE = 'http://localhost:8000';
```

**Production:**
```javascript
const API_BASE = 'https://your-railway-domain.railway.app';
```

### CORS & Origins

The backend enforces strict CORS whitelisting:
- Allowed by default: `http://localhost:3000`, `http://127.0.0.1:3000`, values from `FRONTEND_URL` env
- Configure allowed origins via backend's `ALLOWED_ORIGINS` env variable (comma-separated)
- Add your frontend domain to `.env` `FRONTEND_URL` if getting CORS errors

## 2. Global Error Handling

All API errors return standard HTTP statuses with a consistent JSON error shape:

```json
{
  "error": "Descriptive error message for end users"
}
```

**Recommended fetch wrapper:**
```javascript
async function apiRequest(endpoint, options = {}) {
  const response = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...options.headers,
    },
  });

  const data = await response.json();

  if (!response.ok) {
    if (response.status === 401) {
      // Unauthorized - token expired or invalid
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    if (response.status === 403) {
      // Forbidden - user lacks permission
      throw new Error('Access denied');
    }
    throw new Error(data.error || `Error ${response.status}`);
  }

  return data;
}
```

## 3. Authentication & Token Management

All protected endpoints require a JWT bearer token.

### Login (Any Role)

```javascript
async function login(email, password) {
  const response = await apiRequest('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });

  // response.token, response.user
  localStorage.setItem('auth_token', response.token);
  localStorage.setItem('user', JSON.stringify(response.user));
  
  return response.user;
}
```

### Verify Token (Check Validity)

```javascript
async function verifyToken() {
  const token = localStorage.getItem('auth_token');
  const response = await fetch(`${API_BASE}/auth/verify-token`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token }),
  });
  
  const data = await response.json();
  return data.valid; // true/false
}
```

### Logout

Remove token from storage:
```javascript
function logout() {
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  window.location.href = '/login';
}
```

### Password Reset (3-Step Flow)

**Step 1: Request Reset Token**
```javascript
async function forgotPassword(email) {
  const response = await apiRequest('/auth/forgot-password', {
    method: 'POST',
    body: JSON.stringify({ email }),
  });
  // response: { success: true, message: "Reset email sent" }
}
```

**Step 2: User clicks email link** 
Link format: `http://frontend/reset-password?token=TOKEN_HERE`

**Step 3: Submit New Password**
```javascript
async function resetPassword(token, newPassword) {
  const response = await apiRequest('/auth/reset-password', {
    method: 'POST',
    body: JSON.stringify({ token, new_password: newPassword }),
  });
  // Login required after reset
}
```

### Change Password (Authenticated Users)

```javascript
async function changePassword(currentPassword, newPassword) {
  const token = localStorage.getItem('auth_token');
  const response = await apiRequest('/auth/change-password', {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` },
    body: JSON.stringify({ 
      current_password: currentPassword, 
      new_password: newPassword 
    }),
  });
  // { success: true, message: "Password updated" }
}
```

## 4. Role-Based User Registration

### Admin Registration (Self-Signup)
Only allows `admin` role - typically first-time setup:
```javascript
async function registerAdmin(userData) {
  return apiRequest('/auth/register', {
    method: 'POST',
    body: JSON.stringify({
      email: userData.email,
      password: userData.password,
      username: userData.username,
      full_name: userData.fullName,
      role: 'admin',
    }),
  });
}
```

### University Self-Registration (No Auth)
Creates university + admin account in one call:
```javascript
async function registerUniversity(formData) {
  return apiRequest('/auth/university/register', {
    method: 'POST',
    body: JSON.stringify({
      university_name: formData.universityName,
      university_email: formData.universityEmail,
      university_phone: formData.phone,
      university_address: formData.address,
      admin_name: formData.adminName,
      admin_email: formData.adminEmail,
      admin_password: formData.password,
    }),
  });
  // response: { success: true, university_id: 1 }
}
```

### Student Registration (By University Admin)
```javascript
async function registerStudent(studentData) {
  const token = localStorage.getItem('auth_token');
  return apiRequest('/students', {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` },
    body: JSON.stringify({
      username: studentData.username,
      email: studentData.email,
      password: studentData.password,
      full_name: studentData.fullName,
      student_id: studentData.studentId, // e.g., "STU-001-2024"
      enrollment_date: studentData.enrollmentDate,
      university_id: studentData.universityId,
    }),
  });
}
```

## 5. Certificate Management Workflows

### Workflow 1: Create Certificate (System-Generated)

University admin provides student name, course → backend generates PDF automatically:

```javascript
async function createCertificate(formData) {
  const token = localStorage.getItem('auth_token');
  
  const response = await apiRequest('/certificates/create', {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` },
    body: JSON.stringify({
      student_id: formData.studentId,
      course_name: formData.courseName,
      degree_type: formData.degreeType, // e.g., "Bachelor", "Master"
      issue_date: formData.issueDate, // YYYY-MM-DD
      university_id: formData.universityId,
    }),
  });

  // Response format:
  // {
  //   "success": true,
  //   "certificate_id": "CERT-ABC123",
  //   "certificate_hash": "0xKeccak256...",
  //   "blockchain_tx_hash": "0x123..." OR null (if mock mode),
  //   "blockchain_mode": "live" or "mock",
  //   "onchain_hash": "0x..."
  // }

  return response;
}
```

### Workflow 2: Upload Certificate (University-Generated PDF)

University admin uploads pre-generated PDF. Backend extracts metadata, anchors to blockchain:

```javascript
async function uploadCertificate(pdfFile) {
  const token = localStorage.getItem('auth_token');
  const formData = new FormData();
  
  formData.append('certificate', pdfFile); // PDF file from `<input type="file">`

  const response = await fetch(`${API_BASE}/certificates/upload`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` },
    body: formData, // FormData, NOT JSON
  });

  return await response.json();
  // { success: true, certificate_id, blockchain_tx_hash, blockchain_mode }
}
```

### Download Certificate PDF

```javascript
async function downloadCertificate(certificateId) {
  const token = localStorage.getItem('auth_token');
  
  const response = await fetch(
    `${API_BASE}/certificates/download?certificate_id=${certificateId}`,
    {
      headers: { 'Authorization': `Bearer ${token}` },
    }
  );

  // Returns PDF binary → trigger download
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${certificateId}.pdf`;
  link.click();
}
```

### List Student's Certificates

```javascript
async function getStudentCertificates(studentId, page = 1) {
  const token = localStorage.getItem('auth_token');
  
  const response = await apiRequest(
    `/students/${studentId}/certificates?page=${page}&limit=20`,
    {
      headers: { 'Authorization': `Bearer ${token}` },
    }
  );

  // response.data = [ { certificate_id, student_name, course_name, ... } ]
  // response.pagination = { total, pages, current_page }
  return response;
}
```

### Revoke Certificate

```javascript
async function revokeCertificate(certificateId) {
  const token = localStorage.getItem('auth_token');
  
  const response = await apiRequest('/certificates/revoke', {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}` },
    body: JSON.stringify({ certificate_id: certificateId }),
  });

  // { success: true, blockchain_tx_hash }
  return response;
}
```

## 6. Certificate Verification (Public - No Auth)

### Verify by Certificate ID

```javascript
async function verifyCertificateByID(certificateId) {
  const response = await apiRequest('/public/verify', {
    method: 'POST',
    body: JSON.stringify({ certificate_id: certificateId }),
  });

  // Response structure:
  // {
  //   "valid": true/false,
  //   "status": "valid" | "invalid" | "revoked" | "not_found",
  //   "message": "Certificate is valid",
  //   "signature": { signed, valid, signer },
  //   "blockchain_valid": true/false/null,
  //   "blockchain_connected": true/false,
  //   "certificate": { id, student_name, course_name, ... }
  // }

  return response;
}
```

### Verify by PDF Upload

```javascript
async function verifyPDF(pdfFile) {
  const formData = new FormData();
  formData.append('certificate', pdfFile);

  const response = await fetch(`${API_BASE}/public/verify`, {
    method: 'POST',
    body: formData, // FormData with file
  });

  const result = await response.json();
  
  // Includes additional fields:
  // differences: { field_name: { expected, actual, match: boolean }, ... }
  // conclusion: "Certificate is legitimate and matches blockchain records"
  
  return result;
}
```

### Download Public Certificate

```javascript
async function downloadPublicCertificate(certificateId) {
  const response = await fetch(
    `${API_BASE}/public/certificate/download?certificate_id=${certificateId}`
  );

  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `certificate.pdf`;
  link.click();
}
```

## 7. User Profile Management

### Get Current User Profile

```javascript
async function getProfile() {
  const token = localStorage.getItem('auth_token');
  
  return apiRequest('/auth/profile', {
    headers: { 'Authorization': `Bearer ${token}` },
  });
  // { success: true, data: { id, email, role, full_name, ... } }
}
```

### Update Profile

```javascript
async function updateProfile(updates) {
  const token = localStorage.getItem('auth_token');
  
  return apiRequest('/auth/profile', {
    method: 'PUT',
    headers: { 'Authorization': `Bearer ${token}` },
    body: JSON.stringify({
      username: updates.username,
      full_name: updates.fullName,
    }),
  });
}
```

### Upload Avatar

```javascript
async function uploadAvatar(imageFile) {
  const token = localStorage.getItem('auth_token');
  const formData = new FormData();
  
  formData.append('avatar', imageFile); // JPG or PNG, <2MB

  const response = await fetch(`${API_BASE}/auth/profile/avatar`, {
    method: 'PUT',
    headers: { 'Authorization': `Bearer ${token}` },
    body: formData, // FormData, NOT JSON
  });

  return await response.json();
  // { success: true, path: "/storage/avatars/..." }
}
```

## 8. University Management (Admin Only)

### List Universities

```javascript
async function listUniversities() {
  return apiRequest('/universities');
  // No auth required - returns active universities only
  // response.universities = [ { id, name, code, contact_email, ... } ]
}
```

### Get Universit Details

```javascript
async function getUniversityDetails(universityId) {
  return apiRequest(`/universities/${universityId}`);
  // { success: true, data: { id, name, ... } }
}
```

### University Statistics (Staff Only)

```javascript
async function getUniversityStats(universityId) {
  const token = localStorage.getItem('auth_token');
  
  return apiRequest(`/universities/${universityId}/stats`, {
    headers: { 'Authorization': `Bearer ${token}` },
  });

  // {
  //   "total_students": 150,
  //   "active_students": 145,
  //   "total_certificates": 300,
  //   "certificates_by_status": { active: 280, revoked: 20 },
  //   "average_verification_time": "120ms"
  // }
}
```

### List University Students

```javascript
async function getUniversityStudents(universityId, page = 1) {
  const token = localStorage.getItem('auth_token');
  
  return apiRequest(
    `/universities/${universityId}/students?page=${page}&limit=20&is_active=1`,
    {
      headers: { 'Authorization': `Bearer ${token}` },
    }
  );
  // response.data = students, response.pagination
}
```

## 9. Handling Blockchain Responses

### Understanding Blockchain Mode

Certificates can be anchored "live" on Ethereum or "mock" (locally issued only):

```javascript
function handleCertificateResponse(response) {
  if (response.blockchain_mode === 'live') {
    console.log('✅ Certificate anchored on Ethereum');
    console.log(`TX: ${response.blockchain_tx_hash}`);
    console.log(`Verified at: https://sepolia.etherscan.io/tx/${response.blockchain_tx_hash}`);
  } else if (response.blockchain_mode === 'mock') {
    console.warn('⚠️ Certificate issued locally (blockchain unavailable)');
    console.log('Will be anchored once blockchain is available');
  }
}
```

### Verifying Blockchain Connection

```javascript
async function checkBlockchainStatus() {
  // (Requires backend endpoint - currently internal only)
  // You can infer from certificate responses:
  // - If blockchain_tx_hash is null → mock mode
  // - If blockchain_tx_hash starts with 0x → live mode
}
```

## 10. Best Practices

1. **Token Storage**: Use HttpOnly cookies if possible (more secure than localStorage)
2. **Error Handling**: Always check `!response.ok` before processing
3. **Loading States**: Show loading indicators during API calls (blockchain calls can take 1-3 seconds)
4. **Retry Logic**: Implement exponential backoff for failed requests
5. **Rate Limiting**: No rate limit enforcement yet, but implement client-side throttling
6. **File Uploads**: Max 10MB per file; validate MIME types client-side before upload
7. **CORS Issues**: If stuck, verify `FRONTEND_URL` in backend `.env` and clear browser cache

## 5. Issuing a Certificate (Flow 1: System Generated)

Use this flow when you want the backend to automatically map data onto an HTML template, generate the PDF itself, and cryptographically sign it.

```javascript
const response = await apiRequest('/certificates/create', {
  method: 'POST',
  headers,
  body: JSON.stringify({
    student_id: 54, // Primary Key ID from /students DB row
    course_name: "Bachelor of Medicine",
    degree_type: "Bachelor",
    issue_date: "2026-06-15",
    university_id: 1
  })
});

// The backend handles the PDF logic and returns the chain hashes
console.log(response.certificate_id, response.tx_hash);
```

> Check `response.blockchain_mode` in the result. If it equals `"mock"`, the certificate was stored locally but was NOT sent to the Ethereum blockchain. You should surface this to the admin UI so operators know real blockchain anchoring did not occur. When `blockchain_mode` is `"mock"`, `tx_hash` will be `null` in the database.

## 6. Uploading a Certificate (Flow 2: Pre-existing PDF)

Use this flow when a University already has a completed graphical PDF and just needs the system to validate it, inject the metadata, strap a QR code to it, sign it, and upload it to the blockchain.

```javascript
const formData = new FormData();
// fileInput is your HTML <input type="file">
formData.append('certificate', fileInput.files[0]);

const response = await fetch(`${API_BASE}/certificates/upload`, {
  method: 'POST',
  headers: {
    // IMPORTANT: Let the browser set the Content-Type automatically for FormData!
    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
  },
  body: formData
});

const result = await response.json();
```

## 7. Revoking a Certificate
Permanently marking a certificate as fraudulent or expired on the blockchain. Only admins/universities can do this.

```javascript
const response = await apiRequest('/certificates/revoke', {
  method: 'POST',
  headers,
  body: JSON.stringify({ certificate_id: "CERT-ABCD123" })
});
```

## 8. Verifying a Certificate (Public Portal)

This is the most complex frontend interface you will build. The `/public/verify` endpoint dynamically handles either string ID validation (QR Code Scanner) OR direct file uploading.

### A: Verification by ID (QR Scan)
```javascript
const response = await apiRequest(`/public/verify?certificate_id=CERT-ABCD123`, {
  method: 'GET'
});
```

### B: Verification by Upload
```javascript
const formData = new FormData();
formData.append('certificate', fileInput.files[0]);

const response = await fetch(`${API_BASE}/public/verify`, {
  method: 'POST',
  body: formData
});
const result = await response.json();
```

### Displaying the Verification Result
Whether you use GET or POST, `result` yields a massive analytical payload. **Your frontend UI should distinctively display these states:**

1. **Overall Validity**: `result.conclusion.is_valid` AND `result.conclusion.summary`
2. **Blockchain Validity**: `result.blockchain_valid` (Did the smart contract accept the file?)
3. **Database Match**: `result.matched` (Is the PDF fundamentally unaltered?)
4. **Signature Authenticity**: 
    ```javascript
    const sig = result.signature;
    if(sig.signed && sig.valid) {
      // Show Green Checkmark "Authentic University Signature"
    } else {
      // Show Warning "Unsigned or Tampered"
    }
    ```
5. **Blockchain Anchoring Status**:
    ```javascript
    // Show whether blockchain anchoring was real or mock at issuance time
    const mode = result.certificate?.blockchain_mode;
    if (mode === 'mock') {
      // Show warning: "This certificate was not anchored on the blockchain"
    }
    ```
6. **Granular Metadata Breakdown**: If the certificate fails checking, `result.metadata_differences` shows exactly which field the user tried to spoof vs the database. Show these differences in a side-by-side data table!

## 9. Downloading the PDF
The API supplies a public endpoint utilizing the URL to download or natively view the underlying PDF utilizing PHP's Binary handling.

```javascript
// Opens in the browser utilizing PDF.js or native rendering
window.open(`${API_BASE}/public/certificate/download?certificate_id=CERT-1234&view=1`, '_blank');

// Triggering an immediate OS download attachment using fetch
const response = await fetch(`${API_BASE}/public/certificate/download?certificate_id=CERT-1234`);
const blob = await response.blob();
const downloadUrl = window.URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = downloadUrl;
a.download = `Certificate-CERT-1234.pdf`;
document.body.appendChild(a);
a.click();
a.remove();
```
