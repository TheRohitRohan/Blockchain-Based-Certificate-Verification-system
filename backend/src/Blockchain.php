<?php

namespace App;

use phpseclib\Math\BigInteger;
use Web3\Web3;
use Web3\Contract;
use Web3\Providers\HttpProvider;

class Blockchain
{
    private $web3;
    private $contract;
    private $config;
    private $isConnected = false;
    private $strictMode = false;
    private $connectionError = null;

    public function __construct($strictMode = false)
    {
        $this->strictMode = $strictMode;
        $this->config = require __DIR__ . '/../config.php';
        $bc = $this->config['blockchain'];

        if (empty($bc['rpc_url']) || empty($bc['contract_address'])) {
            $msg = 'Blockchain configuration missing';
            if ($this->strictMode) {
                throw new \Exception($msg . ' - strict mode enabled, cannot continue');
            }
            $this->connectionError = $msg;
            error_log($msg . ' - running in mock mode');
            return;
        }

        try {
            $blockNumber = null;
            $connError   = null;

            // Create HttpProvider with reasonable timeout for Alchemy
            $httpProvider = new HttpProvider($bc['rpc_url'], 30.0);

            // Create Web3 instance with configured provider
            $this->web3 = new Web3($httpProvider);

            $this->web3->eth->blockNumber(function ($err, $block) use (&$blockNumber, &$connError) {
                if ($err !== null) {
                    $connError = $err->getMessage();
                } else {
                    $blockNumber = $block;
                }
            });

            // With HttpProvider (synchronous), the callback fires during the call above.
            // $blockNumber is populated immediately — no sleep needed.
            $this->isConnected = ($blockNumber !== null && $connError === null);

            if (!$this->isConnected) {
                $this->connectionError = $connError ?? 'unknown error';
                if ($this->strictMode) {
                    throw new \Exception('Blockchain connection failed: ' . $this->connectionError);
                }
            }
        } catch (\Exception $e) {
            if ($this->strictMode) {
                throw $e;
            }
            $this->connectionError = $e->getMessage();
            error_log('Blockchain connection failed: ' . $e->getMessage());
            $this->isConnected = false;
        }

        try {
            // Load ABI
            $abiPath = __DIR__ . '/../abi/CertificateRegistry.json';
            if (!file_exists($abiPath)) {
                $msg = 'Contract ABI file not found';
                if ($this->strictMode) {
                    throw new \Exception($msg . ' at ' . $abiPath);
                }
                $this->connectionError = $msg;
                error_log($msg . ' - running in mock mode');
                return;
            }

            $abi = json_decode(file_get_contents($abiPath), true);
            if (!$abi) {
                $msg = 'Invalid ABI JSON';
                if ($this->strictMode) {
                    throw new \Exception($msg);
                }
                $this->connectionError = $msg;
                error_log($msg . ' - running in mock mode');
                return;
            }

            if ($this->web3) {
                $this->contract = new Contract($this->web3->provider, $abi);
                $this->contract->at($bc['contract_address']);
            }
        } catch (\Exception $e) {
            if ($this->strictMode) {
                throw $e;
            }
            $this->connectionError = $e->getMessage();
            error_log("Blockchain contract setup failed: " . $e->getMessage());
            $this->isConnected = false;
        }
    }

    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * Get detailed connection status for API responses
     */
    public function getConnectionStatus(): array
    {
        return [
            'connected' => $this->isConnected,
            'mock_mode' => !$this->isConnected,
            'error' => $this->connectionError,
            'rpc_url' => !empty($this->config['blockchain']['rpc_url']) ? 'configured' : 'missing',
            'contract_address' => !empty($this->config['blockchain']['contract_address']) ? 'configured' : 'missing',
        ];
    }

    /* =======================
       BASIC CONNECTIVITY TEST
       ======================= */

    public function getCurrentBlock(): int
    {
        // If not connected, return mock block number
        if (!$this->isConnected) {
            return 0;
        }

        $blockNumber = 0;

        $this->web3->eth->blockNumber(function ($err, $block) use (&$blockNumber) {
            if ($err !== null) {
                throw new \Exception('Failed to connect to blockchain: ' . $err->getMessage());
            }

            $blockNumber = $this->normalizeRpcInteger($block);
        });

        return $blockNumber;
    }


