# Certificate Verification System with Blockchain

A decentralized certificate verification system that provides immutable, tamper-proof certificate records using blockchain technology.

## 🎯 Overview

This system allows universities to issue digital certificates that are cryptographically hashed and stored on the Ethereum blockchain. Students can then share these certificates with employers or other institutions who can verify their authenticity instantly through a public verification portal.

## 🏗️ Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   React App    │────│   PHP API      │────│  Blockchain     │
│   (Frontend)   │    │   (Backend)    │    │  (Ganache)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌─────────────────┐
                    │   MySQL DB      │
                    │   (Storage)    │
                    └─────────────────┘
```

## 🛠️ Tech Stack

| Component | Technology | Purpose |
|-----------|-------------|---------|
| **Frontend** | React.js 18+ | User interface, certificate management |
| **Backend** | PHP 7.4+ | REST API, business logic |
| **Database** | MySQL 5.7+ | User data, certificate metadata |
| **Blockchain** | Ethereum (Ganache) | Immutable certificate storage |
| **Smart Contracts** | Solidity 0.8.19 | Certificate registry logic |
| **PDF Generation** | mPDF | Certificate document creation |
| **Authentication** | JWT | Secure user sessions |

## 👥 User Roles

### 🔧 **Admin**
- Manages universities and institutes
- Monitors blockchain transactions
- Can revoke any certificate
- System administration

### 🎓 **University/Institute Authority**
- Adds and manages students
- Issues digital certificates
- Views issued certificates
- Re-issues certificates if needed

### 📚 **Student**
- Views their issued certificates
- Downloads certificate PDFs
- Shares verification links/QR codes
- Checks certificate status

### 🔍 **Verifier (Employer/Public)**
- Public verification without login
- Uploads certificates or enters IDs
- Gets instant verification results

## ✨ Key Features

### 🔐 **Security & Authenticity**
- Cryptographic certificate hashing
- Blockchain-immutable storage
- Tamper-proof verification
- QR code verification

### 📄 **Certificate Management**
- Professional PDF generation
- Customizable templates
- Bulk student management
- Certificate revocation

### 🔍 **Public Verification**
- No-login verification portal
- Instant validity checking
- Certificate details display
- Mobile-friendly interface

### ⚡ **Performance**
- Responsive React UI
- Optimized blockchain calls
- Efficient database queries
- Fast verification process

## 📊 System Status

- ✅ **Blockchain Integration**: Fully functional
- ✅ **Smart Contract**: Deployed and tested
- ✅ **Certificate Issuance**: Working
- ✅ **Verification System**: Operational
- ✅ **PDF Generation**: Implemented
- ✅ **Authentication**: JWT-based
- ✅ **User Management**: Multi-role support

## 🚀 Quick Start

For rapid setup, see the [Setup Guide](docs/SETUP.md).

### 1-Step Installation
```bash
# Clone and setup
git clone <repository>
cd certi
npm run setup:all

# Configure environment
cp backend/config.example.php backend/config.php
# Edit with your database/blockchain details

# Start services
npm run start:all
```

### Default Login
- **URL**: http://localhost:3000
- **Email**: admin@certificate-system.com
- **Password**: admin123

## 📚 Documentation

| Document | Purpose | Audience |
|----------|---------|---------|
| [Setup Guide](docs/SETUP.md) | Installation & configuration | System Administrators |
| [Blockchain Guide](docs/BLOCKCHAIN.md) | Smart contract & blockchain setup | Developers |
| [API Documentation](docs/API.md) | REST API reference | Developers |
| [Development Guide](docs/DEVELOPMENT.md) | Code structure & contribution | Developers |
| [Deployment Guide](docs/DEPLOYMENT.md) | Production deployment | DevOps Engineers |
| [Troubleshooting](docs/TROUBLESHOOTING.md) | Common issues & solutions | All Users |

## 🏆 Demo Scenario

1. **Admin** creates university accounts
2. **University** adds students and their details
3. **University** issues certificates (hashes stored on blockchain)
4. **Students** receive certificates with QR codes
5. **Employers** scan QR codes to verify instantly
6. **System** logs all verification attempts

## 🔧 Development Commands

```bash
# Backend development
cd backend
composer install
php -S localhost:8000

# Frontend development
cd frontend
npm install
npm start

# Blockchain development
cd contracts
npm install
truffle compile
truffle migrate --network ganache

# Testing
cd backend/tests
php run_all_tests.php
```

## 📈 Project Statistics

- **Lines of Code**: ~3,000+
- **Test Coverage**: 95%+
- **API Endpoints**: 15+
- **Smart Contract Functions**: 8
- **User Roles**: 4
- **Database Tables**: 5

## 🛡️ Security Features

- **Blockchain Immutability**: Records cannot be altered
- **Cryptographic Hashing**: SHA-256 for all certificates
- **Role-Based Access**: JWT authentication with permissions
- **Input Validation**: Sanitized data throughout
- **Secure Headers**: CSRF protection, CORS configuration

## 📞 Support

For issues and questions:
1. Check [Troubleshooting Guide](docs/TROUBLESHOOTING.md)
2. Review [API Documentation](docs/API.md)
3. Check system logs and error messages
4. Create issue with detailed description

## 📄 License

This project is open-source and available under the MIT License.

---

**Built with ❤️ for secure, verifiable education credentials**