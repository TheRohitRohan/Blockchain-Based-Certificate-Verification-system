# Blockchain-Based Certificate Verification System (Backend)

The backend for the Certificate Verification System is a robust, no-framework PHP application designed to issue, manage, and cryptographically verify academic certificates. It leverages Ethereum smart contracts for immutable anchoring, features robust RBAC (Role-Based Access Control) for admins, universities, and students, and manages secure digital signatures and dynamic PDF generation. It acts as the central API point for the frontend, handling all database interactions and blockchain transactions.

## System Requirements

- **PHP**: ^8.2 or higher
- **Extensions**: `ext-pdo`, `ext-pdo_mysql`, `ext-json`, `ext-curl`, `ext-openssl`, `ext-gmp` (required for raw Ethereum transactions), `ext-gd` or `ext-imagick` (for QR code generation capability), `ext-zlib` (for PDF processing)
- **Database**: MySQL 8.0+ or MariaDB 10.4+
- **Composer**: Dependency manager for PHP

## Installation & Setup

1. **Clone the repository**
   ```bash
   git clone <repository_url>
   cd backend
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Verify directory permissions**
   The application requires certain directories to exist and be fully writable by the web server user. Create them if they do not exist:
   ```bash
   mkdir -p storage/pdfs storage/qr storage/cache certs/
   chmod -R 775 storage certs
   ```

4. **Initialize Database**
   Create a MySQL database and run the `database/schema.sql` file to instantiate all necessary tables and the default admin user.

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

### Blockchain (Ethereum / Alchemy)
- `BLOCKCHAIN_RPC`: The RPC endpoint URL (e.g., your Alchemy URL for Sepolia)
- `CONTRACT_ADDRESS`: Deployed smart contract address
- `BLOCKCHAIN_PRIVATE_KEY`: Private key of the transaction sender (used to pay gas)
- `BLOCKCHAIN_DEFAULT_ADDRESS`: Used to override the sender address derivation
- `BLOCKCHAIN_WALLET_ADDRESS`: The sender's public wallet address
- `BLOCKCHAIN_GAS_LIMIT`: Gas limit for transactions (e.g., `3000000`)
- `BLOCKCHAIN_CHAIN_ID`: Chain ID for the network (e.g., `11155111` for Sepolia)

### Storage
- `STORAGE_PDF_PATH`: Relative path to PDF storage (e.g., `storage/pdfs/`)
- `STORAGE_QR_PATH`: Relative path to QR code storage (e.g., `storage/qr/`)
- `STORAGE_CERTS_PATH`: Relative path for university keys (e.g., `certs/`)
- `STORAGE_CACHE_PATH`: Relative path for file cache (e.g., `storage/cache/`)
- `STORAGE_BASE_URL`: Base URL to access stored assets

### Cache (Redis / File)
- `CACHE_DRIVER`: `redis` or `file` (defaults to `file` if Redis fails)
- `CACHE_TTL`: Standard cache time-to-live in seconds
- `VERIFICATION_CACHE_TTL`: TTL specifically for verification results

### Redis (if CACHE_DRIVER=redis)
- `REDIS_HOST`: Redis host
- `REDIS_PORT`: Redis port (e.g., `6379`)
- `REDIS_PASSWORD`: Redis auth password
- `REDIS_DB`: Redis database index

### Signing
- `DEFAULT_SIGNING_CERT_PATH`: Fallback university RSA private key path 
- `DEFAULT_SIGNING_CERT_PASSWORD`: Password for the fallback key (if encrypted)
- `REQUIRE_SIGNATURE`: Boolean (`true`/`false`) whether signature validation is strictly enforced
- `KEY_ENCRYPTION_SECRET`: Required. 32-character secret used by SignatureService to encrypt/decrypt AES-256-CBC university private keys in the DB.
  Note: This must also be manually added to the 'signing' section of config.php as 'key_encryption_secret' — it is not wired in by default.

## Running Locally

To run the backend using PHP's built-in web server, point it to the `api` folder:

```bash
php -S localhost:8000 -t api
```

The API will now be accessible at `http://localhost:8000/`.

## Running Tests

To run the integration and unit test suite, ensure PHPUnit is installed via Composer:

```bash
./vendor/bin/phpunit
# or simply:
composer test
```

## The Two Certificate Flows

The system architecture implements two distinct paths for getting certificates onto the blockchain and into the database. Developers must understand this distinction:

### Flow 1: System Generated
In this flow, a university/admin provides raw data (Student Name, Course, Issue Date, etc.) to the `/certificates/create` endpoint. The backend:
1. Builds a canonical JSON metadata schema and calculates its hash.
2. Generates an HTML document and converts it to a PDF using `mPDF`.
3. Calculates the hash of the generated PDF.
4. Cryptographically signs an **onchain hash** (combination of the Metadata Hash and PDF Hash) using the University's RSA private key and embeds the signature securely into the PDF's XMP metadata.
5. Anchors the data on the blockchain via a smart contract. The response includes a `blockchain_mode` field (`'live'` or `'mock'`) indicating if real anchoring occurred.

### Flow 2: University Upload
In this flow, a university uploads a completely pre-existing PDF to the `/certificates/upload` endpoint. The backend:
1. Extracts the embedded metadata (like `certificate_id`) from the PDF's XMP block or text layout.
2. Validates it against the DB.
3. Repacks a canonical metadata set and embeds a new QR code over the first page of the PDF.
4. Generates the `onchain_hash`, digitally signs it, and embeds the signature.
5. Anchors the data on the blockchain.

## Detailed Documentation

Please refer to the `docs/` folder for in-depth system guidelines:
- [API Reference](docs/API.md)
- [Architecture & Design](ARCHITECTURE.md)
- [Blockchain Integration](docs/BLOCKCHAIN.md)
- [Frontend Integration Guide](docs/FRONTEND_INTEGRATION.md)
