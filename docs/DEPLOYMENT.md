# Production Deployment Guide

Comprehensive guide for deploying the Certificate Verification System to production environments.

## 🏗️ Production Architecture

### Recommended Infrastructure
```
┌─────────────────────────────────────────────────────────────┐
│                    Load Balancer                     │
│                  (Nginx/HAProxy)                   │
└─────────────────────┬───────────────────────────────────┘
                      │
        ┌─────────────┴─────────────┐
        │                           │
┌───────▼─────┐           ┌───────▼─────┐
│  Web Server   │           │  Web Server   │
│ (Nginx/Apache)│           │ (Nginx/Apache)│
│  Frontend    │           │   Backend     │
└───────┬─────┘           └───────┬─────┘
        │                           │
        └─────────────┬─────────────┘
                      │
        ┌─────────────▼─────────────┐
        │   Database Cluster        │
        │   (MySQL/MariaDB)       │
        │   └─ Primary/Replica     │
        └─────────────┬─────────────┘
                      │
        ┌─────────────▼─────────────┐
        │  Blockchain Network       │
        │  (Ethereum/Polygon)     │
        │  └─ Infura/Alchemy     │
        └───────────────────────────┘
```

### Production Environment Stack
| Component | Recommended Technology | Reason |
|-----------|---------------------|---------|
| **Web Server** | Nginx + PHP-FPM | Performance & scalability |
| **Database** | MySQL 8.0 with Replication | Reliability & backup |
| **Blockchain** | Ethereum Mainnet/Testnet | Real-world verification |
| **CDN** | CloudFlare | Global distribution |
| **SSL** | Let's Encrypt | Free, automated certificates |
| **Monitoring** | Prometheus + Grafana | Performance tracking |

---

## 🚀 Pre-Deployment Checklist

### 🔐 Security Checklist
- [ ] **SSL Certificate** installed and configured
- [ ] **Firewall rules** configured (ports 80, 443, 22)
- [ ] **Database credentials** changed from defaults
- [ ] **Blockchain private keys** stored securely
- [ ] **Environment variables** used for sensitive data
- [ ] **Error reporting** disabled in production
- [ ] **File permissions** properly set
- [ ] **Backup strategy** implemented
- [ ] **Logging system** configured

### 📊 Performance Checklist
- [ ] **Database indexing** optimized
- [ ] **PHP OPcache** enabled
- [ ] **Gzip compression** enabled
- [ ] **Browser caching** configured
- [ ] **CDN** for static assets
- [ ] **Load testing** completed
- [ ] **Monitoring tools** installed

### 🔄 Redundancy Checklist
- [ ] **Database replication** configured
- [ ] **Load balancer** set up
- [ ] **Backup procedures** tested
- [ ] **Disaster recovery** plan documented
- [ ] **Health checks** implemented
- [ ] **Alert system** configured

---

## 🛠️ Server Setup

### 1. Web Server Configuration (Nginx)
```nginx
# /etc/nginx/sites-available/certificate-system
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;

    # Frontend
    location / {
        root /var/www/certificate-system/frontend/build;
        try_files $uri $uri/ /index.html;
        
        # Cache static assets
        location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
        }
    }

    # Backend API
    location /api {
        root /var/www/certificate-system/backend;
        index index.php;
        
        try_files $uri $uri/ /api/index.php?$query_string;
        
        # PHP-FPM Configuration
        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
            
            # Security headers
            add_header X-Frame-Options "SAMEORIGIN" always;
            add_header X-Content-Type-Options "nosniff" always;
            add_header X-XSS-Protection "1; mode=block" always;
        }
    }

    # Security
    location ~ /\. {
        deny all;
    }
    
    location ~ /(composer|\.env) {
        deny all;
    }
}
```

### 2. Apache Alternative
```apache
# /etc/apache2/sites-available/certificate-system.conf
<VirtualHost *:80>
    ServerName your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/certificate-system

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem

    # Frontend
    <Directory /var/www/certificate-system/frontend/build>
        DirectoryIndex index.html
        AllowOverride All
        Require all granted
    </Directory>

    # Backend API
    <Directory /var/www/certificate-system/backend/api>
        DirectoryIndex index.php
        AllowOverride All
        Require all granted
        
        # PHP handling
        <FilesMatch \.php$>
            SetHandler application/x-httpd-php
        </FilesMatch>
    </Directory>

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
```

