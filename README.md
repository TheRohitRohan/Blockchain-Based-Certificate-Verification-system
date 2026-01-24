# Certificate Verification System with Blockchain

A decentralized certificate verification system using React, PHP, Ganache, and Solidity.

## Tech Stack
- **Frontend**: React.js
- **Backend**: PHP (REST API)
- **Blockchain**: Ganache (Local Ethereum)
- **Smart Contracts**: Solidity
- **Database**: MySQL

## Project Structure
```
certi/
├── frontend/          # React application
├── backend/           # PHP REST API
├── contracts/         # Solidity smart contracts
├── config/            # Configuration files
└── README.md
```

## Setup Instructions

### Prerequisites
- Node.js and npm
- PHP 7.4+ with MySQL extension
- Composer
- Ganache (for local blockchain)
- MySQL Database

### 1. Install Ganache
- Download and install Ganache from https://trufflesuite.com/ganache/
- Start Ganache and note the RPC URL (usually http://127.0.0.1:7545)

### 2. Database Setup
- Create a MySQL database named `certificate_db`
- Import the SQL schema from `backend/database/schema.sql`

### 3. Backend Setup
```bash
cd backend
composer install
cp config.example.php config.php
# Edit config.php with your database and blockchain credentials
```

### 4. Smart Contract Deployment
```bash
cd contracts
npm install -g truffle
truffle compile
truffle migrate --network ganache
# Note the deployed contract address and update backend/config.php
```

### 5. Frontend Setup
```bash
cd frontend
npm install
npm start
```

## User Roles
1. **Admin**: Manages universities, monitors blockchain, revokes certificates
2. **University**: Adds students, generates certificates, stores hashes on blockchain
3. **Student**: Views, downloads, and shares certificates
4. **Verifier**: Public verification without login

## Features
- Secure authentication
- Certificate generation with blockchain hash storage
- QR code generation for certificates
- Public verification portal
- Immutable certificate records
- Certificate revocation capability

