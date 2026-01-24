# Project Structure

```
certi/
├── backend/                    # PHP Backend API
│   ├── api/                   # API endpoints
│   │   ├── index.php         # Main API router
│   │   └── .htaccess         # Apache rewrite rules
│   ├── src/                   # PHP source files
│   │   ├── Auth.php          # Authentication & JWT
│   │   ├── Database.php      # Database connection
│   │   ├── Blockchain.php    # Blockchain integration
│   │   └── CertificateService.php  # Certificate business logic
│   ├── database/             # Database files
│   │   └── schema.sql        # Database schema
│   ├── config.example.php    # Configuration template
│   ├── composer.json         # PHP dependencies
│   └── .htaccess             # Apache configuration
│
├── frontend/                  # React Frontend
│   ├── public/               # Static files
│   │   └── index.html        # HTML template
│   ├── src/                  # React source files
│   │   ├── components/       # React components
│   │   │   ├── Login.js      # Login page
│   │   │   ├── Dashboard.js  # Main dashboard
│   │   │   ├── AdminDashboard.js    # Admin interface
│   │   │   ├── UniversityDashboard.js  # University interface
│   │   │   ├── StudentDashboard.js    # Student interface
│   │   │   ├── VerificationPortal.js  # Public verification
│   │   │   ├── ProtectedRoute.js      # Route protection
│   │   │   └── Navbar.js     # Navigation component
│   │   ├── services/         # API services
│   │   │   └── api.js        # API client
│   │   ├── App.js            # Main app component
│   │   ├── App.css           # App styles
│   │   ├── index.js          # Entry point
│   │   └── index.css         # Global styles
│   ├── package.json          # Node dependencies
│   └── .env.example         # Environment variables template
│
├── contracts/                # Smart Contracts
│   ├── contracts/            # Solidity contracts
│   │   └── CertificateRegistry.sol  # Main contract
│   ├── migrations/           # Migration scripts
│   │   └── 1_initial_migration.js
│   ├── truffle-config.js     # Truffle configuration
│   └── package.json         # Node dependencies
│
├── README.md                 # Project overview
├── SETUP.md                  # Setup instructions
├── PROJECT_STRUCTURE.md      # This file
└── .gitignore               # Git ignore rules
```

## Key Components

### Backend (PHP)
- **RESTful API** with JWT authentication
- **Database layer** using PDO
- **Blockchain integration** for certificate hashing
- **Role-based access control** (Admin, University, Student)

### Frontend (React)
- **Single Page Application** with React Router
- **Role-specific dashboards** for each user type
- **Public verification portal** (no login required)
- **QR code generation** for certificates

### Smart Contracts (Solidity)
- **CertificateRegistry** contract for immutable storage
- **Certificate verification** via hash comparison
- **Revocation mechanism** for invalid certificates

## Data Flow

1. **Certificate Creation**:
   - University creates certificate → Backend generates hash → Hash stored on blockchain → Certificate saved to database

2. **Certificate Verification**:
   - Verifier enters certificate ID → Backend queries database → Hash verified on blockchain → Result returned

3. **Certificate Revocation**:
   - Admin revokes certificate → Database updated → Blockchain updated → Certificate marked as invalid

