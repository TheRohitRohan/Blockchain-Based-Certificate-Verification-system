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
  *(Note: Always returns success regardless of whether the email exists — this is intentional to prevent user enumeration attacks)*
  ```json
  {
    "success": true,
    "message": "If an account with that email exists, a password reset link has been sent"
  }
  ```

### B. Reset Password
**What it does:** Validates the token and updates the user's password securely.
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
    "error": "token and new_password are required"
    // or: "Password must be at least 8 characters and contain both letters and numbers"
    // or: "Invalid or expired reset token"
    // or: "Reset token has expired"
  }
  ```
  *(Note: Distinguish between `"Invalid or expired reset token"` (token never existed / already used) and `"Reset token has expired"` (token existed but the 15-minute window passed) — show a tailored message for the expiry case, e.g. "Your link has expired, please request a new one.")*

> **Frontend note — token source:** The reset link in the email is built as `/reset-password?token=<value>`. `ResetPasswordPage.jsx` must read the token from the `token` query parameter specifically.

> **Frontend note — auth bypass:** Both `/forgot-password` and `/reset-password` are public endpoints. Ensure your auth guard does **not** redirect users away from these pages when no JWT is present.

---

## 2. Direct / Bulk Certificate Uploads
**Why we need it:**
Currently, the frontend only allows generating new certificates organically using `createCertificate` (which compiles data into a new PDF natively). Some universities may have pre-existing PDF certificates they want to anchor/upload directly to the system.

### Upload Existing Physical/PDF Certificate
**What it does:** Processes a physical PDF upload via `multipart/form-data`. The PDF becomes the visual certificate document, while **all metadata (student info, course, dates) comes from the form fields** — nothing is read from the PDF itself. The certificate is automatically hashed, signed, and anchored on the blockchain exactly like a generated certificate.
* **Endpoint:** `POST /certificates/upload`
* **Authentication:** **Bearer Token** (`university` or `admin` role)
* **Headers:** `Content-Type: multipart/form-data`
* **Request Body (Form Data):**
  * `certificate`: *(File)* The physical `.pdf` file being uploaded (max 10MB, must be a real PDF — validated by MIME type).
  * `student_id`: *(Integer, Required)* Database row ID of the student (`students.id`, not the student ID number). **The student must already be registered in the system and must belong to the issuing university.**
  * `university_id`: *(Integer, Required for **admin** only)* The university issuing the certificate. For `university` role, this is automatically taken from their token and this field is ignored.
  * `course_name`: *(String, Required)* Name of the course/program.
  * `degree_type`: *(String, Optional)* Type of degree (e.g., "Bachelor", "Master"). Free-text field.
  * `issue_date`: *(String, Required)* Date certificate was issued (`YYYY-MM-DD` format).
* **Response (Success - 200 OK):**
  ```json
  {
    "success": true,
    "certificate_id": "CERT-ABC123XYZ",
    "certificate_hash": "0x1a2b3c4d...",
    "metadata_hash": "0x5e6f7g8h...",
    "pdf_hash": "0x9i0j1k2l...",
    "tx_hash": "0xblockchain_tx_hash or pending",
    "blockchain_mode": "live",
    "signature_status": true,
    "pdf_path": "CERT-ABC123XYZ_uploaded_2026-04-05.pdf"
  }
  ```
* **Response (Error - 400 Bad Request):**
  ```json
  {
    "success": false,
    "error": "No file uploaded or upload error"
    // or: "university_id is required"  (admin only, when not provided)
    // or: "student_id, course_name, and issue_date are required"
    // or: "File too large. Maximum allowed size is 10MB."
    // or: "Only PDF files are allowed"
    // or: "Student not found"
    // or: "Student does not belong to your university"
  }
  ```

---

## 3. Updating and Deleting Certificates
**Why we need it:**
To maintain accurate records, administrators and universities may occasionally need to correct typos without issuing a new certificate, or explicitly purge an erroneously created certificate from the database. Right now, on the UI, they can only "Revoke" them.

### A. Update Existing Certificate
**What it does:** Updates specific certificate fields (`course_name`, `degree_type`, `issue_date`). The PDF is regenerated with the new data, re-signed, and hashes are recalculated. The original blockchain record remains immutable; updates are tracked separately in the database.

> **Important:** Student identity fields (`student_id`, `student_name`, `university`) cannot be changed via this endpoint. Only the three fields listed below are updatable.
* **Endpoint:** `PUT /certificates/update` *(also accepts `POST`)*
* **Authentication:** **Bearer Token** (`university` or `admin` role)
  * University role can only update certificates belonging to their own university (enforced server-side).
  * Always send the caller's `university_id` (from the JWT) alongside the request so the backend can enforce ownership.
* **Headers:** `Content-Type: application/json`
* **Request Body:**
  ```json
  {
    "certificate_id": "CERT-12345",
    "course_name": "Corrected Course Name",
    "degree_type": "Bachelor of Science",
    "issue_date": "2026-04-05"
  }
  ```
  *(At least one of `course_name`, `degree_type`, or `issue_date` must be provided)*
* **Response (Success - 200 OK):**
  ```json
  {
    "success": true,
    "message": "Certificate updated"
  }
  ```
* **Response (Error - 400 Bad Request):**
  ```json
  {
    "success": false,
    "error": "Certificate not found"
    // or: "Cannot update a revoked certificate"
    // or: "No updatable fields provided"
  }
  ```

### B. Delete Certificate Entirely
**What it does:** Permanently removes a certificate from the database and deletes the associated PDF file. Does **not** revoke on blockchain — use the revoke action for that.

> **Important:** This action is **admin only**. The Delete button/action must be completely hidden (not just disabled) for `university` role users.
* **Endpoint:** `DELETE /certificates/delete` *(also accepts `POST`)*
* **Authentication:** **Bearer Token** (`admin` role **only**)*
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
* **Response (Error - 400 Bad Request):**
  ```json
  {
    "success": false,
    "error": "Certificate not found"
  }
  ```

---

## 4. Custom Profile Avatars
**Why we need it:**
Increases user engagement and personalization. Currently, the UI relies completely on a CSS-fallback (`.user-avatar`) to display initials. This backend API securely processes image files and associates them with user accounts.

### Upload Profile Avatar
**What it does:** Securely accepts an image upload, validates it, and updates the user's avatar path in the database.
* **Endpoint:** `PUT /auth/profile/avatar`
* **Authentication:** **Bearer Token** (Any authenticated role)
* **Headers:** `Content-Type: multipart/form-data`
* **Request Body (Form Data):**
  * `avatar`: *(File)* The profile image. Accepted formats: **JPG, PNG only**. Maximum size: **2MB**.
* **Response (Success - 200 OK):**
  ```json
  {
    "success": true,
    "message": "Avatar updated successfully",
    "data": {
      "id": 1,
      "username": "johndoe",
      "email": "john@example.com",
      "avatar_path": "storage/avatars/1_a3f2bc91.jpg"
    }
  }
  ```
  > **Avatar URL:** The `avatar_path` field is a relative path. Prepend your API base URL to render it as an image, e.g. `<img src="{API_BASE_URL}/{avatar_path}" />`. Example: `http://localhost:8000/storage/avatars/1_a3f2bc91.jpg`.
