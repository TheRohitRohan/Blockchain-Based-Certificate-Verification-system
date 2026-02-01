# Troubleshooting Guide

Comprehensive troubleshooting guide for common issues in the Certificate Verification System.

## 🔍 Quick Diagnosis

### First Steps Checklist
- [ ] **Check all services are running** (Ganache, PHP, MySQL)
- [ ] **Verify network connectivity** between services
- [ ] **Review recent changes** (code, configuration, environment)
- [ ] **Check system logs** for error messages
- [ ] **Test basic connectivity** (database, blockchain, API)

---

## 🏗️ Blockchain Issues

### ❌ "Blockchain connection failed"

#### **Symptoms**
- Certificate issuance fails
- Verification returns errors
- Block number returns 0

#### **Common Causes**
1. **Ganache not running**
2. **Incorrect RPC URL**
3. **Wrong contract address**
4. **Network connectivity issues**

#### **Solutions**

**Check Ganache Status**
```bash
# Test Ganache directly
curl -X POST -H "Content-Type: application/json" \
-d '{"jsonrpc":"2.0","method":"eth_blockNumber","params":[],"id":1}' \
http://127.0.0.1:7545

# Expected: {"jsonrpc":"2.0","result":"0x1","id":1}
```

**Verify Configuration**
```php
// Check backend/config.php
'blockchain' => [
    'rpc_url' => 'http://127.0.0.1:7545',  // Must match Ganache
    'contract_address' => '0x...',           // Must be deployed address
    'private_key' => '0x...',                  // Must be valid key
]
```

**Reset Ganache Connection**
```bash
# Restart Ganache
# 1. Stop current instance
# 2. Clear workspace (if needed)
# 3. Start fresh workspace
# 4. Update contract address if redeployed
```

### ❌ "Transaction failed: Not authorized to issue certificates"

#### **Causes**
- Account not added as authorized issuer
- Using wrong private key
- Smart contract permissions issue

#### **Solutions**

**Check Authorization**
```php
// Create test script to check authorization
$blockchain = new Blockchain();
$config = require __DIR__ . '/../config.php';
$account = $config['blockchain']['default_address'];

// This would need to be called by admin
// In the meantime, use admin account for testing
```

**Use Admin Account**
1. Find admin address from contract:
```php
$admin = $blockchain->getAdmin();
echo "Contract admin: $admin\n";
```

2. Update config with admin account for testing
3. Add your account as authorized issuer (via admin functions)

### ❌ "Please make sure you have put all function params and callback"

#### **Causes**
- Web3.php version incompatibility
- Incorrect function signature
- Missing callback parameters

#### **Solutions**

**Check Web3.php Version**
```bash
cd backend
composer show web3p/web3.php
# Should be version 0.3.2 or compatible
```

**Verify Function Call**
```php
// Correct format (parameters flat, callback last)
$this->contract->send(
    'issueCertificate',
    $param1,           // certificateId
    $param2,           // studentName  
    $param3,           // universityName
    $param4,           // courseName
    $param5,           // issueDate
    $param6,           // certificateHash
    [                  // options
        'from' => $fromAddress,
        'gas' => $gasLimit
    ],
    function ($err, $tx) {  // callback
        // handle result
    }
);
```

---

## 🗄️ Database Issues

### ❌ "Database connection failed"

#### **Symptoms**
- Login failures
- Data not saving
- API returning 500 errors

#### **Diagnostics**

**Test MySQL Connection**
```bash
mysql -u root -p -h localhost certificate_db
# If this fails, MySQL is the issue
```

**Check MySQL Service**
```bash
# Linux
sudo systemctl status mysql

# Windows
# Check services.msc for MySQL service

# macOS
brew services list | grep mysql
```

#### **Solutions**

**Fix Credentials**
```php
// Verify config.php matches database
'database' => [
    'host' => 'localhost',      // Try 127.0.0.1
    'dbname' => 'certificate_db', // Must exist
    'username' => 'root',        // Correct user
    'password' => 'password',    // Correct password
    'charset' => 'utf8mb4'
]
```

**Reset Database User**
```sql
-- Reset root password
ALTER USER 'root'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;

-- Create new user
CREATE USER 'certi_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON certificate_db.* TO 'certi_user'@'localhost';
FLUSH PRIVILEGES;
```

### ❌ "SQLSTATE[HY000] [1049] Unknown database"

#### **Causes**
- Database doesn't exist
- Incorrect database name
- Case sensitivity issues

#### **Solutions**

**Create Database**
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS certificate_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**Import Schema**
```bash
mysql -u root -p certificate_db < backend/database/schema.sql
```

### ❌ "SQLSTATE[42000]: Syntax error"

#### **Causes**
- SQL syntax issues
- MySQL version incompatibility
- Missing quotes in queries

#### **Solutions**

**Check SQL Syntax**
```bash
# Validate SQL file
mysql -u root -p certificate_db < backend/database/schema.sql --verbose
```

