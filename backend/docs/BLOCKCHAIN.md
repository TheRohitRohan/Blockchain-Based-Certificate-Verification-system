# Blockchain Integration Guide

The Certificate Verification System uses an Ethereum-compatible architecture (defaulting to the Sepolia testnet) via Alchemy's RPC infrastructure to immutably anchor academic certificates.

## What Blockchain is Used?

The backend specifically connects to Ethereum/EVM-compatible networks. The transactions are fired via raw byte requests utilizing the `Web3 PHP` library combined with `kornrunner/ethereum-offline-raw-tx`. It does not rely on local Ethereum nodes, but instead directly queries an Alchemy RPC Endpoint over HTTP.

## Smart Contract Details

The smart contract acts as an immutable registry. It does **not** store entire PDFs; it stores 64-character Keccak256 hashes ensuring data integrity.

- **ABI Location**: `abi/CertificateRegistry.json`

### Functions:
1. `issueCertificate(certificateId, studentName, universityName, courseName, issueDate, certificateHash)`: Locks the certificate into the registry block.
2. `getCertificate(certificateId)`: Returns a struct representing the exact data sent during issuance, including validity and the block timestamp.
3. `verifyCertificate(certificateId, certificateHash)`: Returns a boolean indicating whether the `certificateId` matches the specific cryptographic payload.
4. `revokeCertificate(certificateId)`: Permanently alters the `is_revoked` boolean state of a record to true. Cannot be undone.
5. `admin()`: A read function to retrieve the deployer/owner's address string.

*(Note: There are no scripts or documentation currently living in the PHP codebase related to the initial deployment of the smart contract itself. Currently, the contract must be deployed independently using hardhat, foundry, or remix, and its execution address transplanted manually into the app config.)*

## Environment Configuration

The Integration strictly relies upon the following `.env` parameters parsed by `config.php`:

```dotenv
BLOCKCHAIN_RPC=https://eth-sepolia.g.alchemy.com/v2/YOUR_API_KEY
CONTRACT_ADDRESS=0xYourDeployedAddress
BLOCKCHAIN_PRIVATE_KEY=0xYourRawPrivateKey  # Without it, mock mode executes 
BLOCKCHAIN_DEFAULT_ADDRESS=0xYourDefaultWalletAddress
BLOCKCHAIN_WALLET_ADDRESS=0xYourFallbackWalletAddress
BLOCKCHAIN_GAS_LIMIT=3000000
BLOCKCHAIN_CHAIN_ID=11155111
```

> **Note on `KEY_ENCRYPTION_SECRET`**: `KEY_ENCRYPTION_SECRET` is required by `SignatureService` for encrypting university private keys but is not present in the default `config.php` signing block. It must be added manually to `config.php` under `signing.key_encryption_secret`.

## The Cryptographic Hashing Strategy

A certificate goes through multiple hash layers prior to final EVM anchoring. This pipeline guarantees that tampering with the PDF visually breaks the verification, and altering the embedded DB text breaks the verification.

1. `metadata_hash`: A pure JSON Keccak256 representation of the normalized data (student name, issue date, URL codes).
2. `pdf_hash`: The Keccak256 hash of the generated or uploaded PDF file binary *before* the RSA signature is embedded.
3. `onchain_hash`: `Keccak256(metadata_hash + pdf_hash)`. This is the final 64-character payload string saved into the Smart Contract registry as the `certificateHash`.

### The Signing Relationship
Because the `onchain_hash` is fully decoupled from the final rendered bytes of the signed PDF, the `SignatureService` utilizes this identical payload to do the math. 
The system signs the `onchain_hash`, embeds the math into the existing PDF XMP block, and saves the file. If we signed the PDF binary directly, embedding the signature would change the PDF binary, recursively invalidating it. Using `onchain_hash` solves this gracefully. 

## Onchain Mechanics & Data Flow

### Issuance 
`issueCertificate()` calls `sendRawTransaction` under the hood. The `ext-gmp` extension formats the integers to EVM parameters. Next, `getTransactionCount()` increments the nonce automatically, the contract payload is converted into `getData()` hex encodings, and successfully confirmed against the chain up to `60` seconds of waiting time.

### Verification 
`verifyCertificate()` conducts a free read-query evaluating the ID against the requested `onchain_hash`. The result returns instantly. To prevent spamming Alchemy RPC limits during high volumes, the backend utilizes `Redis` or file caching with a `300s TTL (5 Minutes)` mapping the exact `blockchain_verify:{ID}:{HASH}`.

### Revocation
`revokeCertificate()` executes a state-changing Ethereum transaction mirroring issuance. Upon confirmation, the backend manually purges the Redis keys associated with the original certificate ID because the state has radically shifted.

---

## Mock Mode & Known Issues

`Blockchain.php` utilizes a "Mock Mode" whenever it encounters fatal connection or configuration errors to prevent the entire system from experiencing downtime. 

**Activation Drivers:**
- Network connection fails (Timeout from Alchemy).
- `BLOCKCHAIN_RPC` is empty or incorrectly formatted.
- Server is missing the `ext-gmp` math library necessary for executing PHP cryptography.
- `BLOCKCHAIN_PRIVATE_KEY` is missing/empty.

**Mock Behavior:**
If `mock` activates, the application continues functionally operating local certificates and databases seamlessly. Specifically:
- `tx_hash` is `null` (it does not generate a fake random hash).
- `mock: true` is present in the `Blockchain.php` response so callers can detect it.
- `CertificateService` now exposes `blockchain_mode: 'mock'` in its return value so the API consumer can see it cleanly.

**Diagnostic Status via `getConnectionStatus()`:**
The `Blockchain.php` class provides a `getConnectionStatus()` method which returns diagnostic info about why mock mode was activated:
- `connected`: bool
- `mock_mode`: bool
- `error`: string|null — the specific reason for failure
- `rpc_url`: 'configured' | 'missing'
- `contract_address`: 'configured' | 'missing'

> [!WARNING]
> **Database Indistinguishability Issue**: A known logic failure exists wherein mock transaction failures are entirely omitted or passed over during the DB transaction `INSERT`. A certificate successfully generated under mock mode has `blockchain_tx_hash: null`, but its `onchain_hash` is generated fully accurately, meaning developers cannot inherently trust if the `onchain_hash` exists on Ethereum purely by looking at the DB row. Always review the `tx_hash` specifically.
