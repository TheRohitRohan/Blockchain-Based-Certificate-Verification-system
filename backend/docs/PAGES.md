# Frontend Pages Documentation

This document details all required pages for the Certificate Verification System frontend.

---

## Table of Contents

1. [Login Page](#1-login-page--login)
2. [Public Verification Portal](#2-public-verification-portal--verify)
3. [Admin Dashboard](#3-admin-dashboard--admin)
4. [University Dashboard](#4-university-dashboard--university)
5. [Issue Certificate Page](#5-issue-certificate-page--universityissue)
6. [Student Dashboard](#6-student-dashboard--student)
7. [Profile Page](#7-profile-page--profile)

---

## 1. Login Page (`/login`)

**Purpose:** Authenticate users and redirect to role-based dashboard

### Layout
- Centered card on page
- Company logo at top
- Form title: "Login"

### Components

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Email | Input (email) | Yes | Valid email format |
| Password | Input (password) | Yes | Min 6 characters |
| Login Button | Button | - | Primary action |

### Behavior
1. On submit → Call `POST /api/auth/login`
2. On success → Store JWT token, redirect to role-based dashboard:
   - Admin → `/admin`
   - University → `/university`
   - Student → `/student`
3. On error → Display error message below form
4. "Remember me" checkbox (optional)

### Default Credentials
- Email: admin@certificate-system.com
- Password: admin123

---

## 2. Public Verification Portal (`/verify`)

**Purpose:** Allow anyone to verify certificates without login

### Layout
- Header with logo and title "Verify Certificate"
- Main content area with verification options
- Result display section

### Components

#### 2.1 Verification Input Section

**Option A: Certificate ID Input**
| Field | Type | Required |
|-------|------|----------|
| Certificate ID | Input (text) | Yes |

**Option B: File Upload**
| Field | Type | Required |
|-------|------|----------|
| Upload PDF | File input (drag/drop) | No |

**Action Button:** "Verify" - Primary button

#### 2.2 Result Display Section

**When Certificate is VALID:**
```
┌─────────────────────────────────────────┐
│  ✓ VERIFIED                             │
│  This certificate is valid              │
├─────────────────────────────────────────┤
│  Certificate ID: CERT-XXXXXX            │
│  Student Name: John Doe                 │
│  University: Tech University           │
│  Course: Computer Science              │
│  Degree: Bachelor                      │
│  Issue Date: 2024-12-01                │
│  Status: Active                         │
│  Blockchain TX: 0x...                  │
├─────────────────────────────────────────┤
│  [Download PDF]  [View in Browser]     │
└─────────────────────────────────────────┘
```

**When Certificate is REVOKED:**
```
┌─────────────────────────────────────────┐
│  ✗ REVOKED                              │
│  This certificate has been revoked      │
├─────────────────────────────────────────┤
│  Certificate ID: CERT-XXXXXX            │
│  Student Name: John Doe                 │
│  Revoked Date: 2025-01-15              │
│  Revoked By: Admin                     │
└─────────────────────────────────────────┘
```

**When Certificate NOT FOUND:**
```
┌─────────────────────────────────────────┐
│  ⚠ NOT FOUND                           │
│  Certificate not in system              │
└─────────────────────────────────────────┘
```

---

## 3. Admin Dashboard (`/admin`)

**Purpose:** Full system administration and monitoring

### Layout
- Sidebar navigation (left)
- Top navbar with admin info and logout
- Main content area (right)

### Sidebar Menu
```
├── Dashboard
├── Universities
├── Certificates
└── Settings (optional)
```

### Sections

#### 3.1 Overview/Stats Cards (Dashboard Home)

**4 Stat Cards in a row:**
| Card | Shows |
|------|-------|
| Total Universities | Count |
| Total Students | Count |
| Total Certificates | Count |
| Active Certificates | Count |

**Recent Activity Table:**
- Last 10 certificate issuances
- Columns: Date, Certificate ID, Student, University

#### 3.2 Universities Management (`/admin/universities`)

**Page Header:** "Manage Universities"

**Components:**

**Add University Button** → Opens modal

**Universities Table:**
| Column | Description |
|--------|-------------|
| ID | Auto increment |
| Name | University full name |
| Code | Short code (e.g., MIT) |
| Status | Active/Inactive badge |
| Actions | Edit, Delete buttons |

**Add/Edit University Modal:**
| Field | Type | Required |
|-------|------|----------|
| Name | Input | Yes |
| Code | Input | Yes |
| Address | Textarea | No |
| Contact Email | Input (email) | No |
| Contact Phone | Input | No |

**Delete Confirmation Modal:**
- Warning message
- Cancel/Delete buttons

#### 3.3 Certificates Management (`/admin/certificates`)

**Page Header:** "All Certificates"

**Components:**

**Filter Bar:**
| Filter | Type |
|--------|------|
| Search | Text input (search by ID, student name) |
| University | Dropdown |
| Status | Dropdown (All/Active/Revoked) |
| Date Range | Date picker (from/to) |

**Certificates Table:**
| Column | Description |
|--------|-------------|
| Certificate ID | Unique ID |
| Student | Student name |
| University | University name |
| Course | Course name |
| Issue Date | Date issued |
| Status | Active/Revoked badge |
| Actions | View, Revoke buttons |

**Certificate Details Modal:**
```
┌─────────────────────────────────────────┐
│  Certificate Details              [X]  │
├─────────────────────────────────────────┤
│  Certificate ID: CERT-XXXXXX           │
│                                         │
│  Student Information                    │
│  ├── Name: John Doe                     │
│  ├── Student ID: STU001                 │
│  └── Email: john@example.com            │
│                                         │
│  University                             │
│  ├── Name: Tech University              │
│  └── Code: TECH001                      │
│                                         │
│  Certificate Details                    │
│  ├── Course: Computer Science          │
│  ├── Degree: Bachelor                   │
│  ├── Issue Date: 2024-12-01             │
│  └── Status: Active                     │
│                                         │
│  Blockchain Information                 │
│  ├── TX Hash: 0x...                    │
│  ├── Block: 12345                      │
│  └── Chain ID: 1337                     │
│                                         │
│  Metadata Hash: 0x...                  │
│  PDF Hash: 0x...                       │
│                                         │
│  [Download PDF]  [Revoke]  [Close]     │
└─────────────────────────────────────────┘
```

**Revoke Confirmation Modal:**
```
┌─────────────────────────────────────────┐
│  Revoke Certificate                     │
├─────────────────────────────────────────┤
│  Are you sure you want to revoke        │
│  certificate CERT-XXXXXX?                │
│                                         │
│  This action cannot be undone.          │
│                                         │
│  [Cancel]  [Revoke Certificate]        │
└─────────────────────────────────────────┘
```

---

## 4. University Dashboard (`/university`)

**Purpose:** University staff manages students and certificates

### Sidebar Menu
```
├── Dashboard
├── Students
├── Issue Certificate
├── My Certificates
└── Profile
```

### Sections

#### 4.1 Dashboard Home (`/university`)

**Stats Cards:**
| Card | Description |
|------|-------------|
| My Students | Count of students in this university |
| Certificates Issued | Total certificates issued |
| This Month | Certificates issued this month |

**Recent Activity:**
- Last 5 certificates issued by this university

#### 4.2 Students Management (`/university/students`)

**Page Header:** "Manage Students"

**Components:**

**Add Student Button**

**Search Bar**

**Students Table:**
| Column | Description |
|--------|-------------|
| Student ID | Unique student ID |
| Full Name | Student full name |
| Email | Student email |
| Enrollment Date | Date enrolled |
| Actions | Edit, Delete |

**Add Student Form:**
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Username | Input | Yes | Unique, min 3 chars |
| Email | Input | Yes | Valid email, unique |
| Password | Input | Yes | Min 6 chars |
| Full Name | Input | Yes | Min 2 chars |
| Student ID | Input | Yes | Unique |
| Enrollment Date | Date picker | No | Default: today |

**Edit Student Form:** Same as Add, pre-filled

#### 4.3 Issue Certificate (`/university/issue`)

**Page Header:** "Issue New Certificate"

**Form:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| Select Student | Dropdown (searchable) | Yes | List of university's students |
| Course Name | Input | Yes | e.g., Computer Science |
| Degree Type | Dropdown | Yes | Bachelor/Master/PhD/Certificate |
| Issue Date | Date picker | Yes | Default: today |

**Degree Types:**
- Bachelor
- Master
- Doctor (PhD)
- Diploma
- Certificate
- Associate

**Submit Button:** "Issue Certificate"

**Success Response Display:**
```
┌─────────────────────────────────────────┐
│  ✓ Certificate Issued Successfully!    │
├─────────────────────────────────────────┤
│  Certificate ID: CERT-ABC123            │
│                                         │
│  QR Code:                              │
│  [QR Code Image]                       │
│                                         │
│  [Download PDF]  [Download QR]         │
│                                         │
│  Share Link:                           │
│  http://localhost:3000/verify?cert=... │
│  [Copy Link]                           │
└─────────────────────────────────────────┘
```

#### 4.4 My Certificates (`/university/certificates`)

**Page Header:** "Issued Certificates"

**Filter Bar:** Search, Date range, Status

**Table:**
| Column | Description |
|--------|-------------|
| Certificate ID | Unique ID |
| Student | Student name |
| Course | Course name |
| Degree | Degree type |
| Issue Date | Date issued |
| Status | Active/Revoked |
| Actions | View, Download, Revoke |

---

## 5. Issue Certificate Page (`/university/issue`)

This is part of University Dashboard but documented separately for clarity.

**Full Page Components:**

#### Header Section
- Title: "Issue New Certificate"
- Breadcrumb: Home > Issue Certificate

#### Form Card
```
┌─────────────────────────────────────────┐
│  Certificate Details                    │
├─────────────────────────────────────────┤
│                                         │
│  Select Student *                      │
│  [Search students...        ▼]         │
│  ┌─────────────────────────────┐       │
│  │ John Doe (STU001)           │       │
│  │ Jane Smith (STU002)         │       │
│  │ Bob Wilson (STU003)         │       │
│  └─────────────────────────────┘       │
│                                         │
│  Course Name *                         │
│  [Enter course name...      ]          │
│                                         │
│  Degree Type *                         │
│  [Select degree...           ▼]        │
│                                         │
│  Issue Date *                          │
│  [📅 2024-12-01             ]          │
│                                         │
│  [Issue Certificate]                   │
└─────────────────────────────────────────┘
```

#### Instructions Card (Optional)
- Steps to issue certificate
- Requirements

---

## 6. Student Dashboard (`/student`)

**Purpose:** Students view their own certificates

### Layout
- Top navbar with student name and logout
- Welcome section
- Certificates grid

### Sections

#### 6.1 Welcome Section
```
┌─────────────────────────────────────────┐
│  Welcome, John Doe!                     │
│  Student ID: STU001                     │
│  University: Tech University            │
└─────────────────────────────────────────┘
```

#### 6.2 Stats Cards
| Card | Description |
|------|-------------|
| Total Certificates | Count of certificates |
| Active | Active certificates count |

#### 6.3 Certificates Grid

**View Toggle:** Grid / List

**Certificate Card (Grid View):**
```
┌───────────────────────────┐
│  [QR Code Thumbnail]    │
├───────────────────────────┤
│  Computer Science       │
│  Bachelor               │
│  ─────────────          │
│  Issued: Dec 2024       │
│  Status: ✓ Active       │
│                           │
│  [View] [Download]      │
└───────────────────────────┘
```

**Certificate Row (List View):**
```
┌─────────────────────────────────────────────────┐
│ [QR] │ CERT-XXX │ CS │ Bachelor │ Dec 2024 │ ✓ │ View │ Download │
└─────────────────────────────────────────────────┘
```

#### 6.4 Certificate Detail Modal

```
┌─────────────────────────────────────────┐
│  Certificate Details              [X]   │
├─────────────────────────────────────────┤
│  [Large QR Code]                        │
│                                         │
│  Certificate ID: CERT-XXXXXX            │
│                                         │
│  Course: Computer Science              │
│  Degree: Bachelor                      │
│  Issue Date: December 1, 2024         │
│  Status: ✓ Active                      │
│                                         │
│  University: Tech University           │
│                                         │
│  [Download PDF]  [Share Link]          │
│                                         │
│  Verification Link:                     │
│  [http://.../verify?cert=...] [Copy]  │
└─────────────────────────────────────────┘
```

---

## 7. Profile Page (`/profile`)

**Purpose:** User profile and settings (optional)

### Sections

#### 7.1 Profile Information (Read-only)
- Username
- Email
- Role
- Full Name
- Associated University (if applicable)

#### 7.2 Change Password Form
| Field | Type | Required |
|-------|------|----------|
| Current Password | Input | Yes |
| New Password | Input | Yes |
| Confirm Password | Input | Yes |

#### 7.3 Logout Button
- Big red button at bottom
- Clears JWT token
- Redirects to login

---

## Shared Components

### Navbar
```
┌─────────────────────────────────────────────────┐
│ [Logo] Certificate System    [User ▼] [Logout] │
└─────────────────────────────────────────────────┘
```

**User Dropdown:**
- Profile
- Logout

### Sidebar (Admin/University)
```
┌──────────────┐
│ [Logo]       │
├──────────────┤
│ Dashboard    │
│ ────────────│
│ Universities │
│ Students     │
│ Certificates │
│ ────────────│
│ Settings     │
└──────────────┘
```

### Toast Notifications
| Type | Usage |
|------|-------|
| Success (green) | Certificate created, saved |
| Error (red) | Login failed, error |
| Warning (yellow) | Validation errors |
| Info (blue) | General info |

### Loading States
- Button spinner when loading
- Skeleton screens for tables
- Full page loader for initial load

### Modal Template
```
┌─────────────────────────────────────────┐
│  Title                            [X]  │
├─────────────────────────────────────────┤
│                                         │
│  Content goes here...                   │
│                                         │
├─────────────────────────────────────────┤
│  [Cancel Button]  [Confirm Button]    │
└─────────────────────────────────────────┘
```

---

## API Endpoints Used

| Page | Endpoint | Method |
|------|----------|--------|
| Login | `/api/auth/login` | POST |
| Public Verify | `/api/public/verify` | GET/POST |
| Admin: Universities | `/api/universities` | GET/POST |
| Admin: Certificates | `/api/certificates` | GET |
| Admin: Revoke | `/api/certificates/revoke` | POST |
| University: Students | `/api/students` | GET/POST |
| University: Create Cert | `/api/certificates/create` | POST |
| University: My Certs | `/api/certificates` | GET |
| Download PDF | `/api/certificates/download` | GET |
| Student: My Certs | `/api/certificates` | GET |

---

## Responsive Breakpoints

| Breakpoint | Width | Behavior |
|------------|-------|----------|
| Mobile | < 768px | Single column, hamburger menu |
| Tablet | 768px - 1024px | Condensed sidebar |
| Desktop | > 1024px | Full layout |

---

## Error Handling

| Error | Display |
|-------|---------|
| Network error | Toast: "Network error. Please try again." |
| 401 Unauthorized | Redirect to login |
| 403 Forbidden | "You don't have permission" |
| 404 Not found | "Resource not found" |
| 500 Server error | "Something went wrong" |

---

This document defines the complete frontend page structure based on backend capabilities.
