# Blockchain-Based Certificate Verification System (Backend)

The backend for the Certificate Verification System is a robust, no-framework PHP application (PHP 8.2+) designed to issue, manage, cryptographically sign, and verify academic certificates anchored to Ethereum-compatible blockchains. It implements two distinct certificate issuance flows, comprehensive RBAC (admin/university/student roles), RSA-based digital signatures, dynamic PDF generation with embedded metadata, and real-time blockchain verification with intelligent caching.

## System Overview

The Certificate Verification System uses a **unified routing architecture** with a single entry point (`api/index.php`) that orchestrates all service interactions. All certificates are cryptographically signed with university-specific RSA keys and anchored to the **Ethereum Sepolia testnet** via smart contracts. The system supports two issuance workflows:

1. **System-Generated Flow**: Backend creates certificate PDF with embedded metadata → calculates composite hash → signs with university key → anchors on blockchain
2. **University Upload Flow**: University provides external PDF → backend extracts/validates metadata → overlays QR code → signs → anchors on blockchain

Both flows ensure immutable verification through blockchain anchoring and cryptographic signatures that survive PDF transmission and storage.

## System Requirements

- **PHP**: 8.2 or higher
- **Extensions**: `ext-pdo`, `ext-pdo_mysql`, `ext-json`, `ext-curl`, `ext-openssl`, `ext-gmp` (Ethereum raw transactions), `ext-gd` or `ext-imagick` (QR codes), `ext-zlib` (PDF processing)
- **Database**: MySQL 8.0+ or MariaDB 10.4+
- **Composer**: PHP dependency manager
- **Blockchain**: Ethereum Sepolia testnet + Alchemy RPC endpoint
- **Cache**: Redis (optional; file-based fallback available)
- **Email**: SMTP credentials for password resets (Gmail tested)

## Installation & Setup

### 1. Clone & Install Dependencies
```bash
git clone <repository_url>
cd backend
composer install
```

### 2. Create Required Directories
```bash
mkdir -p storage/certificates storage/qr_codes storage/cache storage/avatars storage/assets certs/
chmod -R 775 storage certs
```

### 3. Initialize Database
```bash
# Create MySQL database
mysql -u root -p -e "CREATE DATABASE certificate_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql -u root -p certificate_db < database/schema.sql
```

### 4. Configure Environment Variables
Copy `.env.example` to `.env` (if available) or create `.env` with the following values:

```bash
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=certificate_db
DB_USER=root
DB_PASS=your_password
DB_CHARSET=utf8mb4

# Application
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000
API_URL=http://localhost/backend

# JWT Authentication (required)
JWT_SECRET=your-super-secret-jwt-key-change-in-production
JWT_ALGORITHM=HS256
JWT_EXPIRATION=86400

# Blockchain (Ethereum Sepolia Testnet)
BLOCKCHAIN_RPC=https://eth-sepolia.g.alchemy.com/v2/YOUR_ALCHEMY_KEY
CONTRACT_ADDRESS=0xYourContractAddress
BLOCKCHAIN_PRIVATE_KEY=0xYourPrivateKeyHex
BLOCKCHAIN_WALLET_ADDRESS=0xYourWalletAddress
BLOCKCHAIN_DEFAULT_ADDRESS=0xYourDefaultAddress
BLOCKCHAIN_GAS_LIMIT=3000000
BLOCKCHAIN_CHAIN_ID=11155111

# Storage Paths
STORAGE_PDF_PATH=storage/certificates/
STORAGE_QR_PATH=storage/qr_codes/
STORAGE_CERTS_PATH=certs/
STORAGE_CACHE_PATH=storage/cache/
STORAGE_BASE_URL=http://localhost/backend/storage/

# Caching (Redis or File)
CACHE_DRIVER=file
CACHE_TTL=3600
VERIFICATION_CACHE_TTL=3600
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0

# Signing & Encryption
DEFAULT_SIGNING_CERT_PATH=certs/
KEY_ENCRYPTION_SECRET=your-32-char-encryption-key-12345
REQUIRE_SIGNATURE=true

# Email (Password Resets)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@certificate-system.com
MAIL_FROM_NAME=Certificate System

# OpenSSL Config Path (Windows users must set this)
OPENSSL_CONF="C:/Program Files/Git/usr/ssl/openssl.cnf"
```

### 5. Start PHP Development Server
```bash
php -S localhost:8000 -t api
```

The API is now accessible at `http://localhost:8000/`.

