# Blockchain Setup Guide

This guide will walk you through setting up the blockchain component of the Certificate Verification System.

## Prerequisites

1. **Node.js** (v14 or higher) - [Download](https://nodejs.org/)
2. **Ganache** - Local Ethereum blockchain - [Download](https://trufflesuite.com/ganache/)
3. **Truffle** - Smart contract development framework

## Step-by-Step Setup

### Step 1: Install Node.js Dependencies

Navigate to the contracts directory and install dependencies:

```bash
cd contracts
npm install
```

This will install:
- Truffle (smart contract framework)
- @truffle/hdwallet-provider (for connecting to networks)

### Step 2: Install Ganache

1. Download Ganache from https://trufflesuite.com/ganache/
2. Install and launch Ganache
3. Click "Quickstart" to create a new workspace
4. Note the RPC Server URL (usually `http://127.0.0.1:7545`)
5. **IMPORTANT**: Copy the first account's private key (click the key icon) - you'll need this for the backend config

### Step 3: Compile Smart Contracts

In the contracts directory, run:

```bash
truffle compile
```

This will compile the CertificateRegistry.sol contract and create build artifacts.

### Step 4: Deploy Contracts to Ganache

Deploy the contract to your local Ganache network:

```bash
truffle migrate --network ganache
```

After deployment, you'll see output like:
```
CertificateRegistry: 0x1234567890abcdef1234567890abcdef12345678
```

**Copy the contract address** - you'll need it for the backend config.

### Step 5: Update Backend Configuration

1. Copy `backend/config.example.php` to `backend/config.php`
2. Update the blockchain section with:
   - Contract address (from Step 4)
   - Private key (from Step 2 - first account)
   - RPC URL (usually `http://127.0.0.1:7545`)

### Step 6: Test the Integration

We'll create test scripts to verify everything works.

## Troubleshooting

- **Port 7545 already in use**: Change Ganache port or update truffle-config.js
- **Compilation errors**: Check Solidity version matches (0.8.19)
- **Migration fails**: Ensure Ganache is running and has accounts with ETH