    /* =======================
       SMART CONTRACT READS
       ======================= */

    public function getAdmin(): string
    {
        $admin = '';

        $this->contract->call('admin', function ($err, $result) use (&$admin) {
            if ($err !== null) {
                throw new \Exception('Failed to read admin from contract: ' . $err->getMessage());
            }
            if (is_array($result) && isset($result[0])) {
                $admin = $result[0];
            }
        });

        return $admin;
    }

    public function verifyCertificate(string $certificateId, string $certificateHash): bool
    {
        // If not connected, return false (fail-safe) or throw in strict mode
        if (!$this->isConnected || !isset($this->contract)) {
            $msg = "Blockchain verification failed: not connected";
            if ($this->strictMode) {
                throw new \Exception($msg);
            }
            error_log($msg);
            return false;
        }

        $isValid = false;

        $this->contract->call(
            'verifyCertificate',
            $certificateId,
            $certificateHash,
            function ($err, $result) use (&$isValid) {
                if ($err !== null) {
                    throw new \Exception('verifyCertificate call failed: ' . $err->getMessage());
                }
                if (is_array($result) && isset($result[0])) {
                    $isValid = (bool) $result[0];
                }
            }
        );

        return $isValid;
    }

    /* =======================
       SMART CONTRACT WRITES
       ======================= */

    public function generateCertificateHash($certificateData): string
    {
        if (!empty($certificateData['certificate_hash'])) {
            return $certificateData['certificate_hash'];
        }
        return $this->generateKeccak256Hash(json_encode($certificateData));
    }

    /**
     * Generate Keccak256 hash (for new certificates)
     */
    public function generateKeccak256Hash(string $data): string
    {
        return '0x' . \kornrunner\Keccak::hash($data, 256);
    }

    /**
     * Generate combined hash: keccak256(metadata_hash + pdf_hash)
     */
    public function generateCombinedHash(string $metadataHash, string $pdfHash): string
    {
        // Remove 0x prefix if present
        $metadataHash = ltrim($metadataHash, '0x');
        $pdfHash = ltrim($pdfHash, '0x');

        // Combine and hash
        $combined = $metadataHash . $pdfHash;
        return $this->generateKeccak256Hash($combined);
    }

