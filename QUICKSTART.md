# Quick Start Guide

## Prerequisites Checklist
- [ ] Node.js installed (v16+)
- [ ] PHP installed (7.4+)
- [ ] MySQL installed and running
- [ ] Ganache installed and running
- [ ] Composer installed (optional, for PHP dependencies)

## Quick Setup (5 Steps)

### Step 1: Start Ganache
1. Open Ganache application
2. Create a new workspace (or use default)
3. Note the RPC URL: `http://127.0.0.1:7545`
4. Copy one account's private key (you'll need it later)

### Step 2: Setup Database
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE certificate_db;"

# Import schema
mysql -u root -p certificate_db < backend/database/schema.sql
```

### Step 3: Deploy Smart Contract
```bash
cd contracts
npm install
npm install -g truffle
truffle compile
truffle migrate --network ganache
# Copy the contract address from output
```

### Step 4: Configure Backend
```bash
cd backend
cp config.example.php config.php
# Edit config.php with:
# - Database credentials
# - Blockchain RPC URL
# - Contract address (from step 3)
# - Private key (from Ganache)
```

### Step 5: Start Frontend
```bash
cd frontend
npm install
npm start
```

## Test Login
- **URL**: http://localhost:3000
- **Email**: admin@certificate-system.com
- **Password**: admin123

## Common Issues

### "Cannot connect to database"
- Check MySQL is running
- Verify credentials in `backend/config.php`
- Ensure database `certificate_db` exists

### "Blockchain connection failed"
- Ensure Ganache is running
- Check RPC URL in config matches Ganache
- Verify contract address is correct

### "API not responding"
- Check PHP is running
- Verify `.htaccess` is working (Apache)
- Check CORS settings if accessing from different port

### "Frontend can't connect to API"
- Update `REACT_APP_API_URL` in frontend `.env` file
- Or modify `frontend/src/services/api.js` directly

## Next Steps

1. **Create University Account** (as Admin):
   - Login as admin
   - Go to Admin → Universities tab
   - Add a university
   - Create a university user account

2. **Add Students** (as University):
   - Login as university
   - Go to Students tab
   - Add students

3. **Create Certificates** (as University):
   - Go to Create Certificate tab
   - Select student and fill details
   - Generate certificate

4. **Test Verification**:
   - Go to `/verify` route
   - Enter certificate ID
   - Verify authenticity

## Development Tips

- Use browser DevTools to debug API calls
- Check Ganache logs for blockchain transactions
- Monitor MySQL logs for database queries
- Use React DevTools for frontend debugging

