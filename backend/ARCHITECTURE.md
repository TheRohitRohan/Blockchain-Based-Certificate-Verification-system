# Backend Architecture — Certificate Management System

## System Overview

A PHP REST API for certificate issuance, upload, verification, and lifecycle management. Built without frameworks, using MySQL for persistence and optional Ethereum smart contract anchoring via Web3. Supports public verification, PDF digital signing, and cache-accelerated verification flows.

## Two-Flow Distinction

The system implements two distinct certificate workflows that affect PDF and signature handling:

**Flow 1 — System Generated:**
University provides student and course data via API. CertificateService generates PDF from HTML template using mPDF. XMP metadata embedded during PDF generation via `SetAdditionalXmpRdf`. QR code embedded directly in HTML template. This is the primary issuance path.

**Flow 2 — University Upload:**
University uploads externally created PDF. System extracts existing metadata, injects XMP metadata block into PDF binary via `embedMetadataIntoPDF()`, overlays QR code using FPDI, signs the certificate, and registers it. Uses different PDF mutation methods than Flow 1.

## Module Breakdown

### Database
- **File**: `src/Database.php`
- **Purpose**: Singleton PDO connection manager for all database operations.
- **Public methods**: `getConnection()`
- **Depends on**: MySQL (PDO), `config.php`
- **Notes**: Clone and unserialize blocked; single shared connection instance.

### Auth
- **File**: `src/Auth.php`
- **Purpose**: User authentication and JWT token management.
- **Public methods**: `login()`, `register()`, `getUserById()`, `generateToken()`, `verifyToken()`
- **Depends on**: Database, `config.php` (JWT settings)
- **Notes**: JWT manually implemented, no external JWT library.

### CertificateService
- **File**: `src/CertificateService.php`
- **Purpose**: Central orchestrator for certificate lifecycle operations including creation, upload, verification, updates, deletion, and revocation.
- **Public methods**: `createCertificate()`, `uploadCertificate()`, `verifyCertificate()`, `verifyUploadedPDF()`, `updateCertificate()`, `deleteCertificate()`, `listCertificates()`, `getCertificate()`, `revokeCertificate()`, `getStudentCertificates()`
- **Depends on**: Database, Blockchain, PDFService, MetadataService, SignatureService, VerificationEngine, Cache
- **Notes**: Transaction-heavy; warms cache (`cert_light`, `blockchain_verify`) after successful creation; supports both Flow 1 and Flow 2.

### Blockchain
- **File**: `src/Blockchain.php`
- **Purpose**: Ethereum smart contract integration with transparent mock fallback.
- **Public methods**: `isConnected()`, `getCurrentBlock()`, `getAdmin()`, `verifyCertificate()`, `generateCertificateHash()`, `generateKeccak256Hash()`, `generateCombinedHash()`, `issueCertificate()`, `getCertificate()`, `revokeCertificate()`
- **Depends on**: `config.php`, `abi/CertificateRegistry.json`, Ethereum RPC, Web3 libraries
- **Notes**: Mock mode is implicit when config or connection unavailable (not explicit env switch). Mock transaction hashes are indistinguishable from real ones in database.

### VerificationEngine
- **File**: `src/VerificationEngine.php`
- **Purpose**: Deep verification orchestrator for both certificate ID and uploaded PDF verification flows.
- **Public methods**: `verifyUploadedPDF()`, `verifyByCertificateId()`, `invalidateBlockchainCache()`
- **Depends on**: Database, Blockchain, PDFService, SignatureService, MetadataService, ComparisonEngine, Cache
- **Notes**: Multi-level caching with keys `verify`, `cert_light`, `blockchain_verify`; logs to `verification_logs` table; blockchain_verify TTL is 300s here.

### PDFService
- **File**: `src/PDFService.php`
- **Purpose**: PDF generation, metadata embedding/extraction, QR code operations, and hash computation.
- **Public methods**: `generateCertificatePDF()`, `embedMetadata()`, `getPDFPath()`, `extractMetadata()`, `extractText()`, `calculatePDFHash()`, `insertQRCode()`, `generateQRCodeFileName()`, `embedMetadataIntoPDF()`, `addQRCodeToExistingPDF()`
- **Depends on**: Database, MetadataService, mPDF, FPDI, Smalot\PdfParser, Endroid QR, Keccak, filesystem
- **Notes**: Flow 1 uses `SetAdditionalXmpRdf` for XMP; Flow 2 uses `embedMetadataIntoPDF()` for binary mutation. `calculatePDFHash()` returns 0x-prefixed keccak256 hex. `embedMetadata()` is deliberate no-op stub for compatibility.

