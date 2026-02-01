# Blockchain Documentation

Complete guide to the blockchain component of the Certificate Verification System.

## 🏗️ Architecture Overview

### Smart Contract Structure
The system uses a single smart contract `CertificateRegistry.sol` that manages:

```
┌─────────────────────────────────────────────────────────────┐
│                CertificateRegistry                     │
├─────────────────────────────────────────────────────────────┤
│  📋 Certificate Storage                                │
│  ├─ certificates[string id] => Certificate             │
│  ├─ authorizedIssuers[address] => bool               │
│  └─ admin => address                                 │
│                                                     │
│  🔧 Core Functions                                   │
│  ├─ issueCertificate()                               │
│  ├─ verifyCertificate()                               │
│  ├─ getCertificate()                                │
│  ├─ revokeCertificate()                              │
│  ├─ addAuthorizedIssuer()                            │
│  └─ removeAuthorizedIssuer()                          │
│                                                     │
│  📢 Events                                         │
│  ├─ CertificateIssued                               │
│  ├─ CertificateRevoked                              │
│  └─ CertificateValidated                            │
└─────────────────────────────────────────────────────────────┘
```

### Certificate Data Structure
```solidity
struct Certificate {
    string certificateId;      // Unique identifier
    string studentName;        // Student full name
    string universityName;     // Issuing university
    string courseName;        // Course/degree name
    string issueDate;         // Issue date (YYYY-MM-DD)
    string certificateHash;    // SHA-256 hash of certificate data
    bool isValid;            // Current validity status
    bool isRevoked;         // Revocation status
    address issuedBy;        // Who issued it
    uint256 timestamp;       // Blockchain timestamp
}
```

---

## 🔨 Smart Contract Functions

### 🔐 **Public Functions**

#### `issueCertificate(string certificateId, string studentName, string universityName, string courseName, string issueDate, string certificateHash)`
- **Purpose**: Issues a new certificate on the blockchain
- **Access**: Only authorized issuers
- **Gas**: ~150,000-200,000
- **Returns**: None (emits event)

#### `verifyCertificate(string certificateId, string certificateHash) returns (bool)`
- **Purpose**: Verifies if a certificate is valid
- **Access**: Public
- **Gas**: ~10,000 (read operation)
- **Returns**: `true` if valid, `false` otherwise

#### `getCertificate(string certificateId) returns (string, string, string, string, string, string, bool, bool, address, uint256)`
- **Purpose**: Retrieves complete certificate data
- **Access**: Public
- **Gas**: ~15,000 (read operation)
- **Returns**: All certificate fields

#### `revokeCertificate(string certificateId)`
- **Purpose**: Revokes a certificate (permanent)
- **Access**: Only admin
- **Gas**: ~50,000
- **Returns**: None (emits event)

### 👥 **Admin Functions**

#### `addAuthorizedIssuer(address issuer)`
- **Purpose**: Grants certificate issuance permission
- **Access**: Only admin
- **Gas**: ~40,000

#### `removeAuthorizedIssuer(address issuer)`
- **Purpose**: Revokes certificate issuance permission
- **Access**: Only admin
- **Gas**: ~40,000

#### `admin() returns (address)`
- **Purpose**: Returns contract admin address
- **Access**: Public
- **Gas**: ~2,000

---

## 🚀 Deployment Guide

### Prerequisites
- Ganache running on `http://127.0.0.1:7545`
- Truffle installed (`npm install -g truffle`)
- Node.js dependencies installed

### Step-by-Step Deployment

#### 1. Compile Contract
```bash
cd contracts
truffle compile
```

#### 2. Deploy to Ganache
```bash
truffle migrate --network ganache
```

#### 3. Verify Deployment
After successful deployment, you should see:
```
> Contract address: 0xEB352b98B9CCDab750E7a99E7fb0CE740Baedfcf
> Block number: 2
> Gas used: 1,458,722
> Total cost: 0.004832885288116112 ETH
```

#### 4. Update Backend Configuration
Update `backend/config.php` with the deployed contract address:
```php
'blockchain' => [
    'rpc_url' => 'http://127.0.0.1:7545',
    'contract_address' => '0xEB352b98B9CCDab750E7a99E7fb0CE740Baedfcf',
    'private_key' => 'YOUR_PRIVATE_KEY',
    'default_address' => 'YOUR_ACCOUNT_ADDRESS',
    'gas_limit' => 3000000
]
```

---

## 🔗 Integration with Backend

### Blockchain Class Architecture
```php
class Blockchain {
    private Web3 $web3;           // Web3 connection
    private Contract $contract;      // Smart contract instance
    
    public function getCurrentBlock(): int           // Get blockchain height
    public function getAdmin(): string              // Get contract admin
    public function issueCertificate(array $data)    // Issue certificate
    public function verifyCertificate(string $id, string $hash): bool
    public function getCertificate(string $id): ?array
    public function revokeCertificate(string $id): array
}
```

### Certificate Issuance Flow
```php
// 1. Generate certificate hash
$certificateHash = hash('sha256', json_encode($certificateData));

// 2. Send transaction to blockchain
$this->contract->send(
    'issueCertificate',
    [
        $certificateId,        // Certificate ID
        $studentName,          // Student name
        $universityName,      // University name
        $courseName,          // Course name
        $issueDate,           // Issue date
        $certificateHash      // SHA-256 hash
    ],
    ['from' => $fromAddress, 'gas' => $gasLimit],
    function ($err, $txHash) { /* callback */ }
);

// 3. Wait for confirmation
$this->waitForTransaction($txHash);
```