### 3. PHP-FPM Configuration
```ini
# /etc/php/8.0/fpm/pool.d/www.conf
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.0-fpm.sock
listen.owner = www-data
listen.group = www-data

# Performance
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35

# Security
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen
php_admin_flag[expose_php] = off
```

---

## 🗄️ Database Setup

### 1. MySQL Production Configuration
```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
# Performance
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 1
query_cache_size = 64M
query_cache_type = 1

# Security
bind-address = 127.0.0.1
skip-show-database
local-infile = 0

# Logging
slow_query_log = /var/log/mysql/slow.log
long_query_time = 2
log_queries_not_using_indexes = 1

# Replication (Master)
server-id = 1
log-bin = mysql-bin
binlog-format = ROW
```

### 2. Replica Configuration
```ini
# Replica server
[mysqld]
server-id = 2
relay-log = relay-bin
read-only = 1

# Connection details
master-host = master-db.your-domain.com
master-user = replica_user
master-password = secure_password
```

### 3. Database Deployment Script
```bash
#!/bin/bash
# deploy_database.sh

DB_NAME="certificate_production"
DB_USER="certi_user"
DB_PASS="$(openssl rand -base64 32)"

echo "Creating production database..."

# Create database and user
mysql -u root -p << EOF
CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

# Import schema
mysql -u $DB_USER -p$DB_PASS $DB_NAME < backend/database/schema.sql

echo "Database setup complete!"
echo "Save these credentials securely:"
echo "Database: $DB_NAME"
echo "User: $DB_USER"
echo "Password: [SECURE]"
```

---

## ⛓️ Blockchain Production Setup

### 1. Ethereum Mainnet Configuration
```php
// backend/config.php
'blockchain' => [
    'mainnet' => [
        'rpc_url' => 'https://mainnet.infura.io/v3/YOUR_INFURA_KEY',
        'contract_address' => '0xYourDeployedContractAddress',
        'private_key' => $_ENV['BLOCKCHAIN_PRIVATE_KEY'],
        'gas_limit' => 300000,
        'gas_price_gwei' => 20
    ],
    'testnet' => [
        'rpc_url' => 'https://sepolia.infura.io/v3/YOUR_INFURA_KEY',
        'contract_address' => '0xYourTestnetContractAddress',
        'private_key' => $_ENV['BLOCKCHAIN_PRIVATE_KEY'],
        'gas_limit' => 200000,
        'gas_price_gwei' => 10
    ]
]
```

### 2. Smart Contract Deployment to Mainnet
```javascript
// contracts/migrations/2_deploy_mainnet.js
const CertificateRegistry = artifacts.require("CertificateRegistry");
const HDWalletProvider = require("@truffle/hdwallet-provider");

const provider = new HDWalletProvider(
    process.env.MNEMONIC,
    "https://mainnet.infura.io/v3/" + process.env.INFURA_KEY
);

module.exports = function (deployer) {
    deployer.deploy(CertificateRegistry, { 
        gas: 3000000,
        gasPrice: 20000000000 // 20 gwei
    });
};
```

```bash
# Deploy to mainnet
cd contracts
truffle migrate --network mainnet --reset
```

### 3. Environment-Specific Configuration
```php
// backend/src/Blockchain.php
class Blockchain {
    private $network;
    
    public function __construct() {
        $this->network = $_ENV['BLOCKCHAIN_NETWORK'] ?? 'testnet';
        $config = require __DIR__ . '/../config.php';
        $this->setupNetwork($config['blockchain'][$this->network]);
    }
    
    private function setupNetwork(array $networkConfig) {
        $this->web3 = new Web3($networkConfig['rpc_url']);
        $this->contract = new Contract($this->web3->provider, $this->abi);
        $this->contract->at($networkConfig['contract_address']);
        $this->gasLimit = $networkConfig['gas_limit'];
    }
}
```

---

## 🚀 Application Deployment

### 1. Frontend Build Process
```bash
#!/bin/bash
# deploy_frontend.sh

echo "Building frontend for production..."

cd frontend

# Install dependencies
npm ci --only=production

# Build with environment variables
REACT_APP_API_URL=https://your-domain.com/api \
REACT_APP_BLOCKCHAIN_NETWORK=mainnet \
npm run build

# Upload to server
rsync -avz build/ user@your-server:/var/www/certificate-system/frontend/build/

echo "Frontend deployment complete!"
```

