### 📦 Module: API (backend/api/index.php)
- **Purpose:** HTTP entrypoint exposing authentication, certificate creation/upload/verification, public verification and download.
- **Files:** `api/index.php` — front controller handling routing, CORS, JWT extraction, and dispatch to services.
- **Entry points:** `/auth/login`, `/auth/register`, `/certificates/create`, `/certificates/upload`, `/certificates/verify`, `/public/verify`, `/public/certificate/download`.
- **Connects to:** Auth, CertificateService, PublicVerificationService, Database (via services), filesystem for uploads, HTTP clients.
- **Data models:** Users, certificates, verification_logs, universities, students (through service queries).

### 📦 Module: Database
- **Purpose:** Singleton PDO connection factory using config/env for MySQL.
- **Files:** `src/Database.php` — builds DSN, manages PDO options.
- **Entry points:** `Database::getInstance()->getConnection()`.
- **Connects to:** MySQL (certificates, users, students, universities, verification_logs, university_keys).
- **Data models:** Connection layer only; no models.

### 📦 Module: Auth
- **Purpose:** User authentication, registration, and JWT issuance/verification.
- **Files:** `src/Auth.php` — login/register methods, token generation/validation.
- **Entry points:** `/auth/login`, `/auth/register` routes; `generateToken`, `verifyToken`.
- **Connects to:** Database (users table), config (`jwt` secret/expiry).
- **Data models:** Users.

### 📦 Module: CertificateService
- **Purpose:** Core certificate lifecycle: create, upload/process, verify, update, delete, list, revoke, student listings.
- **Files:** `src/CertificateService.php` — orchestrates metadata building, PDF generation, QR management, signing, hashing, blockchain anchoring, DB persistence, cache warming.
- **Entry points:** Called by API routes and VerificationEngine (`verifyCertificate`, `verifyUploadedPDF`).
- **Connects to:** Database (certificates, students, users, universities, verification_logs), Blockchain, PDFService, SignatureService, MetadataService, VerificationEngine, Cache, filesystem storage paths, QR assets.
- **Data models:** certificates, students, users, universities, verification_logs, university_keys (via signing), metadata_json/hashes, onchain_hash, pdf_path, qr_code_path.

### 📦 Module: PDFService
- **Purpose:** Generate and manipulate certificate PDFs, embed metadata, add QR codes, extract metadata/text, compute hashes.
- **Files:** `src/PDFService.php` — uses mPDF/FPDI/Smalot PdfParser/Endroid QR; ensures storage paths.
- **Entry points:** `generateCertificatePDF`, `extractMetadata`, `extractText`, `calculatePDFHash`, `embedMetadataIntoPDF`, `addQRCodeToExistingPDF`, `generateQRCodeFileName`, `getPDFPath`.
- **Connects to:** Database (update pdf_path), MetadataService, filesystem storage (`storage/pdf/`, `storage/qr/`), external libs (mPDF, FPDI, Endroid QR, Smalot PdfParser), config.
- **Data models:** certificates (pdf_path), derived metadata JSON/hash.

### 📦 Module: SignatureService
- **Purpose:** Sign and verify PDFs using RSA keys; manage university key pairs.
- **Files:** `src/SignatureService.php` — signPDF, verifySignature, generateUniversityKeyPair, XMP embedding helpers.
- **Entry points:** Invoked by CertificateService and VerificationEngine.
- **Connects to:** Database (`university_keys`), filesystem (cert storage), OpenSSL, config signing defaults.
- **Data models:** university_keys table (key material, fingerprints), certificates signature_status via callers.

### 📦 Module: MetadataService
- **Purpose:** Canonicalize certificate metadata, generate JSON and Keccak hashes, compare metadata sets.
- **Files:** `src/MetadataService.php`.
- **Entry points:** Used by CertificateService, PDFService, ComparisonEngine, VerificationEngine.
- **Connects to:** kornrunner/keccak library.
- **Data models:** Logical metadata fields (certificate_id, student_id, names, course, degree_type, issue_date, university_code/name).