    public function issueCertificate($certificateData): array
    {
        // If not connected to blockchain, use mock mode or throw
        if (!$this->isConnected || !isset($this->contract)) {
            if ($this->strictMode) {
                throw new \Exception('Blockchain not connected - cannot issue certificate');
            }
            return [
                'success' => true,
                'tx_hash' => null,
                'certificate_hash' => $this->generateCertificateHash($certificateData),
                'mock' => true,
                'note' => 'Mock transaction - blockchain not connected: ' . ($this->connectionError ?? 'unknown')
            ];
        }

        try {
            $config = $this->config;
            $privateKey = $config['blockchain']['private_key'];

            if (empty($privateKey)) {
                if ($this->strictMode) {
                    throw new \Exception('Private key not configured - cannot issue certificate');
                }
                return [
                    'success' => true,
                    'tx_hash' => null,
                    'certificate_hash' => $this->generateCertificateHash($certificateData),
                    'mock' => true,
                    'note' => 'Mock transaction - private key not configured'
                ];
            }

            $certificateId = $certificateData['certificate_id'];
            $studentName = $certificateData['student_name'];
            $universityName = $certificateData['university_name'] ?? 'Test University';
            $courseName = $certificateData['course_name'];
            $issueDate = $certificateData['issue_date'];
            $certificateHash = $certificateData['certificate_hash']
                ?? $this->generateKeccak256Hash(json_encode($certificateData));

            $transactionClass = 'kornrunner\\Ethereum\\Transaction';

            // Check if raw transaction library is available
            if (!class_exists($transactionClass)) {
                if ($this->strictMode) {
                    throw new \Exception('ext-gmp not enabled - required for blockchain transactions');
                }
                return [
                    'success' => true,
                    'tx_hash' => null,
                    'certificate_hash' => $certificateHash,
                    'mock' => true,
                    'note' => 'Mock transaction - ext-gmp not enabled. Install it for real blockchain transactions.'
                ];
            }

            $txHash = null;
            $error = null;
            $fromAddress = $this->getAddressFromPrivateKey($privateKey);

            // Get transaction parameters
            // Note: With HttpProvider, callbacks execute synchronously
            $nonce = '0x0';
            $this->web3->eth->getTransactionCount(
                $fromAddress,
                'pending',
                function ($err, $count) use (&$nonce) {
                    if ($err === null && $count !== null) {
                        $nonce = $this->normalizeRpcHexQuantity($count);
                    }
                }
            );

            $gasPrice = '0x4A817C800'; // 20 Gwei default
            $this->web3->eth->gasPrice(function ($err, $price) use (&$gasPrice) {
                if ($err === null && $price !== null) {
                    $gasPrice = $this->normalizeRpcHexQuantity($price);
                }
            });

            // Encode function call — getData() returns hex data directly (no callback)
            $calldata = '0x' . $this->contract->getData(
                'issueCertificate',
                $certificateId,
                $studentName,
                $universityName,
                $courseName,
                $issueDate,
                $certificateHash
            );

            if (empty($calldata) || $calldata === '0x') {
                throw new \Exception('ABI encoding failed: getData returned empty');
            }

            // Create and sign transaction
            $gasLimit = $this->normalizeRpcHexQuantity((int) $config['blockchain']['gas_limit']);
            $to = $config['blockchain']['contract_address'];

            $tx = new $transactionClass(
                $nonce,
                $gasPrice,
                $gasLimit,
                $to,
                '0x0',
                $calldata
            );

            $cleanKey = ltrim($privateKey, '0x');
            $rawTx = '0x' . $tx->getRaw($cleanKey, $config['blockchain']['chain_id']);

            // Send raw transaction to Alchemy
            $this->web3->eth->sendRawTransaction(
                $rawTx,
                function ($err, $hash) use (&$txHash, &$error) {
                    if ($err !== null) {
                        $error = $err->getMessage();
                    } else {
                        $txHash = $hash;
                    }
                }
            );

            if ($error) {
                throw new \Exception('Blockchain transaction failed: ' . $error);
            }

            if (empty($txHash)) {
                throw new \Exception('Transaction hash not returned');
            }

            // Wait for transaction confirmation
            $confirmed = $this->waitForTransaction($txHash);

            return [
                'success' => true,
                'tx_hash' => $txHash,
                'certificate_hash' => $certificateHash,
                'confirmed' => $confirmed,
                'mock' => false
            ];
        } catch (\Exception $e) {
            if ($this->strictMode) {
                throw $e;
            }
            error_log('Blockchain issueCertificate failed: ' . $e->getMessage());
            return [
                'success' => false,
                'tx_hash' => null,
                'error' => $e->getMessage(),
                'mock' => false
            ];
        }
    }

    public function getCertificate(string $certificateId): ?array
    {
        // If not connected, return null
        if (!$this->isConnected || !isset($this->contract)) {
            return null;
        }

        $certificate = null;

        $this->contract->call(
            'getCertificate',
            $certificateId,
            function ($err, $result) use (&$certificate) {
                if ($err !== null) {
                    throw new \Exception('getCertificate call failed: ' . $err->getMessage());
                }

                if (is_array($result) && count($result) >= 9) {
                    $certificate = [
                        'student_name' => $result[0] ?? '',
                        'university_name' => $result[1] ?? '',
                        'course_name' => $result[2] ?? '',
                        'issue_date' => $result[3] ?? '',
                        'certificate_hash' => $result[4] ?? '',
                        'is_valid' => $result[5] ?? false,
                        'is_revoked' => $result[6] ?? false,
                        'issued_by' => $result[7] ?? '',
                        'timestamp' => $result[8] ?? 0
                    ];
                }
            }
        );

        return $certificate;
    }

    /* =======================
       HELPER METHODS
       ======================= */

    private function getAddressFromPrivateKey(string $privateKey): string
    {
        $config = require __DIR__ . '/../config.php';

        // If we have a default address in config, use it
        if (isset($config['blockchain']['default_address']) && !empty($config['blockchain']['default_address'])) {
            return $config['blockchain']['default_address'];
        }

        return $config['blockchain']['wallet_address'];
    }

    private function normalizeRpcInteger($value): int
    {
        if ($value instanceof BigInteger) {
            return (int) $value->toString();
        }

        if (is_string($value) && strpos($value, '0x') === 0) {
            return hexdec($value);
        }

        return (int) $value;
    }