### 2. Backend Deployment
```bash
#!/bin/bash
# deploy_backend.sh

echo "Deploying backend to production..."

# Install dependencies
cd backend
composer install --no-dev --optimize-autoloader

# Set production environment
export NODE_ENV=production

# Copy files (excluding .env, vendor)
rsync -avz --exclude='.env' --exclude='vendor' \
    src/ api/ templates/ abi/ storage/ \
    user@your-server:/var/www/certificate-system/backend/

# Install vendor on server
ssh user@your-server "cd /var/www/certificate-system/backend && composer install --no-dev"

# Set permissions
ssh user@your-server "chown -R www-data:www-data /var/www/certificate-system/backend/storage"
ssh user@your-server "chmod -R 755 /var/www/certificate-system/backend/storage"

echo "Backend deployment complete!"
```

### 3. Zero-Downtime Deployment
```bash
#!/bin/bash
# zero_downtime_deploy.sh

CURRENT_DIR="/var/www/certificate-system"
BACKUP_DIR="/var/www/backups/certificate-system-$(date +%Y%m%d-%H%M%S)"
NEW_DIR="/var/www/certificate-system-new"

# Create backup
sudo cp -r $CURRENT_DIR $BACKUP_DIR

# Deploy to new directory
sudo cp -r $CURRENT_DIR $NEW_DIR
sudo rsync -avz build/ user@your-server:$NEW_DIR/frontend/
sudo rsync -avz backend/ user@your-server:$NEW_DIR/backend/

# Switch to new version atomically
sudo mv $CURRENT_DIR $NEW_DIR/old
sudo mv $NEW_DIR $CURRENT_DIR
sudo rm -rf $NEW_DIR/old

# Restart services
sudo systemctl reload nginx
sudo systemctl reload php8.0-fpm

echo "Zero-downtime deployment complete!"
```

---

## 🔒 Security Hardening

### 1. SSL/TLS Configuration
```bash
#!/bin/bash
# setup_ssl.sh

DOMAIN="your-domain.com"

# Install Certbot
sudo apt update
sudo apt install -y certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN

# Setup auto-renewal
echo "0 12 * * * /usr/bin/certbot renew --quiet" | sudo crontab -

echo "SSL setup complete!"
```

### 2. Firewall Configuration
```bash
#!/bin/bash
# setup_firewall.sh

# Reset firewall
sudo ufw --force reset

# Allow SSH
sudo ufw allow 22/tcp comment 'SSH'

# Allow web traffic
sudo ufw allow 80/tcp comment 'HTTP'
sudo ufw allow 443/tcp comment 'HTTPS'

# Enable firewall
sudo ufw --force enable

echo "Firewall configured:"
sudo ufw status verbose
```

### 3. File System Security
```bash
#!/bin/bash
# secure_files.sh

WEB_ROOT="/var/www/certificate-system"

# Set ownership
sudo chown -R www-data:www-data $WEB_ROOT

# Set permissions
sudo find $WEB_ROOT -type f -exec chmod 644 {} \;
sudo find $WEB_ROOT -type d -exec chmod 755 {} \;

# Sensitive files
sudo chmod 600 $WEB_ROOT/backend/config.php
sudo chmod 700 $WEB_ROOT/backend/storage

# Remove version info
sudo rm -rf $WEB_ROOT/backend/.git
sudo rm -rf $WEB_ROOT/frontend/.git

echo "File security configured!"
```

---

## 📊 Monitoring & Logging

### 1. Application Monitoring (Prometheus + Grafana)
```yaml
# monitoring/docker-compose.yml
version: '3.8'

services:
  prometheus:
    image: prom/prometheus:latest
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'

  grafana:
    image: grafana/grafana:latest
    ports:
      - "3001:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin123
    volumes:
      - grafana-storage:/var/lib/grafana
```

### 2. Log Management
```php
// backend/src/Logger.php
class Logger {
    private static function log(string $level, string $message, array $context = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'cli'
        ];

        file_put_contents(
            '/var/log/certificate-system/app.log',
            json_encode($logEntry) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    public static function info(string $message, array $context = []) {
        self::log('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []) {
        self::log('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []) {
        self::log('WARNING', $message, $context);
    }
}
```