## Project Workflow

### Certificate Issuance Pipeline

```
[University/Admin] 
    ↓
[Upload Data] → [/certificates/create] or [/certificates/upload]
    ↓
[CertificateService]
    ├→ Extract/Normalize Data
    ├→ Build Metadata JSON + calculate Keccak256 hash
    ├→ Generate PDF with embedded QR code
    ├→ Calculate PDF binary Keccak256 hash
    ├→ Combine hashes: onchain_hash = Keccak256(metadata_hash + pdf_hash)
    ├→ Sign onchain_hash with University RSA private key
    ├→ Embed signature in PDF XMP metadata
    ├→ Store certificate record in database
    └→ Submit to Blockchain for anchoring
    ↓
[Blockchain.php]
    ├→ Connect to Sepolia testnet via Alchemy RPC
    ├→ Call smart contract issueCertificate()
    ├→ Return blockchain_tx_hash + blockchain_mode (live/mock)
    └→ Store blockchain transaction details in DB
    ↓
[Response to Frontend]
    ├→ certificate_id
    ├→ certificate_hash
    ├→ blockchain_tx_hash
    └→ blockchain_mode: 'live' or 'mock'
```

### Certificate Verification Pipeline

```
[Anyone - No Auth Required]
    ↓
[/public/verify] (Certificate ID or PDF Upload)
    ↓
[VerificationEngine]
    ├→ Step 1: Extract certificate_id from PDF metadata or lookup by ID
    ├→ Step 2: Query database for certificate record
    ├→ Step 3: Verify certificate signature against onchain_hash (RSA validation)
    ├→ Step 4: Verify metadata matches database (JSON comparison)
    ├→ Step 5: Verify PDF hash matches stored hash
    ├→ Step 6: Query blockchain to verify certificate is anchored (5-min cache)
    ├→ Step 7: Check revocation status
    ├→ Step 8: Generate comprehensive verification report
    └→ Graceful degradation if blockchain unavailable
    ↓
[ComparisonEngine] (if PDF uploaded)
    ├→ Compare extracted metadata with database record
    ├→ Field-by-field matching with detailed diff report
    └→ Highlight discrepancies
    ↓
[Response]
    ├→ valid: boolean
    ├→ status: 'valid', 'invalid', 'revoked', 'not_found'
    ├→ signature: {signed, valid, signer}
    ├→ blockchain_valid: boolean (with live/mock indication)
    ├→ differences: metadata comparison report
    └→ conclusion: human-readable verification result
```

## Core Services

| Service | Purpose | Key Methods |
|---------|---------|-------------|
| **Auth** | User login, registration, JWT token management | login, register, generateToken, verifyToken, resetPassword |
| **Database** | Singleton PDO wrapper | getConnection |
| **CertificateService** | Issue, verify, revoke, update, delete certificates | createCertificate, uploadCertificate, revokeCertificate, getCertificate |
| **StudentService** | Student CRUD and authorization checks | getStudentById, getStudentCertificates, checkStudentAuthorization |
| **UniversityService** | University CRUD, statistics, authorization | getUniversity, getUniversityCertificates, getUniversityStats |
| **Blockchain** | Web3 RPC integration, contract interaction, mock fallback | issueCertificate, verifyCertificate, getConnectionStatus, getCurrentBlock |
| **SignatureService** | RSA key pair generation, signature creation/verification | signPDF, verifySignature, generateUniversityKeyPair |
| **PDFService** | PDF generation, metadata embedding, QR code insertion | generateCertificatePDF, extractMetadata, calculatePDFHash |
| **MetadataService** | Metadata schema normalization, Keccak256 hashing | buildMetadata, generateMetadataHash, compareMetadata |
| **VerificationEngine** | 8-step verification pipeline with caching | verifyUploadedPDF, verifyByCertificateId, verifyBlockchainCached |
| **ComparisonEngine** | Detailed metadata and field comparison | comparePDFWithDatabase, getFieldMatches |
| **PublicVerificationService** | Public verification endpoint orchestration | verifyPublic, getStoredCertificatePDF |
| **Cache** | Redis or file-based caching singleton | get, set, delete, remember |
| **EmailService** | Password reset emails via SMTP | sendPasswordResetEmail, sendEmail |
| **FileService** | Avatar upload and validation | validateImageFile, uploadAvatar |

## Database Schema Overview

**7 Main Tables:**