**Test Individual Queries**
```sql
-- Test in MySQL client first
SELECT * FROM users WHERE email = 'test@example.com';
INSERT INTO universities (name, code) VALUES ('Test University', 'TEST001');
```

---

## 🔗 API Issues

### ❌ "401 Unauthorized"

#### **Symptoms**
- API calls rejected
- Login required errors
- Token not working

#### **Diagnostics**

**Test Login Endpoint**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@certificate-system.com","password":"admin123"}'
```

#### **Solutions**

**Check Token Format**
```javascript
// Token should include "Bearer "
const token = localStorage.getItem('token');
const authHeader = `Bearer ${token}`;

headers: {
    'Authorization': authHeader,
    'Content-Type': 'application/json'
}
```

**Verify Token Validity**
```php
// Check token expiration
$payload = $auth->verifyToken($token);
if ($payload === null || $payload['exp'] < time()) {
    // Token expired
    return ['error' => 'Token expired'];
}
```

### ❌ "403 Forbidden"

#### **Causes**
- Insufficient permissions
- Wrong user role
- Missing role authorization

#### **Solutions**

**Check Role Permissions**
```php
// In api/index.php
case '/certificates/create':
    $user = requireAuth($token, $auth, ['university', 'admin']);
    // Make sure role is allowed
```

**Verify User Role**
```php
// Debug user role
$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$role = $stmt->fetchColumn();
echo "User role: $role\n";
```

### ❌ "404 Not Found"

#### **Causes**
- Incorrect endpoint URL
- Missing route configuration
- .htaccess issues

#### **Solutions**

**Check API Routes**
```php
// Verify route exists in api/index.php
switch ($path) {
    case '/certificates/create':  // Should match
    case '/certificates/verify':  // Should match
}
```

**Test URL Structure**
```bash
# Test base API
curl http://localhost:8000/api/

# Test specific endpoint
curl http://localhost:8000/api/auth/login
```

---

## 🎨 Frontend Issues

### ❌ "Cannot connect to API"

#### **Symptoms**
- Network errors in browser
- Loading states stuck
- CORS errors

#### **Diagnostics**

**Test API Directly**
```bash
curl http://localhost:8000/api/certificates/verify
# Should return JSON response
```

**Check Browser Console**
- Open Developer Tools (F12)
- Look for Network tab errors
- Check Console for JavaScript errors

#### **Solutions**

**Fix CORS Issues**
```php
// In api/index.php or .htaccess
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
```

**Configure API URL**
```javascript
// Check frontend/src/services/api.js
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api';
```

**Create .env file**
```env
# frontend/.env
REACT_APP_API_URL=http://localhost:8000/api
REACT_APP_BASE_URL=http://localhost:3000
```

### ❌ "Module not found: Can't resolve 'react-router-dom'"

#### **Causes**
- Missing dependencies
- Node modules not installed
- Package.json issues

#### **Solutions**

**Install Dependencies**
```bash
cd frontend
rm -rf node_modules package-lock.json
npm install
```

**Check Package Versions**
```json
// frontend/package.json
"dependencies": {
    "react-router-dom": "^6.8.0",
    "axios": "^1.3.0",
    "react": "^18.2.0"
}
```

---

## 🔧 Development Environment Issues

### ❌ "Port already in use"

#### **Diagnostics**
```bash
# Check what's using ports
netstat -tulpn | grep :8000  # Backend
netstat -tulpn | grep :3000  # Frontend
netstat -tulpn | grep :7545  # Ganache
```

#### **Solutions**

**Kill Process**
```bash
# Linux/macOS
sudo lsof -ti:8000 | xargs kill -9
sudo lsof -ti:3000 | xargs kill -9

# Windows
netstat -ano | findstr :8000
taskkill /PID <PID> /F
```

**Change Ports**
```bash
# Use different ports
cd backend
php -S localhost:8001  # Backend

cd frontend  
npm start --port=3001  # Frontend
```

### ❌ "Composer not found"

#### **Solutions**

**Install Composer**
```bash
# Linux/macOS
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Windows
# Download composer-setup.exe from getcomposer.org
```

**Add to PATH**
```bash
# Linux/macOS
export PATH=$PATH:~/.composer/vendor/bin

# Windows
# Add C:\ProgramData\ComposerSetup\bin to System PATH
```

---

## 📊 Performance Issues

### ❌ "Slow page load times"

#### **Diagnostics**
```bash
# Test API response times
time curl -w "@curl-format.txt" http://localhost:8000/api/certificates
```

```bash
# Create curl-format.txt
time_namelookup:  %{time_namelookup}\n
time_connect:     %{time_connect}\n
time_appconnect:  %{time_appconnect}\n
time_pretransfer: %{time_pretransfer}\n
time_starttransfer: %{time_starttransfer}\n
time_total:      %{time_total}\n
```

#### **Solutions**

**Enable PHP OPcache**
```ini
# /etc/php/8.0/mods-available/opcache.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

**Database Indexing**
```sql
-- Add missing indexes
CREATE INDEX idx_certificates_student ON certificates(student_id);
CREATE INDEX idx_certificates_university ON certificates(university_id);
ANALYZE TABLE certificates;
```

