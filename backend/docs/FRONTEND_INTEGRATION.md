# Frontend Integration Guide

This document is a pragmatic, step-by-step guide for Frontend Developers working with the Certificate Verification System Backend API.

## 1. Connecting to the API & CORS

### Base URL
You will communicate with the backend via its single unified entry point `api/index.php`. 
If running locally: `http://localhost:8000/`
In Production: Ensure you use the exact domain provided by the deployment manager.

```javascript
const API_BASE = 'http://localhost:8000';
// Or your deployed Railway URL 
```

### CORS Configuration
Cross-Origin Resource Sharing (CORS) is enabled and enforces a strict whitelist by dynamically building an allowed origins array:
- `http://localhost:3000` is always allowed.
- Your `.env` value for `FRONTEND_URL` is appended if it exists.

If the incoming request origin is not in the list, the backend forcibly falls back to using `http://localhost:3000` as the `Access-Control-Allow-Origin` header.

If your frontend is blocked by a CORS error, you need to add your origin to the backend's `FRONTEND_URL` environment variable.

## 2. Global Error Handling
The API reliably utilizes standard HTTP statuses: `400 Bad Request`, `401 Unauthorized`, `403 Forbidden`, `404 Not Found`, and `500 Internal Server Error`.

**All errors return a standard JSON shape:**
```json
{
  "error": "The descriptive error message to show users"
}
```

**Example wrapper using `fetch()`**:
```javascript
async function apiRequest(endpoint, options = {}) {
  const response = await fetch(`${API_BASE}${endpoint}`, options);
  const data = await response.json();

  if (!response.ok) {
    if (response.status === 401) {
       // Handle logout/redirect to login
    }
    if (response.status === 403) {
      // Handle Unauthorized Screen
    }
    throw new Error(data.error || 'An unexpected error occurred');
  }

  return data;
}
```

## 3. Authentication Flow

Users (Admins/Universities/Students) log in to retrieve a JSON Web Token (JWT). You must attach this token as a `Bearer` token in the `Authorization` header on *every subsequent private request*.

**Logging In:**
```javascript
const response = await apiRequest('/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email: 'admin@university.edu', password: 'password123' })
});

// Store response.token securely (e.g., localStorage or HttpOnly cookie)
localStorage.setItem('auth_token', response.token);
```

**Adding the Token to Headers globally:**
```javascript
const headers = {
  'Content-Type': 'application/json',
  'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
};
```

## 4. Registering a Student

If an administrator or university wishes to onboard a brand-new student and directly register them into the database, use `/students`. Because it simultaneously creates a user account *and* a student profile, it requires several strings:

```javascript
const response = await apiRequest('/students', {
  method: 'POST',
  headers,
  body: JSON.stringify({
    username: "jsmith_2026",
    email: "jsmith@student.edu",
    password: "SecurePassword!123",
    full_name: "John Smith",
    student_id: "UNI-105-X",
    university_id: 1, // Optional if the logged-in user is already a University
    enrollment_date: "2023-09-01"
  })
});
// returns { success: true }
```

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
