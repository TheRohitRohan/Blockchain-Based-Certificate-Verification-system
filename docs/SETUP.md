# Complete Setup Guide

This comprehensive guide will walk you through setting up the Certificate Verification System from scratch.

## 📋 Prerequisites Checklist

### Required Software
- [ ] **Node.js** (v16 or higher) - [Download](https://nodejs.org/)
- [ ] **PHP** (v7.4 or higher) - [Download](https://www.php.net/downloads.php)
- [ ] **MySQL** (v5.7 or higher) - [Download](https://www.mysql.com/downloads/)
- [ ] **Composer** (for PHP dependencies) - [Download](https://getcomposer.org/)
- [ ] **Ganache** (local blockchain) - [Download](https://trufflesuite.com/ganache/)

### System Requirements
- **RAM**: Minimum 4GB, Recommended 8GB+
- **Storage**: Minimum 2GB free space
- **OS**: Windows 10+, macOS 10.14+, Ubuntu 18.04+

---

## 🚀 Step-by-Step Installation

### Step 1: Environment Setup

#### 1.1 Verify Node.js Installation
```bash
node --version  # Should be v16.0.0 or higher
npm --version   # Should be v8.0.0 or higher
```

#### 1.2 Verify PHP Installation
```bash
php --version    # Should be 7.4.0 or higher
composer --version  # Should be v2.0.0 or higher
```

#### 1.3 Verify MySQL
```bash
mysql --version  # Should be 5.7.0 or higher
```

#### 1.4 Install Ganache
1. Download from [https://trufflesuite.com/ganache/](https://trufflesuite.com/ganache/)
2. Install and launch Ganache
3. Click **"QUICKSTART"** to create a new workspace
4. **Important**: Note the following from Ganache interface:
   - **RPC Server**: `http://127.0.0.1:7545` (default)
   - **First Account Address**: Copy this address
   - **Private Key**: Click the key icon and copy the private key

---

### Step 2: Database Setup

#### 2.1 Create Database
```bash
# Log into MySQL
mysql -u root -p

# Create database
CREATE DATABASE certificate_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create user (optional but recommended)
CREATE USER 'certi_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON certificate_db.* TO 'certi_user'@'localhost';
FLUSH PRIVILEGES;

EXIT;
```

#### 2.2 Import Database Schema
```bash
# Navigate to project root
cd "D:\Screenshots\Project Sem 8\certi"

# Import schema
mysql -u root -p certificate_db < backend/database/schema.sql
```

---

### Step 3: Backend Configuration

#### 3.1 Install PHP Dependencies
```bash
cd backend
composer install
```

#### 3.2 Configure Application
```bash
# Copy configuration template
cp config.example.php config.php

# Edit configuration (use your preferred editor)
notepad config.php  # Windows
# or
nano config.php     # Linux/macOS
```

Update the following sections in `config.php`:

```php
'database' => [
    'host' => 'localhost',
    'dbname' => 'certificate_db',
    'username' => 'root',           // or 'certi_user' if created
    'password' => 'your_mysql_password',
    'charset' => 'utf8mb4'
],

'blockchain' => [
    'rpc_url' => 'http://127.0.0.1:7545',
    'contract_address' => '',          // Will fill after deployment
    'private_key' => 'YOUR_PRIVATE_KEY',   // From Ganache Step 1
    'default_address' => 'YOUR_ACCOUNT_ADDRESS', // From Ganache Step 1
    'gas_limit' => 3000000
],

'app' => [
    'base_url' => 'http://localhost:3000',
    'api_url' => 'http://localhost/backend'
]
```

---

### Step 4: Blockchain Setup

#### 4.1 Install Node.js Dependencies
```bash
cd contracts
npm install
npm install -g truffle
```

#### 4.2 Configure Truffle
Edit `contracts/truffle-config.js` if needed:
```javascript
module.exports = {
  networks: {
    ganache: {
      host: "127.0.0.1",
      port: 7545,
      network_id: "*"
    }
  },
  compilers: {
    solc: {
      version: "0.8.19"
    }
  }
};
```

#### 4.3 Compile Smart Contracts
```bash
cd contracts
truffle compile
```

#### 4.4 Deploy to Ganache
```bash
truffle migrate --network ganache
```

**Successful deployment output will show:**
```
Starting migrations...
======================
> Network name:    'ganache'
> Network id:      5777

1_initial_migration.js
======================
   Replacing 'CertificateRegistry'
   -------------------------------
   > transaction hash:    0x...
   > contract address:    0xEB352b98B9CCDab750E7a99E7fb0CE740Baedfcf
   > block number:        2
```

#### 4.5 Update Backend Config
Copy the **contract address** from deployment output and update `backend/config.php`:

```php
'blockchain' => [
    'contract_address' => '0xEB352b98B9CCDab750E7a99E7fb0CE740Baedfcf', // Your deployed address
    // ... other config
]
```

---

### Step 5: Frontend Setup

#### 5.1 Install Dependencies
```bash
cd frontend
npm install
```

#### 5.2 Configure API Connection
Create `.env` file in frontend directory:
```env
REACT_APP_API_URL=http://localhost/backend
REACT_APP_BASE_URL=http://localhost:3000
```

#### 5.3 Start Development Server
```bash
npm start
```

---

### Step 6: Start Backend Server

#### 6.1 Start PHP Development Server
```bash
cd backend
php -S localhost:8000
```

#### 6.2 Alternative: Apache/Nginx Setup
For production, configure your web server to serve the `backend/api` directory.

---

## ✅ Verification & Testing

### Step 7: System Verification

#### 7.1 Test Blockchain Connection
```bash
cd backend/tests
php test_connection.php
```

**Expected output:**
```
=== Simple Blockchain Connection Test ===
✅ Blockchain class instantiated
✅ Current block: 2
✅ Connection successful!
```

#### 7.2 Run All Tests
```bash
cd backend/tests
php run_all_tests.php
```

#### 7.3 Test Full Application
1. Open browser: http://localhost:3000
2. Login with default credentials:
   - **Email**: admin@certificate-system.com
   - **Password**: admin123
3. Verify dashboard loads successfully

---

## 🔧 Development Workflow

### Starting Development Servers

**Option A: Manual (3 terminals)**
```bash
# Terminal 1: Backend
cd backend && php -S localhost:8000

# Terminal 2: Frontend  
cd frontend && npm start

# Terminal 3: Ganache (if not running)
# Launch Ganache application
```

**Option B: Scripted**
Create `start-dev.sh` (Linux/macOS) or `start-dev.bat` (Windows):
```batch
@echo off
start "Backend" cmd /k "cd backend && php -S localhost:8000"
start "Frontend" cmd /k "cd frontend && npm start"
echo "Development servers started!"
echo "Backend: http://localhost:8000"
echo "Frontend: http://localhost:3000"
```

### Common Development Tasks

```bash
# Reset blockchain state
cd contracts && truffle migrate --reset

# Re-run tests
cd backend/tests && php run_all_tests.php

# Clear browser cache
# In Chrome: Ctrl+Shift+R (hard reload)
```

---

## 🆘 Troubleshooting

### Database Issues

**Error**: "Connection failed"
```bash
# Check MySQL service
# Windows: services.msc
# Linux: sudo systemctl status mysql
# macOS: brew services list

# Test connection manually
mysql -u root -p -h localhost certificate_db
```

### Blockchain Issues

**Error**: "Blockchain connection failed"
1. Ensure Ganache is running
2. Verify RPC URL in config matches Ganache
3. Check contract address is correct
4. Test Ganache manually:
```bash
curl -X POST -H "Content-Type: application/json" \
-d '{"jsonrpc":"2.0","method":"eth_blockNumber","params":[],"id":1}' \
http://127.0.0.1:7545
```

### Frontend Issues

**Error**: "API not responding"
1. Check backend server is running (port 8000)
2. Verify API URL in frontend `.env`
3. Check browser console for CORS errors
4. Test API directly: http://localhost:8000/api/certificates/verify

### Permission Issues

**Linux/macOS**: Fix folder permissions
```bash
chmod -R 755 backend/storage/
chown -R www-data:www-data backend/storage/
```

---

## 📚 Next Steps

After successful setup:

1. **Create University Accounts** (as Admin)
2. **Add Students** (as University)  
3. **Issue Certificates** (as University)
4. **Test Verification** (as Public User)
5. **Explore Features** (QR codes, PDF download, etc.)

For detailed feature guides, see:
- [Development Guide](DEVELOPMENT.md)
- [API Documentation](API.md)
- [Deployment Guide](DEPLOYMENT.md)

---

**Need help?** Check the [Troubleshooting Guide](TROUBLESHOOTING.md) or create an issue.