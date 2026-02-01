# Development Guide

Comprehensive guide for developers contributing to or extending the Certificate Verification System.

## 🏗️ Project Architecture

### Directory Structure
```
certi/
├── backend/                    # PHP REST API
│   ├── src/                  # Core application logic
│   │   ├── Auth.php          # Authentication & JWT handling
│   │   ├── Blockchain.php     # Blockchain integration
│   │   ├── CertificateService.php # Certificate business logic
│   │   ├── Database.php       # Database connection & queries
│   │   └── PDFGenerator.php  # PDF certificate generation
│   ├── api/                  # API endpoints
│   │   └── index.php        # Main API router
│   ├── tests/                 # Test suite
│   ├── abi/                   # Smart contract ABI files
│   ├── storage/               # File storage (certificates, QR codes)
│   ├── database/              # SQL schemas
│   ├── templates/             # HTML templates for PDFs
│   └── config.php             # Application configuration
├── frontend/                   # React.js application
│   ├── src/
│   │   ├── components/       # React components
│   │   └── services/        # API service layer
│   └── public/
├── contracts/                  # Solidity smart contracts
│   ├── contracts/            # Smart contract source code
│   ├── migrations/           # Database migrations
│   └── build/               # Compiled contracts
└── docs/                     # Documentation
```

### Component Interaction
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend     │────│     API        │────│   Database     │
│   (React)      │    │   (PHP)        │    │   (MySQL)      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌─────────────────┐
                    │  Blockchain     │
                    │  (Ganache)     │
                    └─────────────────┘
```

---

## 💻 Backend Development

### PHP Standards
- **Version**: PHP 7.4+ (compatible with 8.0+)
- **Style**: PSR-12 coding standards
- **Naming**: PascalCase for classes, camelCase for methods
- **Documentation**: PHPDoc blocks for all public methods

### Class Structure Template
```php
<?php

namespace App;

use PDO;

/**
 * Service for managing certificates
 * 
 * @package App
 */
class CertificateService {
    private Database $db;
    private Blockchain $blockchain;

    /**
     * Constructor
     * 
     * @param Database $db Database instance
     * @param Blockchain $blockchain Blockchain instance
     */
    public function __construct(Database $db, Blockchain $blockchain) {
        $this->db = $db;
        $this->blockchain = $blockchain;
    }

    /**
     * Create a new certificate
     * 
     * @param array $data Certificate data
     * @return array Result with success/error information
     */
    public function createCertificate(array $data): array {
        // Implementation here
    }
}
```

### Database Access Pattern
```php
// Use Database singleton
$db = Database::getInstance()->getConnection();

// Prepared statements for security
$stmt = $db->prepare("SELECT * FROM certificates WHERE student_id = ?");
$stmt->execute([$studentId]);
$results = $stmt->fetchAll();