- `universities`: Organization registry with signing certificate paths
- `users`: Multi-role accounts (admin/university/student) with JWT auth
- `students`: Student profiles linked to users and universities
- `certificates`: Full certificate lifecycle with blockchain tracking
- `university_keys`: Encrypted RSA private keys for each university
- `verification_logs`: Audit trail of all verification attempts
- `password_resets`: Time-limited password reset tokens (15-min expiry)

See [ARCHITECTURE.md](ARCHITECTURE.md) for complete schema details.

## Caching Strategy

**3-Layer Cache System:**

1. **Blockchain Verification Cache**: 5 minutes (prevents repeated RPC calls)
   - Key: `blockchain_verify:{certificateId}:{onchainHash}`
   - Only caches successful results; failures bypass cache for retry on next request

2. **Complete Verification Cache**: 1 hour (configurable)
   - Key: `verify:{certificateId}`
   - Caches full verification result including signature and metadata comparison

3. **Fast Certificate Lookup**: 1 hour
   - Key: `cert_light:{certificateId}`
   - Quick status lookup without full verification

**Cache Invalidation:**
- Certificate updates/deletions: Immediately clear `verify:*` and `cert_light:*` keys
- Certificate revocation: Also clear `blockchain_verify:*:{onchainHash}` keys
- Cache flushes gracefully to file backend if Redis unavailable

## Environment Variables

Copy `.env.example` to `.env` (if available) and configure your system. Below is everything the application reads via `config.php`:

### Database
- `DB_HOST`: Database hostname (default: `localhost`)
- `DB_PORT`: Database port (default: `3306`)
- `DB_NAME`: Database name (e.g., `certificate_db`)
- `DB_USER`: Database user
- `DB_PASS`: Database password
- `DB_CHARSET`: Database charset (default: `utf8mb4`)

### App Configuration
- `APP_URL`: Base URL of the backend (e.g., `http://localhost:8000`)
- `FRONTEND_URL`: URL of the frontend app, used for CORS and QR code links (e.g., `http://localhost:3000`)
- `API_URL`: Direct API mounting point
- `APP_ENV`: Environment mode (e.g., `development`, `production`)
- `APP_DEBUG`: Boolean string (`true`/`false`) to toggle debug output

### JWT (Authentication)
- `JWT_SECRET`: **Required.** The secret key used to sign JWT tokens. Must not be empty.
- `JWT_ALGORITHM`: The hashing algorithm for tokens (e.g., `HS256`)
- `JWT_EXPIRATION`: Token expiration time in seconds (e.g., `86400` for 24 hours)

### Blockchain (Ethereum / Alchemy Sepolia Testnet)
- `BLOCKCHAIN_RPC`: The RPC endpoint URL (e.g., `https://eth-sepolia.g.alchemy.com/v2/YOUR_KEY`)
- `CONTRACT_ADDRESS`: Deployed smart contract address
- `BLOCKCHAIN_PRIVATE_KEY`: Private key of the transaction sender (used to pay gas)
- `BLOCKCHAIN_WALLET_ADDRESS`: The sender's public wallet address
- `BLOCKCHAIN_DEFAULT_ADDRESS`: Default sender address (fallback override)
- `BLOCKCHAIN_CHAIN_ID`: Chain ID for the network (e.g., `11155111` for Sepolia)
- `BLOCKCHAIN_GAS_LIMIT`: Gas limit for transactions (e.g., `3000000`)

### Storage Paths
- `STORAGE_PDF_PATH`: Relative path to PDF storage (e.g., `storage/certificates/`)
- `STORAGE_QR_PATH`: Relative path to QR code storage (e.g., `storage/qr_codes/`)
- `STORAGE_CERTS_PATH`: Relative path for university RSA keys (e.g., `certs/`)
- `STORAGE_CACHE_PATH`: Relative path for file cache (e.g., `storage/cache/`)
- `STORAGE_BASE_URL`: Base URL to access stored assets (e.g., `http://localhost/backend/storage/`)

### Cache
- `CACHE_DRIVER`: `redis` or `file` (defaults to `file` if Redis unavailable)
- `CACHE_TTL`: Standard cache time-to-live in seconds (default: `3600`)
- `VERIFICATION_CACHE_TTL`: TTL specifically for verification results (default: `3600`)

### Redis (if CACHE_DRIVER=redis)
- `REDIS_HOST`: Redis host (default: `127.0.0.1`)
- `REDIS_PORT`: Redis port (default: `6379`)
- `REDIS_PASSWORD`: Redis auth password (leave empty if not required)
- `REDIS_DB`: Redis database index (default: `0`)

