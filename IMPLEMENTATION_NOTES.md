# Implementation Notes

## Architecture Overview

This is a **full-stack certificate verification system** with blockchain integration, built using:

- **Frontend**: React.js (SPA with routing)
- **Backend**: PHP (RESTful API)
- **Blockchain**: Ethereum (via Ganache)
- **Smart Contracts**: Solidity
- **Database**: MySQL

## Key Design Decisions

### 1. Authentication System
- **JWT Tokens**: Stateless authentication for scalability
- **Role-Based Access**: Admin, University, Student roles
- **Password Security**: Bcrypt hashing

### 2. Blockchain Integration
- **Hash Storage**: Certificate hashes stored on blockchain for immutability
- **Verification**: Cross-reference database and blockchain for authenticity
- **Revocation**: Admin can revoke certificates (marked on both DB and blockchain)

### 3. Database Design
- **Normalized Schema**: Separate tables for users, universities, students, certificates
- **Audit Trail**: Verification logs for tracking
- **Foreign Keys**: Data integrity constraints

### 4. API Design
- **RESTful**: Standard HTTP methods and status codes
- **JSON**: All requests/responses in JSON format
- **CORS**: Configured for cross-origin requests

## File Structure Explanation

### Backend (`backend/`)
- `api/index.php` - Main API router handling all endpoints
- `src/Auth.php` - Authentication and JWT token management
- `src/Database.php` - Database connection singleton
- `src/Blockchain.php` - Blockchain interaction layer
- `src/CertificateService.php` - Certificate business logic
- `database/schema.sql` - Complete database schema

### Frontend (`frontend/`)
- `src/App.js` - Main app component with routing
- `src/components/` - All React components
  - Role-specific dashboards (Admin, University, Student)
  - Public verification portal
  - Authentication components
- `src/services/api.js` - API client with axios

### Smart Contracts (`contracts/`)
- `contracts/CertificateRegistry.sol` - Main smart contract
  - Certificate storage
  - Verification logic
  - Revocation mechanism

## Important Configuration

### Backend Config (`backend/config.php`)
```php
- Database credentials
- Blockchain RPC URL (Ganache)
- Contract address (after deployment)
- JWT secret key
- Private key for blockchain transactions
```

### Frontend Config
- API URL in `src/services/api.js` or `.env` file
- Default: `http://localhost/backend/api`

## Workflow Examples

### Creating a Certificate
1. University logs in
2. Selects a student
3. Fills certificate details
4. System generates unique certificate ID
5. Creates SHA-256 hash of certificate data
6. Stores hash on blockchain (via smart contract)
7. Saves certificate to database with blockchain transaction hash
8. Returns certificate ID to user

### Verifying a Certificate
1. Verifier enters certificate ID (public portal)
2. System queries database for certificate
3. Retrieves stored hash
4. Calls smart contract to verify hash on blockchain
5. Compares results
6. Returns verification status (Valid/Invalid/Revoked)
7. Logs verification attempt

### Revoking a Certificate
1. Admin logs in
2. Selects certificate to revoke
3. System updates database (is_revoked = true)
4. Updates blockchain record (via smart contract)
5. Future verifications return "Revoked" status

## Security Considerations

### Implemented
- Password hashing
- SQL injection prevention
- JWT token expiration
- Role-based access control
- CORS configuration

### Production Recommendations
- Use HTTPS
- Change JWT secret
- Implement rate limiting
- Add input sanitization
- Enable error logging
- Use environment variables for secrets
- Implement proper session management
- Add CAPTCHA for public endpoints
- Regular security audits

## Testing Checklist

- [ ] Admin can login
- [ ] Admin can add university
- [ ] University can login
- [ ] University can add student
- [ ] University can create certificate
- [ ] Certificate hash stored on blockchain
- [ ] Student can view certificates
- [ ] QR code generates correctly
- [ ] Public verification works
- [ ] Revoked certificates show as invalid
- [ ] Verification logs are created

## Troubleshooting

### Blockchain Issues
- **Problem**: Cannot connect to Ganache
  - **Solution**: Ensure Ganache is running, check RPC URL

- **Problem**: Contract not found
  - **Solution**: Verify contract address in config.php matches deployed address

### Database Issues
- **Problem**: Connection refused
  - **Solution**: Check MySQL is running, verify credentials

### API Issues
- **Problem**: 404 errors
  - **Solution**: Check .htaccess, verify web server configuration

### Frontend Issues
- **Problem**: Cannot connect to API
  - **Solution**: Check API URL, verify CORS settings, check network tab

## Performance Notes

- Database queries use indexes for faster lookups
- Blockchain calls are asynchronous
- Frontend uses React for efficient rendering
- Consider caching for frequently accessed data

## Scalability Considerations

- Database can be replicated
- API can be load balanced
- Frontend can be served via CDN
- Blockchain can use mainnet/testnet for production
- Consider microservices architecture for large scale