**Frontend Optimization**
```javascript
// Use React.memo for expensive components
const CertificateList = React.memo(({ certificates }) => {
    return certificates.map(cert => <CertificateCard key={cert.id} {...cert} />);
});

// Implement code splitting
const AdminDashboard = lazy(() => import('./AdminDashboard'));
```

---

## 🔒 Security Issues

### ❌ "Access denied" or permission errors

#### **Diagnostics**
```bash
# Check file permissions
ls -la backend/storage/
ls -la backend/config.php
```

#### **Solutions**

**Fix File Permissions**
```bash
# Linux/macOS
sudo chown -R www-data:www-data backend/storage/
sudo chmod -R 755 backend/storage/
sudo chmod 600 backend/config.php

# Windows
# Right-click folder → Properties → Security
# Add IIS_IUSRS/WWW group with appropriate permissions
```

### ❌ "JWT token not working"

#### **Diagnostics**
```php
// Test JWT manually
$payload = ['user_id' => 1, 'exp' => time() + 3600];
$token = $auth->generateToken(['id' => 1, 'email' => 'test@test.com', 'role' => 'admin']);
$decoded = $auth->verifyToken($token);
var_dump($decoded);
```

#### **Solutions**

**Check JWT Secret**
```php
// Ensure secret is same and not empty
'jwt' => [
    'secret' => 'your-very-secure-secret-key-change-in-production',
    'algorithm' => 'HS256',
    'expiration' => 86400
]
```

---

## 🛠️ Debugging Tools

### Backend Debugging
```php
// Enable error reporting in development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Custom debug function
function debug($data) {
    if (defined('DEBUG') && DEBUG) {
        error_log("DEBUG: " . print_r($data, true));
    }
}

// SQL query debugging
$stmt = $db->prepare("SELECT * FROM certificates WHERE id = ?");
debug("Executing query with ID: " . $id);
$stmt->execute([$id]);
```

### Frontend Debugging
```javascript
// React DevTools debugging
console.log('Certificate data:', certificate);
console.error('API error:', error);

// Network debugging
fetch('/api/certificates')
    .then(res => {
        console.log('Response status:', res.status);
        console.log('Response headers:', [...res.headers.entries()]);
        return res.json();
    })
    .catch(err => console.error('Network error:', err));
```

### Database Query Debugging
```sql
-- Enable MySQL query log
SET GLOBAL general_log = 'ON';
SET GLOBAL log_output = 'TABLE';

-- Check slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;

-- Explain query performance
EXPLAIN SELECT * FROM certificates WHERE university_id = 1 ORDER BY created_at DESC;
```

---

## 📞 Getting Help

### Before Asking for Help
1. **Check logs** for specific error messages
2. **Try basic connectivity tests**
3. **Verify configuration files**
4. **Review recent changes**
5. **Search existing issues**

### Information to Include
When reporting issues, include:

**System Information:**
- Operating system and version
- PHP version
- Node.js version
- MySQL version
- Browser (if frontend issue)

**Error Details:**
- Full error message
- Steps to reproduce
- Expected vs actual behavior
- Log file excerpts

**Configuration:**
- Relevant config sections (hide passwords/keys)
- Environment variables
- Custom modifications

### Support Channels
- **GitHub Issues**: Create new issue with full details
- **Documentation**: Check relevant guides first
- **Community**: Forums, Discord, Stack Overflow

---

## 🔧 Maintenance Commands

### Regular Maintenance
```bash
#!/bin/bash
# maintenance.sh

echo "Starting system maintenance..."

# Clear temporary files
rm -rf backend/storage/temp/*
rm -rf frontend/build/

# Optimize database
mysql -u root -p certificate_db -e "OPTIMIZE TABLE certificates, users, universities, verification_logs;"

# Check logs for errors
grep -i "error" /var/log/certificate-system/app.log | tail -20

# Restart services if needed
systemctl reload nginx
systemctl reload php8.0-fpm

echo "Maintenance completed!"
```

### Health Check Script
```bash
#!/bin/bash
# health_check.sh

echo "=== System Health Check ==="

# Check services
services=("nginx" "php8.0-fpm" "mysql")
for service in "${services[@]}"; do
    if systemctl is-active --quiet $service; then
        echo "✅ $service: running"
    else
        echo "❌ $service: not running"
    fi
done

# Check ports
ports=("80" "443" "3306" "7545")
for port in "${ports[@]}"; do
    if netstat -tuln | grep -q ":$port "; then
        echo "✅ Port $port: open"
    else
        echo "❌ Port $port: closed"
    fi
done

# Test API
api_response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/health)
if [ "$api_response" = "200" ]; then
    echo "✅ API: healthy"
else
    echo "❌ API: unhealthy (HTTP $api_response)"
fi

echo "=== Health Check Complete ==="
```

---

**For setup instructions, see the [Setup Guide](SETUP.md). For API reference, see [API Documentation](API.md).**