### Signing & Encryption
- `DEFAULT_SIGNING_CERT_PATH`: Fallback university RSA private key path
- `DEFAULT_SIGNING_CERT_PASSWORD`: Password for the fallback key (if encrypted)
- `KEY_ENCRYPTION_SECRET`: **Required.** 32-character secret used to encrypt/decrypt AES-256-CBC university private keys stored in the database. Must be added to `config.php` in the `'signing'` section.
- `REQUIRE_SIGNATURE`: Boolean (`true`/`false`) whether signature validation is strictly enforced (default: `true`)

### Email (Password Resets)
- `MAIL_HOST`: SMTP server hostname (e.g., `smtp.gmail.com`)
- `MAIL_PORT`: SMTP port (e.g., `587` for TLS, `465` for SSL)
- `MAIL_USERNAME`: SMTP username
- `MAIL_PASSWORD`: SMTP password (use App Passwords for Gmail)
- `MAIL_ENCRYPTION`: Encryption type (`tls` or `ssl`)
- `MAIL_FROM_ADDRESS`: Sender email address
- `MAIL_FROM_NAME`: Sender display name

### OpenSSL Configuration
- `OPENSSL_CONF`: Path to OpenSSL config file (required on Windows)

## Running Locally

To run the backend using PHP's built-in web server, point it to the `api` folder:

```bash
php -S localhost:8000 -t api
```

The API will now be accessible at `http://localhost:8000/`.

### Using Docker (Alternative)
```bash
docker-compose up -d
```

## Running Tests

To run the integration and unit test suite, ensure PHPUnit is installed via Composer:

```bash
./vendor/bin/phpunit
# or via Composer script:
composer test
```

## API Endpoints Summary

See [docs/API.md](docs/API.md) for the complete API reference.

**Quick Reference:**
- **Authentication**: `/auth/login`, `/auth/register`, `/auth/university/register`, `/auth/verify-token`
- **Certificates**: `/certificates/create`, `/certificates/upload`, `/certificates/verify`, `/certificates/revoke`, `/certificates/list`
- **Students**: `/students`, `/students/:id`, `/students/:id/certificates`
- **Universities**: `/universities`, `/universities/:id`, `/universities/:id/students`, `/universities/:id/certificates`
- **Public Verification**: `/public/verify`, `/public/certificate/download`

## The Two Certificate Issuance Flows

### Flow 1: System Generated
A university/admin provides raw data (Student Name, Course, Issue Date, etc.) to `/certificates/create`. The backend:
1. Builds canonical JSON metadata and calculates its Keccak256 hash
2. Generates HTML document and converts to PDF using mPDF
3. Calculates PDF binary Keccak256 hash
4. Signs the combined hash (`onchain_hash = keccak256(metadata_hash + pdf_hash)`) using University's RSA private key
5. Embeds signature securely into PDF's XMP metadata block
6. Stores certificate in database with metadata
7. Anchors to blockchain via smart contract (synchronous, with graceful mock fallback)
8. Returns `blockchain_mode: 'live'` or `'mock'` in response

### Flow 2: University Upload
A university uploads a completely pre-generated PDF to `/certificates/upload`. The backend:
1. Extracts embedded metadata (certificate_id, student info) from PDF's XMP block or text
2. Validates extracted data against database
3. Repacks metadata into canonical JSON schema
4. Overlays QR code onto PDF (bottom-right corner)
5. Generates `onchain_hash`, signs it, embeds in XMP
6. Stores certificate in database
7. Anchors to blockchain (synchronous)
8. Returns blockchain transaction details

Both flows ensure the certificate is immutably anchored on Ethereum Sepolia and cryptographically signed with the university's private key.

## Blockchain Integration

**Network**: Ethereum Sepolia Testnet (ChainID: 11155111)
**RPC Provider**: Alchemy (production-grade testnet RPC)
**Smart Contract**: Deployed to Sepolia with certificate issuance and verification functions

**Key Points**:
- Certificate anchoring is **synchronous** (happens immediately during /certificates/create or /certificates/upload)
- If blockchain is unavailable, system gracefully falls back to **mock mode** (certificate still issued locally with `blockchain_mode: 'mock'`)
- Verification always queries live blockchain (with 5-min cache to reduce RPC load)
- All hashes use Keccak256 (Ethereum-native hashing)