### 3. Health Check Endpoint
```php
// backend/api/health.php
<?php
header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'version' => '1.0.0',
    'checks' => []
];

// Database check
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT 1");
    $health['checks']['database'] = 'healthy';
} catch (Exception $e) {
    $health['checks']['database'] = 'unhealthy: ' . $e->getMessage();
    $health['status'] = 'unhealthy';
}

// Blockchain check
try {
    $blockchain = new Blockchain();
    $blockNumber = $blockchain->getCurrentBlock();
    $health['checks']['blockchain'] = 'healthy (block: ' . $blockNumber . ')';
} catch (Exception $e) {
    $health['checks']['blockchain'] = 'unhealthy: ' . $e->getMessage();
    $health['status'] = 'unhealthy';
}

echo json_encode($health);
```

---

## 🔧 Performance Optimization

### 1. Caching Strategy
```php
// backend/src/Cache.php
class Cache {
    private static $redis;

    public static function init() {
        self::$redis = new Redis();
        self::$redis->connect('127.0.0.1', 6379);
    }

    public static function get(string $key) {
        return self::$redis->get($key);
    }

    public static function set(string $key, $value, int $ttl = 3600) {
        return self::$redis->setex($key, $ttl, $value);
    }

    public static function delete(string $key) {
        return self::$redis->del($key);
    }
}

// Usage in CertificateService
public function verifyCertificate($certificateId) {
    $cacheKey = "cert_verify_$certificateId";
    $cached = Cache::get($cacheKey);
    
    if ($cached !== false) {
        return json_decode($cached, true);
    }

    $result = $this->blockchain->verifyCertificate($certificateId);
    Cache::set($cacheKey, json_encode($result), 300);
    
    return $result;
}
```

### 2. Database Query Optimization
```sql
-- Optimize certificate lookup
CREATE INDEX idx_certificates_composite ON certificates(certificate_id, university_id, student_id);
CREATE INDEX idx_verification_logs_timestamp ON verification_logs(verified_at DESC);

-- Partition large tables
ALTER TABLE verification_logs PARTITION BY RANGE (YEAR(verified_at)) (
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION pmax VALUES LESS THAN MAXVALUE
);
```

### 3. CDN Configuration (CloudFlare)
```yaml
# cloudflare_config.yaml
rules:
  - name: "Static Assets"
    action:
      cache: true
      cache_ttl: 31536000  # 1 year
    matches:
      - name: "file_extension"
        values: ["css", "js", "png", "jpg", "jpeg", "gif", "svg"]

  - name: "API Responses"
    action:
      cache: true
      cache_ttl: 300  # 5 minutes
    matches:
      - name: "url_pattern"
        values: ["/api/*"]
```

---

## 🔄 Backup & Recovery

### 1. Automated Backup Script
```bash
#!/bin/bash
# backup.sh

BACKUP_DIR="/var/backups/certificate-system"
DATE=$(date +%Y%m%d-%H%M%S)
DB_BACKUP="$BACKUP_DIR/database_$DATE.sql"
FILES_BACKUP="$BACKUP_DIR/files_$DATE.tar.gz"

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u certi_user -p$DB_PASS certificate_production > $DB_BACKUP

# Files backup
tar -czf $FILES_BACKUP /var/www/certificate-system/backend/storage

# Encrypt backups
gpg --symmetric --cipher-algo AES256 --output $DB_BACKUP.gpg $DB_BACKUP
gpg --symmetric --cipher-algo AES256 --output $FILES_BACKUP.gpg $FILES_BACKUP

# Remove unencrypted files
rm $DB_BACKUP $FILES_BACKUP

# Clean old backups (keep 30 days)
find $BACKUP_DIR -name "*.gpg" -mtime +30 -delete

echo "Backup completed: $DATE"
```

### 2. Recovery Procedure
```bash
#!/bin/bash
# restore.sh

BACKUP_FILE=$1
RESTORE_DIR="/tmp/certificate-restore"

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 <backup_file>"
    exit 1
fi

# Create restore directory
mkdir -p $RESTORE_DIR

# Decrypt and extract
gpg --output $RESTORE_DIR/backup.tar.gz --decrypt $BACKUP_FILE
tar -xzf $RESTORE_DIR/backup.tar.gz -C $RESTORE_DIR

# Restore database
mysql -u certi_user -p certificate_production < $RESTORE_DIR/database.sql

# Restore files
cp -r $RESTORE_DIR/storage/* /var/www/certificate-system/backend/storage/
chown -R www-data:www-data /var/www/certificate-system/backend/storage

echo "Restore completed from: $BACKUP_FILE"
```