// Transaction handling
try {
    $db->beginTransaction();
    
    // Multiple operations here
    
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

### API Endpoint Structure
```php
// In api/index.php
case '/certificates/create':
    if ($method === 'POST') {
        $user = requireAuth($token, $auth, ['university', 'admin']);
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $certService->createCertificate($data);
        echo json_encode($result);
    }
    break;
```

### Error Handling Pattern
```php
try {
    // Business logic here
    return [
        'success' => true,
        'data' => $result
    ];
} catch (\Exception $e) {
    return [
        'success' => false,
        'error' => $e->getMessage()
    ];
}
```

---

## 🎨 Frontend Development

### React Standards
- **Version**: React 18.2+
- **Language**: JavaScript ES6+
- **Styling**: CSS Modules or Styled Components
- **State**: React Hooks (useState, useEffect, useContext)

### Component Structure
```jsx
import React, { useState, useEffect } from 'react';
import { certificateAPI } from '../services/api';

/**
 * Certificate Management Component
 * 
 * @param {Object} props Component props
 * @returns {JSX.Element} Rendered component
 */
const CertificateManager = ({ user }) => {
    const [certificates, setCertificates] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        loadCertificates();
    }, []);

    const loadCertificates = async () => {
        setLoading(true);
        try {
            const response = await certificateAPI.getAll();
            setCertificates(response.data.certificates);
        } catch (error) {
            console.error('Failed to load certificates:', error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="certificate-manager">
            {/* Component JSX here */}
        </div>
    );
};

export default CertificateManager;
```

### API Service Pattern
```jsx
// services/api.js
import axios from 'axios';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api';

const api = axios.create({
    baseURL: API_BASE_URL,
    headers: {
        'Content-Type': 'application/json',
    },
});

// Add token to requests
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

export const certificateAPI = {
    create: (data) => api.post('/certificates/create', data),
    verify: (id, hash) => api.post('/certificates/verify', { certificate_id: id, certificate_hash: hash }),
    getAll: () => api.get('/certificates'),
};
```

### State Management Pattern
```jsx
// contexts/AuthContext.js
import React, { createContext, useContext, useReducer } from 'react';

const AuthContext = createContext();

const authReducer = (state, action) => {
    switch (action.type) {
        case 'LOGIN':
            return { ...state, user: action.payload, isAuthenticated: true };
        case 'LOGOUT':
            return { ...state, user: null, isAuthenticated: false };
        default:
            return state;
    }
};

export const AuthProvider = ({ children }) => {
    const [state, dispatch] = useReducer(authReducer, initialState);

    return (
        <AuthContext.Provider value={{ state, dispatch }}>
            {children}
        </AuthContext.Provider>
    );
};

export const useAuth = () => useContext(AuthContext);
```

---

## 🔗 Blockchain Development

### Smart Contract Development
```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

contract CertificateRegistry {
    struct Certificate {
        string certificateId;
        string studentName;
        string universityName;
        string courseName;
        string issueDate;
        string certificateHash;
        bool isValid;
        bool isRevoked;
        address issuedBy;
        uint256 timestamp;
    }

    mapping(string => Certificate) public certificates;
    mapping(address => bool) public authorizedIssuers;
    address public admin;

    event CertificateIssued(
        string indexed certificateId,
        string certificateHash,
        address indexed issuer,
        uint256 timestamp
    );

    modifier onlyAdmin() {
        require(msg.sender == admin, "Only admin can perform this action");
        _;
    }

    modifier onlyAuthorizedIssuer() {
        require(
            authorizedIssuers[msg.sender] || msg.sender == admin,
            "Not authorized to issue certificates"
        );
        _;
    }

    function issueCertificate(
        string memory certificateId,
        string memory studentName,
        string memory universityName,
        string memory courseName,
        string memory issueDate,
        string memory certificateHash
    ) public onlyAuthorizedIssuer {
        require(
            bytes(certificates[certificateId].certificateId).length == 0,
            "Certificate ID already exists"
        );

        certificates[certificateId] = Certificate({
            certificateId: certificateId,
            studentName: studentName,
            universityName: universityName,
            courseName: courseName,
            issueDate: issueDate,
            certificateHash: certificateHash,
            isValid: true,
            isRevoked: false,
            issuedBy: msg.sender,
            timestamp: block.timestamp
        });

        emit CertificateIssued(certificateId, certificateHash, msg.sender, block.timestamp);
    }
}
```

### Testing Smart Contracts
```javascript
// test/CertificateRegistry.test.js
const CertificateRegistry = artifacts.require("CertificateRegistry");

contract("CertificateRegistry", function (accounts) {
    let contract;
    const admin = accounts[0];
    const issuer = accounts[1];

    beforeEach(async function () {
        contract = await CertificateRegistry.new({ from: admin });
        await contract.addAuthorizedIssuer(issuer, { from: admin });
    });

    it("should issue a certificate", async function () {
        const certId = "CERT-001";
        const studentName = "John Doe";
        const universityName = "Tech University";
        const courseName = "Computer Science";
        const issueDate = "2024-12-01";
        const certHash = "0x123...";

        await contract.issueCertificate(
            certId, studentName, universityName, courseName, issueDate, certHash,
            { from: issuer }
        );

        const cert = await contract.getCertificate(certId);
        assert.equal(cert.studentName, studentName);
        assert.equal(cert.universityName, universityName);
    });
});
```

### Deployment Script
```javascript
// migrations/1_initial_migration.js
const CertificateRegistry = artifacts.require("CertificateRegistry");

module.exports = function (deployer) {
    deployer.deploy(CertificateRegistry);
};
```

---

## 🧪 Testing Framework

### Backend Testing
```php
// tests/test_certificate_service.php
require_once __DIR__ . '/../vendor/autoload.php';

use App\CertificateService;
use App\Database;

class CertificateServiceTest {
    private $certService;
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->certService = new CertificateService();
    }

    public function runTests() {
        echo "=== Certificate Service Tests ===\n";
        
        $this->testCertificateCreation();
        $this->testCertificateVerification();
        $this->testCertificateRetrieval();
        
        echo "\n✅ All certificate tests passed!\n";
    }

    private function testCertificateCreation() {
        echo "Testing certificate creation...\n";
        
        $data = [
            'student_id' => 1,
            'university_id' => 1,
            'course_name' => 'Test Course',
            'issue_date' => '2024-12-01'
        ];

        $result = $this->certService->createCertificate($data);
        
        assert($result['success'] === true, 'Certificate creation should succeed');
        assert(isset($result['certificate_id']), 'Should return certificate ID');
        
        echo "  ✅ Certificate creation test passed\n";
    }
}

// Run tests
$test = new CertificateServiceTest();
$test->runTests();
```

### Frontend Testing
```jsx
// components/__tests__/CertificateManager.test.js
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import CertificateManager from '../CertificateManager';

describe('CertificateManager', () => {
    test('renders certificate list', async () => {
        render(<CertificateManager user={{ role: 'university' }} />);
        
        expect(screen.getByText('Certificates')).toBeInTheDocument();
        expect(screen.getByText('Create New Certificate')).toBeInTheDocument();
    });

    test('creates certificate successfully', async () => {
        render(<CertificateManager user={{ role: 'university' }} />);
        
        fireEvent.click(screen.getByText('Create New Certificate'));
        fireEvent.change(screen.getByLabelText('Course Name'), {
            target: { value: 'Test Course' }
        });
        fireEvent.click(screen.getByText('Issue Certificate'));

        // Wait for success message
        const successMessage = await screen.findByText('Certificate issued successfully');
        expect(successMessage).toBeInTheDocument();
    });
});
```

### Integration Testing
```bash
#!/bin/bash
# scripts/run_integration_tests.sh

echo "Running Integration Tests..."

# Test API endpoints
cd backend/tests
php test_api_endpoints.php

# Test blockchain integration
php test_blockchain.php

# Test database operations
php test_database.php

# Test PDF generation
php test_pdf_generation.php

echo "All integration tests completed!"
```

---

## 🔧 Development Workflow

### Setting Up Development Environment
```bash
# 1. Clone repository
git clone <repository-url>
cd certi

# 2. Install dependencies
cd backend && composer install
cd ../frontend && npm install
cd ../contracts && npm install

# 3. Configure environment
cp backend/config.example.php backend/config.php
# Edit configuration files

# 4. Start services
npm run start:dev  # If available
# Or manually:
# Terminal 1: cd backend && php -S localhost:8000
# Terminal 2: cd frontend && npm start
# Terminal 3: ganache (if not running)
```

### Git Workflow
```bash
# Feature branch workflow
git checkout -b feature/new-feature

# Make changes...

# Run tests
cd backend/tests && php run_all_tests.php
cd frontend && npm test

# Commit changes
git add .
git commit -m "feat: add new feature"

# Push and create PR
git push origin feature/new-feature
```

### Code Quality Tools

#### PHP Linting
```bash
# Install PHP CodeSniffer
composer require --dev squizlabs/php_codesniffer

# Run linting
./vendor/bin/phpcs --standard=PSR12 src/
```

#### JavaScript Linting
```bash
# Already included with Create React App
npm run lint
npm run lint:fix
```

#### Security Scanning
```bash
# PHP security check
composer require --dev enlightn/security-checker
./vendor/bin/security-checker security:check

# JavaScript dependency check
npm audit
npm audit fix
```

---

## 📝 Adding New Features

### Step 1: Database Schema
```sql
-- Add new table or columns
CREATE TABLE IF NOT EXISTS new_features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Step 2: Backend Implementation
```php
// Add new service class
class NewFeatureService {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function createFeature(array $data): array {
        $stmt = $this->db->prepare(
            "INSERT INTO new_features (name) VALUES (?)"
        );
        return $stmt->execute([$data['name']]);
    }
}
```

### Step 3: API Endpoint
```php
// Add to api/index.php
case '/features':
    if ($method === 'POST') {
        $user = requireAuth($token, $auth);
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $newFeatureService->createFeature($data);
        echo json_encode($result);
    }
    break;
```

### Step 4: Frontend Component
```jsx
// Create new React component
const FeatureManager = () => {
    const [features, setFeatures] = useState([]);

    return (
        <div className="feature-manager">
            {/* Component implementation */}
        </div>
    );
};

export default FeatureManager;
```

### Step 5: Testing
```php
// Add tests for new feature
class NewFeatureTest {
    public function testFeatureCreation() {
        $service = new NewFeatureService();
        $result = $service->createFeature(['name' => 'Test Feature']);
        assert($result === true);
    }
}
```

---

## 🔄 Continuous Integration

### GitHub Actions Workflow
```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  backend-tests:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v2
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '7.4'
    - name: Install dependencies
      run: composer install
    - name: Run tests
      run: php backend/tests/run_all_tests.php

  frontend-tests:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v2
    - name: Setup Node.js
      uses: actions/setup-node@v2
      with:
        node-version: '16'
    - name: Install dependencies
      run: npm ci
      working-directory: ./frontend
    - name: Run tests
      run: npm test
      working-directory: ./frontend

  contract-tests:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v2
    - name: Setup Node.js
      uses: actions/setup-node@v2
      with:
        node-version: '16'
    - name: Install dependencies
      run: npm ci
      working-directory: ./contracts
    - name: Compile contracts
      run: npm run compile
      working-directory: ./contracts
    - name: Run contract tests
      run: npm test
      working-directory: ./contracts
```

---

## 📊 Performance Optimization

### Database Optimization
```sql
-- Add indexes for performance
CREATE INDEX idx_certificates_student_id ON certificates(student_id);
CREATE INDEX idx_certificates_university_id ON certificates(university_id);
CREATE INDEX idx_verification_logs_certificate_id ON verification_logs(certificate_id);

-- Analyze query performance
EXPLAIN SELECT * FROM certificates WHERE student_id = 1;
```

### Frontend Optimization
```jsx
// Lazy loading for large lists
import { lazy, Suspense } from 'react';

const CertificateList = lazy(() => import('./CertificateList'));

const CertificateManager = () => (
    <Suspense fallback={<div>Loading...</div>}>
        <CertificateList />
    </Suspense>
);

// Memoization for expensive calculations
import { useMemo, useCallback } from 'react';

const expensiveCalculation = useMemo(() => {
    return computeExpensiveValue(data);
}, [data]);

const memoizedCallback = useCallback((id) => {
    handleCertificateClick(id);
}, []);
```

### Blockchain Optimization
```php
// Batch operations where possible
public function batchVerifyCertificates(array $certificates): array {
    $results = [];
    
    foreach ($certificates as $cert) {
        $results[$cert['id']] = $this->verifyCertificate(
            $cert['id'], 
            $cert['hash']
        );
    }
    
    return $results;
}

// Cache blockchain data
private static $contractCache = null;

public function getContract() {
    if (self::$contractCache === null) {
        self::$contractCache = $this->loadContract();
    }
    return self::$contractCache;
}
```

---

## 🐛 Debugging

### Backend Debugging
```php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Custom logging
error_log("Certificate created: " . $certificateId);

// Debug mode
if (defined('DEBUG') && DEBUG) {
    var_dump($data);
}
```

### Frontend Debugging
```jsx
// React DevTools
// Install browser extension for component inspection

// Console logging
console.log('Certificate data:', certificate);
console.error('API error:', error);

// Error boundaries
class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true };
    }

    componentDidCatch(error, info) {
        console.error('Error caught:', error, info);
    }

    render() {
        if (this.state.hasError) {
            return <h1>Something went wrong.</h1>;
        }
        return this.props.children;
    }
}
```

### Blockchain Debugging
```php
// Transaction debugging
public function debugTransaction(string $txHash) {
    $this->web3->eth->getTransaction($txHash, function ($err, $tx) {
        if ($err) {
            echo "Transaction error: " . $err->getMessage() . "\n";
            return;
        }
        echo "Transaction details:\n";
        echo "  Hash: " . $tx->hash . "\n";
        echo "  From: " . $tx->from . "\n";
        echo "  Gas: " . $tx->gas . "\n";
    });
}
```

---

## 📚 Resources & References

### Documentation
- [PHP Documentation](https://www.php.net/docs.php)
- [React Documentation](https://reactjs.org/docs)
- [Solidity Documentation](https://docs.soliditylang.org/)
- [Web3.php Documentation](https://github.com/sc0Vu/web3.php)

### Tools
- [PHPStorm](https://www.jetbrains.com/phpstorm/) - PHP IDE
- [VS Code](https://code.visualstudio.com/) - Multi-language IDE
- [Postman](https://www.postman.com/) - API testing
- [Truffle Suite](https://trufflesuite.com/) - Blockchain development

### Community
- [Stack Overflow](https://stackoverflow.com/questions/tagged/php+react+blockchain)
- [Reddit r/php](https://www.reddit.com/r/PHP/)
- [Reactiflux Discord](https://discord.gg/reactiflux)

---

**For API reference, see the [API Documentation](API.md).**