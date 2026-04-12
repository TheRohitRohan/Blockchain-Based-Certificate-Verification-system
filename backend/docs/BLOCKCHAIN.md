# Blockchain Integration Guide

The Certificate Verification System uses the **Ethereum Sepolia Testnet** via **Alchemy's RPC infrastructure** to immutably anchor academic certificates on-chain. All blockchain anchoring is **synchronous** (blocking) during certificate creation, with graceful fallback to mock mode if blockchain is unavailable.

## Network & Infrastructure

- **Network**: Ethereum Sepolia Testnet (ChainID: `11155111`)
- **RPC Provider**: Alchemy (https://eth-sepolia.g.alchemy.com)
- **Library**: `web3-php` + `kornrunner/ethereum-offline-raw-tx` for raw transaction encoding
- **Execution**: Direct HTTP JSON-RPC calls (no local node required)
- **Direct Raw Transactions**: Uses `ext-gmp` PHP extension for cryptographic integer operations

## Smart Contract Details

The smart contract acts as an immutable, tamper-proof registry. It stores **only Keccak256 hashes** (64-char strings), not entire PDFs or document content.

- **Location**: `abi/CertificateRegistry.json`
- **Deployment**: Must be deployed independently using Truffle/Hardhat/Foundry. Address then manually configured in `.env` as `CONTRACT_ADDRESS`.

### Functions:

| Function | Parameters | Returns | Purpose |
|----------|------------|---------|---------|
| `issueCertificate` | certificateId, certificateHash, studentName, universityName, courseName, issueDate | tx_hash, block_number | Lock certificate into immutable registry |
| `verifyCertificate` | certificateId, certificateHash | boolean | Verify certificate exists and hash matches |
| `getCertificate` | certificateId | struct | Retrieve certificate data from chain (readonly) |
| `revokeCertificate` | certificateId | tx_hash | Permanently mark certificate as revoked |
| `admin` | - | address | Retrieve contract deployer address (readonly) |

## Synchronous Blockchain Anchoring

**Certificate creation is BLOCKING**. When `/certificates/create` or `/certificates/upload` is called:

1. Backend generates certificate locally (PDF, metadata, hashes)
2. **Synchronously** calls `Blockchain::issueCertificate()` (blocks up to 60 seconds)
3. Waits for transaction confirmation
4. Stores TX hash in DB if successful
5. Returns response with `blockchain_mode: 'live'` or `'mock'`

**No queue, no retries, no background jobs**. The entire operation completes before the API response is sent.

### Response Format:
```json
{
  "success": true,
  "certificate_id": "CERT-ABC123",
  "blockchain_mode": "live",
  "blockchain_tx_hash": "0x1234...",
  "onchain_hash": "0xABCD...",
  "message": "Certificate created and anchored successfully"
}
```

## Environment Configuration

Configure the following `.env` parameters parsed by `config.php`:

```bash
# Blockchain Network
BLOCKCHAIN_RPC=https://eth-sepolia.g.alchemy.com/v2/YOUR_ALCHEMY_API_KEY
CONTRACT_ADDRESS=0xYourDeployedContractAddress
BLOCKCHAIN_CHAIN_ID=11155111
BLOCKCHAIN_GAS_LIMIT=3000000

# Private Key for Signing Transactions
BLOCKCHAIN_PRIVATE_KEY=0xYourPrivateKeyInHex
BLOCKCHAIN_DEFAULT_ADDRESS=0xYourWalletAddress  # Fallback override
BLOCKCHAIN_WALLET_ADDRESS=0xYourWalletAddress   # Sender address
```

## The Cryptographic Hashing Strategy

Certificates pass through multiple hash layers to ensure both PDF integrity and metadata integrity:

### Hash Layers:

1. **`metadata_hash`** = `Keccak256(JSON metadata string)`
   - Normalized by `MetadataService::buildMetadata()`
   - Contains: student name, course, issue date, university code, schema version
   - Immutable once set

2. **`pdf_hash`** = `Keccak256(PDF binary bytes)`
   - Calculated BEFORE signing
   - Ensures PDF tampering is detected

3. **`onchain_hash`** = `Keccak256(metadata_hash + pdf_hash)`
   - Final 64-char payload anchored to blockchain
   - What gets digitally signed with university's RSA private key
   - Stored in smart contract for verification

### Why This Structure?

Because embedding a signature into the PDF changes its binary content, which would invalidate the PDF hash, we use the **`onchain_hash`** (computed before signing) as the stable payload for both:
- Smart contract anchoring
- RSA signature generation
- Verification later

This decouples cryptographic proof from physical file mutations.

## Onchain Mechanics & Data Flow

### Issuance (Synchronous)
```
/certificates/create {data}
    ↓
CertificateService::createCertificate()
    ├→ Build metadata, calculate metadata_hash
    ├→ Generate PDF, calculate pdf_hash
    ├→ Combine: onchain_hash = keccak256(metadata_hash + pdf_hash)
    ├→ Sign onchain_hash with university RSA key
    ├→ Store in DB
    └→ Call Blockchain::issueCertificate (BLOCKS HERE)
         ├→ Check connection & mock mode
         ├→ Get nonce from blockchain
         ├→ Create raw transaction
         ├→ Sign with BLOCKCHAIN_PRIVATE_KEY
         ├→ Send to RPC endpoint
         ├→ Poll for confirmation (up to 60s)
         └→ Return tx_hash or null (if failed/mock)
    ↓
Response: {success, blockchain_mode, tx_hash}
```

### Verification (Cached, Read-only)
```
/public/verify {certificateId}
    ↓
VerificationEngine::verifyByCertificateId()
    ├→ Lookup certificate in DB
    ├→ Extract onchain_hash
    ├→ Check 5-minute cache key: blockchain_verify:{id}:{hash}
    ├→ If cached HIT: return cached result
    ├→ If cache MISS: Call Blockchain::verifyCertificate() (read-only, no gas)
    │   └→ Query smart contract for {id, hash} match
    ├→ Cache result (300s TTL, only if true)
    └→ Return {valid: true/false, blockchain_valid, ...}
```

**Caching**: To prevent RPC throttling, verification results are cached for **5 minutes**. Only successful verifications (true) are cached; failures bypass cache so they're re-queried on next request.

### Revocation (Synchronous)
```
/certificates/revoke {certificateId}
    ↓
CertificateService::revokeCertificate()
    ├→ Mark certificate status = 'revoked' in DB
    ├→ Call Blockchain::revokeCertificate() (BLOCKS HERE)
    │   ├→ Create & sign transaction
    │   ├→ Send to RPC
    │   └→ Return tx_hash
    ├→ Invalidate blockchain cache: blockchain_verify:{id}:*
    └→ Response: {success, tx_hash}
```

## Mock Mode & Graceful Degradation

If blockchain is unavailable or misconfigured, the system activates **Mock Mode** to prevent total outage. Certificates can still be issued and verified locally.

### Activation Conditions:

Mock mode automatically activates when:
- Alchemy RPC endpoint is unreachable (timeout/500 error)
- `BLOCKCHAIN_RPC` is empty or malformed
- `BLOCKCHAIN_PRIVATE_KEY` is missing
- `ext-gmp` extension unavailable (needed for EVM math)
- Contract address is not configured
- Network connection fails

### Mock Behavior:

| Aspect | Live Mode | Mock Mode |
|--------|-----------|-----------|
| `blockchain_tx_hash` | Real tx hash (0x1234...) | `null` |
| `blockchain_status` | `anchored` | `mock` |
| `blockchain_mode` | `'live'` | `'mock'` |
| Certificate stored? | ✅ Yes | ✅ Yes |
| Locally verifiable? | ✅ Yes | ✅ Yes |
| Cryptographically signed? | ✅ Yes | ✅ Yes |
| On Ethereum? | ✅ Yes | ❌ No |
| Mock hash stored? | N/A | No fake hashes generated |

### Detection:

API consumers can detect mock mode via response:
```json
{
  "success": true,
  "blockchain_mode": "mock",
  "blockchain_tx_hash": null,
  "message": "Certificate created locally (blockchain unavailable)"
}
```

### Diagnostic Status:

`Blockchain::getConnectionStatus()` returns:
```json
{
  "connected": false,
  "mock_mode": true,
  "error": "Connection refused: Alchemy RPC timeout",
  "rpc_url": "configured",
  "contract_address": "configured"
}
```

## Verification During Blockchain Outage

Even if blockchain is down, verification continues:

1. **Signature verification**: RSA-based, all keys stored locally → works offline
2. **Metadata verification**: Database comparison → works offline
3. **Hash verification**: Local Keccak256 calculation → works offline
4. **Blockchain check**: Marked as `blocked_unavailable`, continues anyway

Result: Certificate marked as valid if local checks pass, with note that blockchain couldn't be verified.

## Known Limitations & Issues

1. **No TX Monitoring**: Database columns `blockchain_attempts`, `blockchain_error`,`blockchain_submitted_at` exist but are never updated post-creation. No background job polls failed transactions.

2. **Mock Hash Ambiguity**: Certificates issued in mock mode have `blockchain_tx_hash: null`, but cannot be visually distinguished from live certificates until deployment/network recovery. Review `blockchain_status` field for definitive status.

3. **Database Stale Status**: Once a certificate is created with a blockchaina status, it is NEVER automatically updated. If block confirmation takes longer than expected, the database state may lag behind actual blockchain state. Manual intervention required.

4. **No Async Retry**: Failed transactions are NOT automatically retried. If `issueCertificate()` times out, the certificate is stored with `tx_hash: null` silently.

5. **Gas Estimation**: `BLOCKCHAIN_GAS_LIMIT` is fixed. No dynamic gas price adjustment based on network conditions.

## Testing & Sepolia Testnet

- **Faucets**: Get free Sepolia ETH from https://sepoliafaucet.com
- **Block Explorer**: https://sepolia.etherscan.io
- **Test Interval**: Blocks ~12-15 seconds; transactions confirm in 1-3 blocks
- **Alchemy Free Tier**: Sufficient for development (rate-limited to ~30 req/sec)