### MetadataService
- **File**: `src/MetadataService.php`
- **Purpose**: Canonical metadata construction, normalization, hashing, and comparison.
- **Public methods**: `buildMetadata()`, `normalizeMetadata()`, `generateMetadataJson()`, `generateMetadataHash()`, `extractMetadata()`, `compareMetadata()`, `getSchemaVersion()`
- **Depends on**: Keccak
- **Notes**: Schema version 1.0; `buildMetadata()` tolerates missing fields via empty string defaults; `generateMetadataJson()` is deterministic with sorted keys.

### Cache
- **File**: `src/Cache.php`
- **Purpose**: Cache abstraction layer with Redis primary and filesystem fallback.
- **Public methods**: `get()`, `set()`, `delete()`, `flush()`, `remember()`
- **Depends on**: Predis, filesystem, `config.php`
- **Notes**: Singleton; auto-falls back to file driver if Redis connection fails.

### ComparisonEngine
- **File**: `src/ComparisonEngine.php`
- **Purpose**: Field-by-field comparison of uploaded PDF against database certificate record.
- **Public methods**: `comparePDFWithDatabase()`
- **Depends on**: Database, PDFService, MetadataService
- **Notes**: Returns structured match/diff result; catches all exceptions internally.

### SignatureService
- **File**: `src/SignatureService.php`
- **Purpose**: RSA-based PDF signing/verification and university key pair management.
- **Public methods**: `signPDF()`, `verifySignature()`, `generateUniversityKeyPair()`
- **Depends on**: Database, OpenSSL, filesystem (`certs/` directory), `config.php`
- **Notes**: Signs the stable onchainHash string (not PDF binary) to prevent sign/verify mismatch from XMP modifications. Signature embedded in XMP under `cert:signature` and `cert:signer` tags. Private keys encrypted with AES-256-CBC and stored in `university_keys.certificate_password`; legacy plain-base64 keys supported with auto-detect. KEY_ENCRYPTION_SECRET must be 32 characters in config.

### PublicVerificationService
- **File**: `src/PublicVerificationService.php`
- **Purpose**: Public endpoint orchestration for verification via certificate ID or uploaded PDF with rich response payloads.
- **Public methods**: `verifyPublic()`, `getStoredCertificatePDF()`
- **Depends on**: Database, VerificationEngine, ComparisonEngine, PDFService
- **Notes**: All helper methods are private; returns public-facing structured responses.

---

## API Surface

| Method | Endpoint | Auth | Handler |
|--------|----------|------|---------|
| POST | `/api/auth/login` | No | `Auth::login` + `Auth::generateToken` |
| POST | `/api/auth/register` | No | `Auth::register` |
| POST | `/api/certificates/create` | Yes (`university`, `admin`) | `CertificateService::createCertificate` |
| POST | `/api/certificates/upload` | Yes (`university`, `admin`) | `CertificateService::uploadCertificate` |
| POST | `/api/certificates/verify` | No | `CertificateService::verifyUploadedPDF` or `verifyCertificate` |
| GET/POST | `/api/public/verify` | No | `PublicVerificationService::verifyPublic` |
| GET | `/api/public/certificate/download` | No | `PublicVerificationService::getStoredCertificatePDF` |
| GET | `/api/certificates` | Yes (any valid JWT) | `CertificateService::getStudentCertificates` + inline DB |
| POST | `/api/certificates/revoke` | Yes (`admin`, `university`) | `CertificateService::revokeCertificate` |
| GET | `/api/certificates/download` | Yes | `PDFService::getPDFPath` / `generateCertificatePDF` |
| GET | `/api/universities` | No | Inline DB query |
| POST | `/api/universities` | Yes (`admin`) | Inline DB insert |
| GET | `/api/students` | Yes (`university`, `admin`) | Inline DB query |
| POST | `/api/students` | Yes (`university`, `admin`) | `Auth::register` + inline DB insert |
| GET | `/api/certificates/get` | No | `CertificateService::getCertificate` |
| PUT/POST | `/api/certificates/update` | Yes (`university`, `admin`) | `CertificateService::updateCertificate` |
| DELETE/POST | `/api/certificates/delete` | Yes (`admin`) | `CertificateService::deleteCertificate` |
| GET | `/api/certificates/list` | Yes (`university`, `admin`) | `CertificateService::listCertificates` |
| POST | `/api/universities/generate-key` | Yes (`admin`) | `SignatureService::generateUniversityKeyPair` |

