# Certificate Verification System - Setup Guide

## Prerequisites

| Software | Version | Purpose |
|----------|---------|---------|
| Node.js | v16+ | Frontend runtime |
| PHP | 7.4+ | Backend runtime |
| MySQL | 5.7+ | Database |
| Composer | 2.0+ | PHP dependencies |
| Ganache | Latest | Local blockchain |

---

## Quick Start

### 1. Database Setup
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE certificate_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql -u root -p certificate_db < backend/database/schema.sql
```

### 2. Backend Setup
```bash
cd backend
composer install

# Configure database in config.php (edit the file)
# Set DB_HOST, DB_NAME, DB_USER, DB_PASS
```

### 3. Blockchain Setup (Optional - runs in mock mode without)
```bash
cd contracts
npm install
truffle compile
truffle migrate --network ganache
```

### 4. Start Backend
```bash
cd backend
php -S localhost:8080
```

### 5. Start Frontend
```bash
cd frontend
npm install
npm start
```

---

## Default Login
- **URL**: http://localhost:3000
- **Email**: admin@certificate-system.com
- **Password**: admin123

---

## Configuration Files

### Backend (backend/config.php)
```php
'database' => [
    'host' => 'localhost',
    'dbname' => 'certificate_db',
    'username' => 'root',
    'password' => 'your_password',
],

'blockchain' => [
    'rpc_url' => 'http://127.0.0.1:7545',
    'contract_address' => '0x...', // After truffle migrate
    'private_key' => '0x...',      // From Ganache
],
```

### Frontend (frontend/.env)
```env
REACT_APP_API_URL=http://localhost:8080
REACT_APP_BASE_URL=http://localhost:3000
```

---

## Project Structure
```
certi/
├── backend/          # PHP API
│   ├── api/          # REST endpoints
│   ├── src/          # Business logic
│   ├── storage/      # PDFs, QR codes
│   └── tests/        # PHP tests
├── frontend/         # React app
├── contracts/        # Solidity smart contracts
└── docs/            # Documentation
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| API returns 404 | Run `php -S localhost:8080` in backend folder |
| Database connection error | Check MySQL is running and credentials in config.php |
| Blockchain timeout | System works in mock mode without Ganache |
| CORS errors | Ensure backend runs on correct port |

---

## Testing
```bash
# Backend tests
cd backend/tests
php run_all_tests.php
```