### Certificate Verification Flow
```php
// 1. Call smart contract
$this->contract->call(
    'verifyCertificate',
    $certificateId,
    $certificateHash,
    function ($err, $result) {
        $isValid = $result[0]; // Boolean result
    }
);

// 2. Returns true if:
//    - Certificate exists
//    - Hash matches
//    - Not revoked
```

---

## 🧪 Testing Blockchain

### Test Suite Structure
```bash
backend/tests/
├── test_connection.php      # Basic blockchain connectivity
├── test_blockchain.php     # Full blockchain integration
├── test_complete.php       # End-to-end testing
└── test_authorization.php  # Authorization testing
```

### Running Tests
```bash
# All blockchain tests
cd backend/tests
php test_blockchain.php

# Individual test categories
php test_connection.php      # Connection only
php test_complete.php        # Full flow
```

### Expected Test Results
```
=== BLOCKCHAIN INTEGRATION TEST ===
✅ PASS: Blockchain Class Instantiation
✅ PASS: Blockchain Connection
✅ PASS: Contract ABI Loading
✅ PASS: Certificate Hash Generation
✅ PASS: Hash Consistency
✅ PASS: Certificate Issuance Simulation
✅ PASS: Certificate Retrieval
✅ PASS: Verification Method Exists
✅ PASS: Configuration Valid

Total Tests: 9, Passed: 9, Success Rate: 100%
```

---

## 🔧 Configuration Options

### Gas Settings
```php
'blockchain' => [
    'gas_limit' => 3000000,  // Maximum gas per transaction
    // Recommended limits:
    // - issueCertificate: 200,000
    // - verifyCertificate: 50,000 (read-only)
    // - revokeCertificate: 100,000
    // - addAuthorizedIssuer: 40,000
]
```

### Network Configuration
```php
// For different blockchain networks:
'development' => [
    'rpc_url' => 'http://127.0.0.1:7545',
    'contract_address' => '0x...'
],
'testnet' => [
    'rpc_url' => 'https://sepolia.infura.io/v3/YOUR_KEY',
    'contract_address' => '0x...'
],
'mainnet' => [
    'rpc_url' => 'https://mainnet.infura.io/v3/YOUR_KEY',
    'contract_address' => '0x...'
]
```

---

## 📊 Monitoring & Debugging

### Blockchain Explorer
For Ganache, use the built-in transaction explorer in the Ganache UI.

### Event Monitoring
```php
// Listen for certificate events
$contract->events->CertificateIssued(function ($err, $event) {
    echo "Certificate issued: " . $event->args['certificateId'];
});

$contract->events->CertificateRevoked(function ($err, $event) {
    echo "Certificate revoked: " . $event->args['certificateId'];
});
```

### Transaction Debugging
```php
private function waitForTransaction(string $txHash, int $maxWaitTime = 30): bool {
    $startTime = time();
    
    while (time() - $startTime < $maxWaitTime) {
        $receipt = null;
        $this->web3->eth->getTransactionReceipt($txHash, function ($err, $result) use (&$receipt) {
            if ($err === null && $result !== null) {
                $receipt = $result;
            }
        });

        if ($receipt && isset($receipt->status)) {
            return $receipt->status === '0x1' || $receipt->status === true;
        }

        sleep(1);
    }

    throw new \Exception('Transaction confirmation timeout');
}
```

---

## 🛡️ Security Considerations

### Smart Contract Security
- ✅ **Access Control**: Only authorized issuers can create certificates
- ✅ **Input Validation**: String lengths and formats validated
- ✅ **Immutable Storage**: Once issued, certificates cannot be altered
- ✅ **Revocation System**: Admin can revoke compromised certificates

### Backend Security
- ✅ **Private Key Protection**: Never expose in frontend
- ✅ **Transaction Validation**: Verify all transaction results
- ✅ **Error Handling**: Graceful failure with logging
- ✅ **Rate Limiting**: Prevent abuse of blockchain calls

### Best Practices
1. **Never commit private keys** to version control
2. **Use environment variables** for sensitive configuration
3. **Monitor gas usage** and optimize where possible
4. **Test on testnet** before mainnet deployment
5. **Regular backups** of database and configuration

---

## 🔄 Upgrade Process

### Contract Migration
When upgrading the smart contract:

1. **Deploy new contract** to new address
2. **Migrate data** if needed (usually not required)
3. **Update configuration** with new contract address
4. **Test thoroughly** before switching
5. **Update frontend** if interface changes

### Version Management
```solidity
// pragma solidity ^0.8.19;
contract CertificateRegistryV2 {
    // New features and improvements
    // Maintain backward compatibility where possible
}
```

---

## 📚 Additional Resources

### Solidity Documentation
- [Official Solidity Docs](https://docs.soliditylang.org/)
- [Security Considerations](https://consensys.github.io/smart-contract-best-practices/)

### Truffle Framework
- [Truffle Documentation](https://trufflesuite.com/docs/truffle/)
- [Ganache Documentation](https://trufflesuite.com/docs/ganache/)

### Web3.php Library
- [Web3.php GitHub](https://github.com/sc0Vu/web3.php)
- [Ethereum JSON-RPC](https://eth.wiki/json-rpc/API)

---

**For troubleshooting common blockchain issues, see the [Troubleshooting Guide](TROUBLESHOOTING.md).**