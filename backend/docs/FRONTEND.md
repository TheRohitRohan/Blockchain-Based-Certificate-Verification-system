# Frontend User Guide

## Accessing the Application

1. Open browser: **http://localhost:3000**
2. Login with your credentials

---

## User Roles

| Role | Access |
|------|--------|
| **Admin** | /admin - Full system control |
| **University** | /university - Manage students & certificates |
| **Student** | /student - View own certificates |
| **Public** | /verify - Verify any certificate |

---

## Login

1. Enter email and password
2. System redirects to role-based dashboard

**Default Admin Login:**
- Email: admin@certificate-system.com
- Password: admin123

---

## Admin Features

### Dashboard (/admin)
- View all universities
- Create new universities
- Monitor all certificates
- Revoke any certificate

### Actions
- **Add University**: Click "Add University" button
- **View Details**: Click on any university row
- **Revoke Certificate**: Select certificate → Click Revoke

---

## University Features

### Dashboard (/university)
- View own students
- Add new students
- Issue certificates
- View issued certificates
- Download certificate PDFs

### Adding Students
1. Click "Add Student"
2. Fill form:
   - Username
   - Email
   - Full Name
   - Student ID (e.g., STU001)
   - Enrollment Date
3. Click Submit

### Issuing Certificates
1. Click "Issue Certificate"
2. Select student from dropdown
3. Enter:
   - Course Name
   - Degree Type
   - Issue Date
4. Click Issue
5. Certificate is created with unique ID

### Certificate Details
Each certificate includes:
- Certificate ID (e.g., CERT-ABC123)
- QR Code for verification
- PDF Download link
- Blockchain transaction hash
- Status (Active/Revoked)

---

## Student Features

### Dashboard (/student)
- View all received certificates
- Download certificate PDFs
- View certificate details
- Share verification links

### Certificate Card Shows:
- Course name
- Degree type
- Issue date
- University name
- Status badge (Valid/Revoked)

### Actions
- **Download PDF**: Click download icon
- **View Details**: Click on certificate card
- **Share**: Copy verification URL

---

## Public Verification (/verify)

Anyone can verify certificates without login:

### Method 1: Enter Certificate ID
1. Go to /verify
2. Enter Certificate ID
3. Click Verify

### Method 2: Scan QR Code
1. Scan QR code on certificate
2. Opens verification page automatically

### Verification Result Shows:
- ✅ Valid - Certificate is authentic
- ❌ Revoked - Certificate was revoked
- ❌ Not Found - Certificate doesn't exist

---

## Navigation

| Link | Page | Who Can Access |
|------|------|----------------|
| /login | Login Page | Everyone |
| /verify | Public Verification | Everyone |
| /dashboard | Role Dashboard | All Users |
| /admin | Admin Dashboard | Admin Only |
| /university | University Dashboard | University/Admin |
| /student | Student Dashboard | Student Only |

---

## Logout

Click logout button in navigation bar to sign out.

---

## Common Tasks

### Find Certificate ID
- Check student dashboard
- Look at PDF filename
- Scan QR code

### Verify a Certificate
1. Go to /verify
2. Enter certificate ID
3. View result

### Download Certificate PDF
1. Login as student or university
2. Find certificate in list
3. Click download button