---

## 🚦 Scaling Strategies

### 1. Horizontal Scaling (Load Balancer)
```nginx
# Load balancer configuration
upstream backend_servers {
    server 10.0.1.10:8000 weight=1 max_fails=3 fail_timeout=30s;
    server 10.0.1.11:8000 weight=1 max_fails=3 fail_timeout=30s;
    server 10.0.1.12:8000 weight=1 max_fails=3 fail_timeout=30s;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;

    location /api {
        proxy_pass http://backend_servers;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 2. Database Scaling (Read Replicas)
```php
// backend/src/Database.php
class Database {
    private static $master;
    private static $replicas = [];

    public static function init() {
        // Master connection (writes)
        self::$master = new PDO(
            "mysql:host=master-db;dbname=certificate_db",
            $username, $password, $options
        );

        // Replica connections (reads)
        $replicas = ['replica1-db', 'replica2-db'];
        foreach ($replicas as $replica) {
            self::$replicas[] = new PDO(
                "mysql:host=$replica;dbname=certificate_db",
                $username, $password, $options
            );
        }
    }

    public static function getConnection($write = false) {
        if ($write) {
            return self::$master;
        }
        
        // Random replica for read operations
        $replicaIndex = array_rand(self::$replicas);
        return self::$replicas[$replicaIndex];
    }
}
```

### 3. Blockchain Cost Optimization
```php
// backend/src/GasOptimizer.php
class GasOptimizer {
    public static function estimateGas(string $function, array $params) {
        $estimates = [
            'issueCertificate' => 150000,
            'verifyCertificate' => 30000,
            'revokeCertificate' => 50000,
        ];

        return $estimates[$function] ?? 100000;
    }

    public static function getOptimalGasPrice() {
        // Dynamic gas price based on network congestion
        $response = file_get_contents('https://ethgasstation.info/api/ethgasAPI.json');
        $data = json_decode($response, true);
        
        return $data['fast'] / 10; // Convert to gwei
    }
}
```

---

## 📈 Performance Monitoring

### 1. Key Metrics
- **Response Time**: API endpoints < 200ms
- **Throughput**: > 100 requests/second
- **Error Rate**: < 1%
- **Uptime**: > 99.9%
- **Blockchain Latency**: < 5 seconds
- **Database Query Time**: < 100ms

### 2. Alert Configuration
```yaml
# alerts.yml
groups:
  - name: certificate-system
    rules:
      - alert: HighErrorRate
        expr: rate(http_requests_total{status=~"5.."}[5m]) > 0.1
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "High error rate detected"

      - alert: SlowDatabaseQueries
        expr: mysql_slow_queries > 10
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Slow database queries detected"

      - alert: BlockchainFailure
        expr: block_success_rate < 0.95
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Blockchain transaction failures detected"
```

---

## 🔄 Continuous Deployment

### GitHub Actions Workflow
```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup Node.js
      uses: actions/setup-node@v2
      with:
        node-version: '16'
        
    - name: Build Frontend
      run: |
        cd frontend
        npm ci
        npm run build
        
    - name: Deploy to Server
      uses: appleboy/ssh-action@v0.1.4
      with:
        host: ${{ secrets.HOST }}
        username: ${{ secrets.USERNAME }}
        key: ${{ secrets.SSH_KEY }}
        script: |
          cd /var/www/certificate-system
          git pull origin main
          cd frontend && npm run build
          sudo systemctl reload nginx
```

---

## 📞 Support & Maintenance

### Maintenance Schedule
- **Weekly**: Security updates, log review
- **Monthly**: Performance analysis, backup testing
- **Quarterly**: Capacity planning, security audit
- **Annually**: Architecture review, cost optimization

### Emergency Procedures
1. **Service Down**: Check server status, restart services
2. **Database Issue**: Switch to replica, investigate master
3. **Blockchain Failure**: Use backup node, verify transactions
4. **Security Incident**: Enable maintenance mode, investigate logs

### Contact Information
- **Technical Lead**: [Email/Phone]
- **Infrastructure Team**: [Email/Phone]
- **Security Team**: [Email/Phone]

---

**For development setup and API reference, see the [Development Guide](DEVELOPMENT.md) and [API Documentation](API.md).**