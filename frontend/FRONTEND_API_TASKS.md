# Frontend Implementation Guide: Unconnected Backend APIs

This document outlines critical backend APIs that have been fully developed but are currently **unconnected** or **partially implemented** on the frontend. Implementing these features will significantly enhance the platform's security, administrative capabilities, and user experience. 

---

## 1. Password Reset Flow
**Why we need it:** 
Users (students, university admins) may occasionally forget their passwords. Having an automated, self-service password reset flow is a critical security and usability requirement. The backend is already integrated with Gmail SMTP and PHPMailer to send secure, time-sensitive reset tokens.

### A. Request Password Reset Link
**What it does:** Triggers an email containing a secure token to the user's registered email address.
* **Endpoint:** `POST /auth/forgot-password`
* **Authentication:** **None** (Public)
* **Headers:** `Content-Type: application/json`
* **Request Body:**
  ```json
  {
    "email": "user@example.com"
  }
  ```
* **Response (Success - 200 OK):**
  *(Note: Always returns success to prevent user enumeration)*
  ```json
  {
    "success": true,
    "message": "If an account with that email exists, a password reset link has been sent"
  }
  ```

### B. Reset Password
**What it does:** Validates the token and updates the user's password securely to the new one.
* **Endpoint:** `POST /auth/reset-password`
* **Authentication:** **None** (Public)
* **Headers:** `Content-Type: application/json`
* **Request Body:**
  ```json
  {
    "token": "123abc456def789...", 
    "new_password": "NewStrongPassword1!" 
  }
  ```
  *(Note: Password must be at least 8 characters and contain both letters and numbers)*
* **Response (Success - 200 OK):**
  ```json
  {
    "success": true,
    "message": "Password has been reset successfully"
  }
  ```
* **Response (Error - 400 Bad Request):**
  ```json
  {
    "error": "token and new_password are required" // or password strength error, or invalid/expired token error
  }
  ```

---

## 2. Direct / Bulk Certificate Uploads
**Why we need it:** 
Currently, the frontend only allows generating new certificates organically using `createCertificate` (which compiles data into a new PDF natively). Some universities may have pre-existing PDF certificates they want to anchor/upload directly to the system.

### Upload Existing physical/PDF Certificate
**What it does:** Processes a physical PDF upload via `multipart/form-data`, extracting data from them or securing external PDFs.
* **Endpoint:** `POST /certificates/upload`
* **Authentication:** **Bearer Token** (`university` or `admin` role)
* **Headers:** `Content-Type: multipart/form-data`
* **Request Body (Form Data):**
  * `certificate`: (File) The physical `.pdf` file being uploaded.
* **Response (Success - 200 OK):**
  ```json
  {
    "success": true,
    "certificate_id": "CERT-123",
    "message": "Certificate uploaded successfully"
  }
  ```
* **Response (Error - 400 Bad Request):**
  ```json
  {
    "success": false,
    "error": "No file uploaded or upload error"
  }
  ```

---

## 3. Updating and Deleting Certificates
**Why we need it:** 
To maintain accurate records, administrators may occasionally need to correct typos entirely without issuing a new certificate, or explicitly purge an erroneously created certificate from the database. Right now, on the UI, they can only "Revoke" them.

### A. Update Existing Certificate
**What it does:** Updates the details (like name, course, etc.) of an existing certificate.
* **Endpoint:** `PUT /certificates/update` (Also accepts `POST`)
* **Authentication:** **Bearer Token** (`university` or `admin` role)
* **Headers:** `Content-Type: application/json`
* **Request Body:**
  ```json
  {
    "certificate_id": "CERT-12345",
    "student_name": "Corrected Name",
    "course_name": "Corrected Course Name"
    // Insert any other updatable fields as required
  }
  ```
* **Response (Success - 200 OK):**
  ```json
  {
    "success": true,
    "message": "Certificate updated"
  }
  ```

### B. Delete Certificate Entirely
**What it does:** Permanently removes a certificate from the database.
* **Endpoint:** `DELETE /certificates/delete` (Also accepts `POST`)
* **Authentication:** **Bearer Token** (`admin` role ONLY)
* **Headers:** `Content-Type: application/json`
* **Request Body:**
  ```json
  {
    "certificate_id": "CERT-12345"
  }
  ```
* **Response (Success - 200 OK):**
  ```json
  {
    "success": true,
    "message": "Certificate deleted"
  }
  ```

---

## 4. Custom Profile Avatars
**Why we need it:** 
Increases user engagement and personalization. Currently, the UI relies completely on a CSS-fallback (`.user-avatar`) to display initials. This backend API securely processes image files and associates them with user accounts.

### Upload Profile Avatar
**What it does:** Securely accepts an image upload, processes it, and updates the user's avatar path in the database.
* **Endpoint:** `PUT /auth/profile/avatar`
* **Authentication:** **Bearer Token** (Any authenticated role)
* **Headers:** `Content-Type: multipart/form-data`
* **Request Body (Form Data):**
  * `avatar`: (File) The profile image (e.g., .jpg, .png).
* **Response (Success - 200 OK):**
  ```json
  {
    "success": true,
    "message": "Avatar updated successfully",
    "data": {
      "id": 1,
      "username": "...",
      "email": "...",
      "avatar_url": "/path/to/avatar.jpg"
      // ... full user profile object
    }
  }
  ```
* **Response (Error - 400 Bad Request):**
  ```json
  {
    "error": "No file uploaded" 
  }
  ```

---

### Implementation To-Do List

- [ ] Implement `ForgotPasswordPage.jsx` component to dispatch email requests.
- [ ] Implement `ResetPasswordPage.jsx` component to handle parsing the token from URL parameters and submitting the new password.
- [ ] Ensure authentication checks effectively ignore the aforementioned password-reset pages.
- [ ] Create a "Upload Existing Certificate" button and modal inside `UniversityCertificates.jsx` / `AdminCertificates.jsx` mapped to the upload endpoint.
- [ ] Add an `Edit` action and modal in certificate tables to handle payload mapping for `update`.
- [ ] Add a `Delete` action in the `AdminCertificates` table.
- [ ] Build an overlay image uploader on the avatar circle component in `ProfilePage.jsx` to upload new profile pictures.