    private function normalizeRpcHexQuantity($value): string
    {
        if ($value instanceof BigInteger) {
            $hex = ltrim($value->toHex(), '0');
            return '0x' . ($hex === '' ? '0' : $hex);
        }

        if (is_string($value)) {
            if (strpos($value, '0x') === 0) {
                $hex = ltrim(substr($value, 2), '0');
                return '0x' . ($hex === '' ? '0' : $hex);
            }

            if (ctype_digit($value)) {
                return '0x' . dechex((int) $value);
            }
        }

        if (is_int($value)) {
            return '0x' . dechex($value);
        }

        return '0x0';
    }

    private function waitForTransaction(string $txHash, int $maxWaitTime = 60): bool
    {
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

            sleep(2);
        }

        // Don't throw — the TX may still be pending. Return false so caller knows it's unconfirmed.
        error_log("Transaction {$txHash} not confirmed within {$maxWaitTime}s — may still be pending");
        return false;
    }

    public function revokeCertificate(string $certificateId): array
    {
        // If not connected, use mock mode
        if (!$this->isConnected || !isset($this->contract)) {
            return [
                'success' => true,
                'tx_hash' => null,
                'mock' => true,
                'note' => 'Mock revocation - blockchain not connected'
            ];
        }

        try {
            $config = $this->config;
            $privateKey = $config['blockchain']['private_key'];

            if (empty($privateKey)) {
                throw new \Exception('Private key not configured for blockchain transactions');
            }

            $transactionClass = 'kornrunner\\Ethereum\\Transaction';

            // Check if raw transaction library is available
            if (!class_exists($transactionClass)) {
                return [
                    'success' => true,
                    'tx_hash' => null,
                    'mock' => true,
                    'note' => 'Mock revocation - ext-gmp not enabled.'
                ];
            }

            $txHash = null;
            $error = null;
            $fromAddress = $this->getAddressFromPrivateKey($privateKey);

            // Get transaction parameters
            $nonce = '0x0';
            $this->web3->eth->getTransactionCount(
                $fromAddress,
                'pending',
                function ($err, $count) use (&$nonce) {
                    if ($err === null && $count !== null) {
                        $nonce = $this->normalizeRpcHexQuantity($count);
                    }
                }
            );

            $gasPrice = '0x4A817C800'; // 20 Gwei default
            $this->web3->eth->gasPrice(function ($err, $price) use (&$gasPrice) {
                if ($err === null && $price !== null) {
                    $gasPrice = $this->normalizeRpcHexQuantity($price);
                }
            });

            // Encode function call — getData() returns hex data directly (no callback)
            $calldata = '0x' . $this->contract->getData(
                'revokeCertificate',
                $certificateId
            );

            if (empty($calldata) || $calldata === '0x') {
                throw new \Exception('ABI encoding failed: getData returned empty');
            }

            // Create and sign transaction
            $gasLimit = $this->normalizeRpcHexQuantity((int) $config['blockchain']['gas_limit']);
            $to = $config['blockchain']['contract_address'];

            $tx = new $transactionClass(
                $nonce,
                $gasPrice,
                $gasLimit,
                $to,
                '0x0',
                $calldata
            );

            $cleanKey = ltrim($privateKey, '0x');
            $rawTx = '0x' . $tx->getRaw($cleanKey, $config['blockchain']['chain_id']);

            // Send raw transaction
            $this->web3->eth->sendRawTransaction(
                $rawTx,
                function ($err, $hash) use (&$txHash, &$error) {
                    if ($err !== null) {
                        $error = $err->getMessage();
                    } else {
                        $txHash = $hash;
                    }
                }
            );

            if ($error) {
                throw new \Exception('Blockchain transaction failed: ' . $error);
            }

            if (empty($txHash)) {
                throw new \Exception('Transaction hash not returned');
            }

            // Wait for transaction confirmation
            $confirmed = $this->waitForTransaction($txHash);

            return [
                'success' => true,
                'tx_hash' => $txHash,
                'confirmed' => $confirmed,
                'mock' => false
            ];
        } catch (\Exception $e) {
            error_log('Blockchain revokeCertificate failed: ' . $e->getMessage());
            return [
                'success' => false,
                'tx_hash' => null,
                'error' => $e->getMessage(),
                'mock' => false
            ];
        }
    }
}