---

## Dependency Map

```
Database
└── MySQL (PDO)

Auth
├── Database
└── config.php (jwt)

MetadataService
└── Keccak

Cache
├── Redis (Predis, optional)
└── Filesystem fallback

PDFService
├── Database
├── MetadataService
├── Filesystem (pdf/qr storage)
├── mPDF
├── FPDI
├── Smalot\PdfParser
├── Endroid QR
└── Keccak

SignatureService
├── Database
├── OpenSSL
├── Filesystem (certs/ directory)
└── config.php

Blockchain
├── config.php (RPC/contract/private key)
├── abi/CertificateRegistry.json
└── Ethereum RPC (Web3/Contract libs)

ComparisonEngine
├── Database
├── PDFService
└── MetadataService

VerificationEngine
├── Database
├── Blockchain
├── PDFService
├── SignatureService
├── MetadataService
├── ComparisonEngine
└── Cache

CertificateService
├── Database
├── Blockchain
├── PDFService
├── MetadataService
├── SignatureService
├── VerificationEngine
└── Cache

PublicVerificationService
├── Database
├── VerificationEngine
├── ComparisonEngine
└── PDFService

API (api/index.php)
├── Auth
├── CertificateService
├── PublicVerificationService
├── SignatureService
└── Database (inline queries in some routes)
```

---

## Database Tables

- **universities**: `id`, `name`, `code`, contact fields, wallet/signing config, `is_active`
- **users**: `id`, `username`, `email`, `password_hash`, `role`, `full_name`, `university_id`
- **students**: `id`, `user_id`, `student_id`, `university_id`, enrollment info
- **certificates**: `certificate_id`, `student_id`, `university_id`, course/degree/date, status/revocation fields, blockchain tx/hash columns, metadata/pdf hash columns, `metadata_json`, `signature_status`
- **verification_logs**: `certificate_id`, `verifier_ip`, `verification_method`, `verification_result`, `verification_details`, `verified_at`
- **university_keys**: `university_id`, `certificate_path`, `certificate_password` (AES-256-CBC encrypted private key PEM), `public_key_pem`, `key_fingerprint`, `is_active`

---

## Cache Key Reference

- `verify:{certificate_id}` — full verification result; TTL `cache.verification_ttl` (default 3600s)
- `cert_light:{certificate_id}` — lightweight verification result; TTL `cache.verification_ttl` (default 3600s)
- `blockchain_verify:{certificate_id}:{onchain_hash}` — blockchain verification result; TTL 300s in VerificationEngine, 3600s when warmed by CertificateService (last write wins — known inconsistency)

---

## Known Issues

1. **Non-functional `/api/universities/generate-key` endpoint**: `$signatureService` was never instantiated in `api/index.php`. The route handler logic is correct, but the service object does not exist at runtime. Fix: instantiate `SignatureService` at the top of `api/index.php` alongside `Auth` and `CertificateService`, and add `use App\SignatureService;` to the use block.

2. **Mock blockchain transaction hashes are indistinguishable from real ones**: When operating in implicit mock mode (due to missing config or connection), generated transaction hashes are stored in the `certificates` table without any flag or marker to indicate they are mock. This can lead to false trust in verification results if mock mode is accidentally used in production.

3. **`blockchain_verify` cache TTL conflict**: `VerificationEngine` writes the `blockchain_verify` key with a 300-second TTL, but `CertificateService::warmupCertificateCache()` overwrites it with a 3600-second TTL. Last write wins; this creates inconsistent cache behavior across verification flows.

4. **Silent signing failure in `createCertificate()`**: If PDF signing fails in `CertificateService::createCertificate()`, the error is logged as a warning and execution silently continues. The certificate is issued and stored with no signature and no error returned to the caller, creating a discrepancy between expected and actual certificate state.
