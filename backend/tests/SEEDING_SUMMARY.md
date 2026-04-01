# Database Cleanup & Seeding Summary

## Status: ✅ COMPLETE

All non-admin data has been cleaned and fresh data has been added.

---

## Admin User (Preserved)
- **Email**: admin@certificate-system.com
- **Role**: admin
- **Password**: (unchanged)

---

## Universities Added (5 Total)

### 1. Global Institute of Technology (GIT)
- **ID**: 3
- **Code**: GIT
- **Address**: 123 Tech Street, Silicon Valley, CA 94025
- **Email**: admin@git.edu
- **Phone**: +1-650-123-4567
- **Status**: Active
- **RSA Keys**: Generated ✓

### 2. National University of Engineering (NUE)
- **ID**: 4
- **Code**: NUE
- **Address**: 456 Engineering Ave, Boston, MA 02115
- **Email**: admin@nue.edu
- **Phone**: +1-617-123-4567
- **Status**: Active
- **RSA Keys**: Generated ✓

### 3. Oxford International College (OIC)
- **ID**: 5
- **Code**: OIC
- **Address**: 789 Academic Road, London, UK
- **Email**: admin@oic.ac.uk
- **Phone**: +44-20-1234-5678
- **Status**: Active
- **RSA Keys**: Generated ✓

### 4. University of Advanced Studies (UAS)
- **ID**: 6
- **Code**: UAS
- **Address**: 321 Innovation Drive, Toronto, ON
- **Email**: admin@uas.ca
- **Phone**: +1-416-123-4567
- **Status**: Active
- **RSA Keys**: Generated ✓

### 5. Asian Institute of Business & Technology (AIBT)
- **ID**: 7
- **Code**: AIBT
- **Address**: 654 Development Road, Singapore 039594
- **Email**: admin@aibt.sg
- **Phone**: +65-6123-4567
- **Status**: Active
- **RSA Keys**: Generated ✓

---

## Students Added (25 Total - 5 per University)

### Global Institute of Technology (GIT)
| # | Student ID | Email | Username | Password |
|---|---|---|---|---|
| 1 | GIT-0001 | gitstd001@git.edu | gitstd001 | Student@123! |
| 2 | GIT-0002 | gitstd002@git.edu | gitstd002 | Student@123! |
| 3 | GIT-0003 | gitstd003@git.edu | gitstd003 | Student@123! |
| 4 | GIT-0004 | gitstd004@git.edu | gitstd004 | Student@123! |
| 5 | GIT-0005 | gitstd005@git.edu | gitstd005 | Student@123! |

### National University of Engineering (NUE)
| # | Student ID | Email | Username | Password |
|---|---|---|---|---|
| 6 | NUE-0006 | nuestd006@nue.edu | nuestd006 | Student@123! |
| 7 | NUE-0007 | nuestd007@nue.edu | nuestd007 | Student@123! |
| 8 | NUE-0008 | nuestd008@nue.edu | nuestd008 | Student@123! |
| 9 | NUE-0009 | nuestd009@nue.edu | nuestd009 | Student@123! |
| 10 | NUE-0010 | nuestd010@nue.edu | nuestd010 | Student@123! |

### Oxford International College (OIC)
| # | Student ID | Email | Username | Password |
|---|---|---|---|---|
| 11 | OIC-0011 | oicstd011@oic.edu | oicstd011 | Student@123! |
| 12 | OIC-0012 | oicstd012@oic.edu | oicstd012 | Student@123! |
| 13 | OIC-0013 | oicstd013@oic.edu | oicstd013 | Student@123! |
| 14 | OIC-0014 | oicstd014@oic.edu | oicstd014 | Student@123! |
| 15 | OIC-0015 | oicstd015@oic.edu | oicstd015 | Student@123! |

### University of Advanced Studies (UAS)
| # | Student ID | Email | Username | Password |
|---|---|---|---|---|
| 16 | UAS-0016 | uasstd016@uas.edu | uasstd016 | Student@123! |
| 17 | UAS-0017 | uasstd017@uas.edu | uasstd017 | Student@123! |
| 18 | UAS-0018 | uasstd018@uas.edu | uasstd018 | Student@123! |
| 19 | UAS-0019 | uasstd019@uas.edu | uasstd019 | Student@123! |
| 20 | UAS-0020 | uasstd020@uas.edu | uasstd020 | Student@123! |

### Asian Institute of Business & Technology (AIBT)
| # | Student ID | Email | Username | Password |
|---|---|---|---|---|
| 21 | AIBT-0021 | aibtstd021@aibt.edu | aibtstd021 | Student@123! |
| 22 | AIBT-0022 | aibtstd022@aibt.edu | aibtstd022 | Student@123! |
| 23 | AIBT-0023 | aibtstd023@aibt.edu | aibtstd023 | Student@123! |
| 24 | AIBT-0024 | aibtstd024@aibt.edu | aibtstd024 | Student@123! |
| 25 | AIBT-0025 | aibtstd025@aibt.edu | aibtstd025 | Student@123! |

---

## What Was Cleaned

✓ All non-admin users deleted  
✓ All students deleted  
✓ All certificates deleted  
✓ All universities deleted  

---

## What Was Added

✓ 5 Universities with complete details  
✓ 25 Students (5 per university)  
✓ RSA Key pairs for all universities  
✓ All data is ready for API usage  

---

## Notes

- All passwords follow the pattern: `Student@123!`
- All students are set to enrollment date: Today
- Universities are active and ready for certificate issuance
- RSA keys stored in: `backend/storage/certs/`
- Admin data preserved for authentication