### 📦 Module: ComparisonEngine
- **Purpose:** Compare PDF content/metadata with database records field-by-field and by hash.
- **Files:** `src/ComparisonEngine.php`.
- **Entry points:** `comparePDFWithDatabase`.
- **Connects to:** Database (certificates/students/users/universities), PDFService (metadata/hash extraction), MetadataService.
- **Data models:** certificates (metadata_hash, pdf_hash), students, users, universities.

### 📦 Module: VerificationEngine
- **Purpose:** End-to-end verification flows for uploaded PDFs or ID-based checks, combining signature, metadata, hash, blockchain, cache, and logging.
- **Files:** `src/VerificationEngine.php`.
- **Entry points:** `verifyUploadedPDF`, `verifyByCertificateId`, cache invalidation helpers.
- **Connects to:** Database (certificates, verification_logs, students/users/universities), Blockchain, PDFService, SignatureService, MetadataService, ComparisonEngine, Cache, filesystem (pdfs).
- **Data models:** certificates, verification_logs.

### 📦 Module: PublicVerificationService
- **Purpose:** Public-facing verification orchestration for uploads or ID queries, comparison summaries, and PDF retrieval.
- **Files:** `src/PublicVerificationService.php`.
- **Entry points:** `verifyPublic`, `getStoredCertificate`, `getStoredCertificatePDF`.
- **Connects to:** Database (certificates/students/users/universities), VerificationEngine, ComparisonEngine, PDFService, filesystem (stored PDFs), config.
- **Data models:** certificates, verification summaries.

### 📦 Module: Blockchain
- **Purpose:** Interface to Ethereum-compatible smart contract for issuing/verifying/revoking certificates with mock fallback.
- **Files:** `src/Blockchain.php` — connectivity setup, hash generation, contract calls, mock behavior when offline.
- **Entry points:** `issueCertificate`, `verifyCertificate`, `revokeCertificate`, `getCertificate`, `generateCombinedHash`, `generateKeccak256Hash`, `getCurrentBlock`.
- **Connects to:** Web3 provider, contract ABI (`abi/CertificateRegistry.json`), config blockchain credentials, phpseclib BigInteger.
- **Data models:** On-chain certificate data (hashes, status), linked via certificate_id/hash.

### 📦 Module: Cache
- **Purpose:** Caching abstraction supporting Redis or filesystem.
- **Files:** `src/Cache.php`.
- **Entry points:** `get`, `set`, `delete`, `flush`, `remember`.
- **Connects to:** Predis (optional), filesystem storage/cache, config cache settings.
- **Data models:** Cached verification results and hashes (keys like verify:, cert_light:, blockchain_verify:).

### 📦 Module: test utilities
- **Purpose:** Developer scripts for QR sizing/generation.
- **Files:** `test_qr.php`, `test_qr_size.php`.
- **Entry points:** CLI.
- **Connects to:** PDFService/QR generation.
- **Data models:** None.

### 📦 Dependency Map
- API → Auth, CertificateService, PublicVerificationService.
- CertificateService → Database, Blockchain, PDFService, SignatureService, MetadataService, VerificationEngine, Cache.
- VerificationEngine → Database, Blockchain, PDFService, SignatureService, MetadataService, ComparisonEngine, Cache.
- PublicVerificationService → VerificationEngine, ComparisonEngine, PDFService, Database.
- PDFService → Database, MetadataService, filesystem, external PDF/QR libs.
- SignatureService → Database (university_keys), OpenSSL, config.
- ComparisonEngine → Database, PDFService, MetadataService.
- MetadataService → kornrunner/keccak.
- Blockchain → web3/contract ABI, config.
- Cache → Predis or filesystem, config.
- Auth → Database, config (JWT).
- Database → MySQL via PDO.
