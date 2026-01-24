# Setup Guide

## Prerequisites

1. **Node.js** (v16 or higher) and npm
2. **PHP** (7.4 or higher) with extensions:
   - pdo
   - pdo_mysql
   - curl
   - json
3. **Composer** (PHP dependency manager)
4. **MySQL** database server
5. **Ganache** (for local Ethereum blockchain)

## Step-by-Step Setup

### 1. Install Ganache

1. Download Ganache from https://trufflesuite.com/ganache/
2. Install and launch Ganache
3. Create a new workspace or use the default one
4. Note the RPC URL (usually `http://127.0.0.1:7545`)
5. Copy one of the account private keys (you'll need this for the backend config)

### 2. Database Setup

1. Create a MySQL database:
```sql
CREATE DATABASE certificate_db;
```

2. Import the schema:
```bash
mysql -u root -p certificate_db < backend/database/schema.sql
```

Or use phpMyAdmin/MySQL Workbench to import `backend/database/schema.sql`

### 3. Smart Contract Deployment

1. Install Truffle globally:
```bash
npm install -g truffle
```

2. Navigate to contracts directory:
```bash
cd contracts
npm install
```

3. Compile the contract:
```bash
truffle compile
```

4. Deploy to Ganache:
```bash
truffle migrate --network ganache
```

5. **Important**: Copy the deployed contract address from the migration output
   - It will look like: `Contract deployed at: 0x1234...`

### 4. Backend Setup

1. Navigate to backend directory:
```bash
cd backend
```

2. Install PHP dependencies (if using Composer):
```bash
composer install
```

3. Create config file:
```bash
cp config.example.php config.php
```

4. Edit `config.php`:
   - Update database credentials
   - Set blockchain RPC URL (from Ganache)
   - Paste the deployed contract address
   - Paste a private key from Ganache (for university account)

5. Configure your web server:
   - **Apache**: Ensure mod_rewrite is enabled
   - **Nginx**: Configure rewrite rules
   - **PHP Built-in Server** (for testing):
```bash
cd backend
php -S localhost:8000
```

### 5. Frontend Setup

1. Navigate to frontend directory:
```bash
cd frontend
```

2. Install dependencies:
```bash
npm install
```

3. Create `.env` file (optional):
```
REACT_APP_API_URL=http://localhost/backend/api
```

4. Start development server:
```bash
npm start
```

The app will open at `http://localhost:3000`

## Default Login Credentials

After importing the database schema, you can login with:

- **Email**: admin@certificate-system.com
- **Password**: admin123

## Testing the System

1. **Login as Admin**:
   - Add a university
   - View all certificates

2. **Login as University** (create account first):
   - Add students
   - Create certificates
   - View issued certificates

3. **Login as Student** (create account via university):
   - View certificates
   - Share verification links
   - View QR codes

4. **Public Verification**:
   - Go to `/verify` route
   - Enter a certificate ID
   - Verify authenticity

## Troubleshooting

### Blockchain Connection Issues
- Ensure Ganache is running
- Check RPC URL in config.php
- Verify contract address is correct

### Database Connection Issues
- Check MySQL is running
- Verify database credentials in config.php
- Ensure database exists and schema is imported

### API Not Responding
- Check web server configuration
- Verify .htaccess is working (Apache)
- Check CORS settings if accessing from different port

### Frontend Build Issues
- Clear node_modules and reinstall: `rm -rf node_modules && npm install`
- Check Node.js version compatibility

## Production Deployment Notes

1. Change JWT secret in `backend/config.php`
2. Use environment variables for sensitive data
3. Set up proper SSL certificates
4. Configure production blockchain network
5. Set up proper database backups
6. Enable error logging
7. Configure CORS properly for production domain