* **Response (Error - 400 Bad Request):**
  ```json
  {
    "error": "No file uploaded"
    // or: "File size exceeds 2MB limit"
    // or: "Only JPG and PNG files are allowed"
  }
  ```

---

## Implementation To-Do List

- [x] Implement `ForgotPasswordPage.jsx` — form to submit email and dispatch reset request to `POST /auth/forgot-password`.
- [x] Implement `ResetPasswordPage.jsx` — reads `token` from the URL query parameter (`?token=...`), form to submit new password to `POST /auth/reset-password`. Show a specific message when error is `"Reset token has expired"`.
- [x] Ensure auth guards do **not** redirect unauthenticated users away from the password reset pages.
- [x] Certificate upload endpoint implemented (`POST /certificates/upload`)
- [ ] Create an "Upload Existing Certificate" button and modal inside `UniversityCertificates.jsx` / `AdminCertificates.jsx`. The modal must collect: `student_id`, `course_name`, `issue_date`, optionally `degree_type`, and the PDF file. Admin modal must also collect `university_id`.
- [x] Certificate update endpoint implemented (`PUT /certificates/update`)
- [ ] Add an `Edit` action and modal in certificate tables (available to both `university` and `admin` roles) to update `course_name`, `degree_type`, and/or `issue_date` via the update endpoint.
- [x] Certificate delete endpoint implemented (`DELETE /certificates/delete`)
- [ ] Add a `Delete` action in the `AdminCertificates` table (visible to `admin` role **only**, completely hidden for `university` role) to call the delete endpoint.
- [ ] Build an overlay image uploader on the avatar circle component in `ProfilePage.jsx`. On click, open a file picker filtered to JPG/PNG, then `PUT` to `/auth/profile/avatar`. After success, update the displayed avatar using the `avatar_path` from the response prepended with the API base URL.