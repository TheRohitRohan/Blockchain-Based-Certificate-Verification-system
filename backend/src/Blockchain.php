<?php

namespace App;

use phpseclib\Math\BigInteger;
use Web3\Web3;
use Web3\Contract;

class Blockchain
{
    private $web3;
    private $contract;
    private $config;
    private $isConnected = false;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config.php';
        $bc = $this->config['blockchain'];

        if (empty($bc['rpc_url']) || empty($bc['contract_address'])) {
            error_log('Blockchain configuration missing - running in mock mode');
            return;
        }

        try {
            $blockNumber = null;
            $connError   = null;

            // Create Web3 instance
            $this->web3 = new Web3($bc['rpc_url']);

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

        } catch (\Exception $e) {
            error_log('Blockchain connection failed: ' . $e->getMessage());
            $this->isConnected = false;
        }

        try {
            // Load ABI
            $abiPath = __DIR__ . '/../abi/CertificateRegistry.json';
            if (!file_exists($abiPath)) {
                error_log('Contract ABI file not found - running in mock mode');
                return;
            }

            $abi = json_decode(file_get_contents($abiPath), true);
            if (!$abi) {
                error_log('Invalid ABI JSON - running in mock mode');
                return;
            }

            if ($this->web3) {
                $this->contract = new Contract($this->web3->provider, $abi);
                $this->contract->at($bc['contract_address']);
            }
        } catch (\Exception $e) {
            error_log("Blockchain contract setup failed: " . $e->getMessage());
            $this->isConnected = false;
        }
    }
    
    public function isConnected(): bool {
        return $this->isConnected;
    }

    /* =======================
       BASIC CONNECTIVITY TEST
       ======================= */

    public function getCurrentBlock(): int
    {
        // If not connected, return mock block number
        if (!$this->isConnected) {
            return 1;
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
        // FIX 1: If not connected, return false (fail-safe)
        // Previously returned true which made all certificates valid when blockchain was down
        if (!$this->isConnected || !isset($this->contract)) {
            error_log("Blockchain verification failed: not connected");
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
        // If not connected to blockchain, use mock mode
        if (!$this->isConnected || !isset($this->contract)) {
            return [
                'success' => true,
                'tx_hash' => '0x' . bin2hex(random_bytes(32)),
                'certificate_hash' => $this->generateCertificateHash($certificateData),
                'note' => 'Mock transaction - blockchain not connected'
            ];
        }

        try {
            $config = $this->config;
            $privateKey = $config['blockchain']['private_key'];
            
            if (empty($privateKey)) {
                // For testing without private key, return mock success
                return [
                    'success' => true,
                    'tx_hash' => '0x' . bin2hex(random_bytes(32)),
                    'certificate_hash' => $this->generateCertificateHash($certificateData),
                    'note' => 'Mock transaction - configure private key for real blockchain'
                ];
            }

            $certificateId = $certificateData['certificate_id'];
            $studentName = $certificateData['student_name'];
            $universityName = $certificateData['university_name'] ?? 'Test University';
            $courseName = $certificateData['course_name'];
            $issueDate = $certificateData['issue_date'];
            $certificateHash = $certificateData['certificate_hash']
                ?? $this->generateKeccak256Hash(json_encode($certificateData));

            $txHash = null;
            $error = null;
            $fromAddress = $this->getAddressFromPrivateKey($privateKey);
            $transactionClass = 'kornrunner\\Ethereum\\Transaction';

            if (class_exists($transactionClass)) {
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

                $calldata = '';
                $this->contract->getData(
                    'issueCertificate',
                    $certificateId,
                    $studentName,
                    $universityName,
                    $courseName,
                    $issueDate,
                    $certificateHash,
                    function ($err, $data) use (&$calldata, &$error) {
                        if ($err !== null) {
                            $error = 'getData failed: ' . $err->getMessage();
                        } else {
                            $calldata = $data;
                        }
                    }
                );

                if ($error) {
                    throw new \Exception('ABI encoding failed: ' . $error);
                }

                $gasLimit = $this->normalizeRpcHexQuantity((int) $config['blockchain']['gas_limit']);
                $to = $config['blockchain']['contract_address'];

                $tx = new $transactionClass(
                    $nonce,
                    $gasPrice,
                    $gasLimit,
                    $to,
                    '0',
                    $calldata
                );

                $cleanKey = ltrim($privateKey, '0x');
                $rawTx = '0x' . $tx->getRaw($cleanKey, $config['blockchain']['chain_id']);

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
            } else {
                $this->contract->send(
                    'issueCertificate',
                    $certificateId,
                    $studentName,
                    $universityName,
                    $courseName,
                    $issueDate,
                    $certificateHash,
                    [
                        'from' => $fromAddress,
                        'gas' => $config['blockchain']['gas_limit']
                    ],
                    function ($err, $tx) use (&$txHash, &$error) {
                        if ($err !== null) {
                            $error = $err->getMessage();
                            return;
                        }
                        $txHash = $tx;
                    }
                );
            }

            if ($error) {
                throw new \Exception('Blockchain transaction failed: ' . $error);
            }

            if (empty($txHash)) {
                throw new \Exception('Transaction hash not returned');
            }

            // Wait for transaction confirmation
            $this->waitForTransaction($txHash);

            return [
                'success' => true,
                'tx_hash' => $txHash,
                'certificate_hash' => $certificateHash
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
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
        // In a production environment, use a proper library like web3.php's account functions
        // For now, this is a simplified approach
        $config = require __DIR__ . '/../config.php';
        
        // If we have a default address in config, use it
        if (isset($config['blockchain']['default_address']) && !empty($config['blockchain']['default_address'])) {
            return $config['blockchain']['default_address'];
        }
        
        // For Ganache, return a common test address
        return '0x90F8bf6A479f320ead074411a4B0e7944Ea8c9C1';
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

    private function waitForTransaction(string $txHash, int $maxWaitTime = 30): bool
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

            sleep(1);
        }

        throw new \Exception('Transaction confirmation timeout');
    }

    public function revokeCertificate(string $certificateId): array
    {
        // If not connected, use mock mode
        if (!$this->isConnected || !isset($this->contract)) {
            return [
                'success' => true,
                'tx_hash' => '0x' . bin2hex(random_bytes(32)),
                'note' => 'Mock revocation - blockchain not connected'
            ];
        }

        try {
            $config = $this->config;
            $privateKey = $config['blockchain']['private_key'];
            
            if (empty($privateKey)) {
                throw new \Exception('Private key not configured for blockchain transactions');
            }

            $txHash = null;
            $error = null;

            $this->contract->send(
                'revokeCertificate',
                $certificateId,
                [
                    'from' => $this->getAddressFromPrivateKey($privateKey),
                    'gas'  => $config['blockchain']['gas_limit']
                ],
                function ($err, $tx) use (&$txHash, &$error) {
                    if ($err !== null) {
                        $error = $err->getMessage();
                        return;
                    }
                    $txHash = $tx;
                }
            );

            if ($error) {
                throw new \Exception('Blockchain transaction failed: ' . $error);
            }

            if (empty($txHash)) {
                throw new \Exception('Transaction hash not returned');
            }

            // Wait for transaction confirmation
            $this->waitForTransaction($txHash);

            return [
                'success' => true,
                'tx_hash' => $txHash
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}












// namespace App;

// class Blockchain {
//     private $rpcUrl;
//     private $contractAddress;
//     private $privateKey;
//     private $gasLimit;

//     public function __construct() {
//         $config = require __DIR__ . '/../config.php';
//         $bc = $config['blockchain'];
        
//         $this->rpcUrl = $bc['rpc_url'];
//         $this->contractAddress = $bc['contract_address'];
//         $this->privateKey = $bc['private_key'];
//         $this->gasLimit = $bc['gas_limit'];
//     }

//     private function callRPC($method, $params = []) {
//         $data = [
//             'jsonrpc' => '2.0',
//             'method' => $method,
//             'params' => $params,
//             'id' => 1
//         ];

//         $ch = curl_init($this->rpcUrl);
//         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//         curl_setopt($ch, CURLOPT_POST, true);
//         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
//         curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

//         $response = curl_exec($ch);
//         curl_close($ch);

//         return json_decode($response, true);
//     }

//     public function issueCertificate($certificateData) {
//         // This is a simplified version. In production, you'd use web3.php or similar
//         // For now, we'll simulate the transaction
        
//         $certificateHash = hash('sha256', json_encode($certificateData));
        
//         // In a real implementation, you would:
//         // 1. Encode the function call
//         // 2. Sign the transaction with the private key
//         // 3. Send it to the blockchain
        
//         // For demonstration, return a mock transaction hash
//         $txHash = '0x' . bin2hex(random_bytes(32));
        
//         return [
//             'success' => true,
//             'tx_hash' => $txHash,
//             'certificate_hash' => $certificateHash
//         ];
//     }

//     public function verifyCertificate($certificateId, $certificateHash) {
//         // Call the smart contract's verifyCertificate function
//         // This is simplified - in production use web3.php
        
//         $data = $this->encodeFunctionCall('verifyCertificate', [
//             'string' => $certificateId,
//             'string' => $certificateHash
//         ]);

//         $result = $this->callRPC('eth_call', [[
//             'to' => $this->contractAddress,
//             'data' => $data
//         ], 'latest']);

//         if (isset($result['result'])) {
//             // Decode the boolean result
//             $hex = substr($result['result'], -1);
//             return $hex === '1';
//         }

//         return false;
//     }

//     public function getCertificate($certificateId) {
//         // Call the smart contract's getCertificate function
//         $data = $this->encodeFunctionCall('getCertificate', ['string' => $certificateId]);
        
//         $result = $this->callRPC('eth_call', [[
//             'to' => $this->contractAddress,
//             'data' => $data
//         ], 'latest']);

//         // Decode the result (simplified)
//         return $result;
//     }

//     private function encodeFunctionCall($functionName, $params) {
//         // Simplified function encoding
//         // In production, use a proper ABI encoder
//         $functionSignature = $this->getFunctionSignature($functionName);
//         return $functionSignature . '00000000000000000000000000000000000000000000000000000000';
//     }

//     private function getFunctionSignature($functionName) {
//         // Simplified - in production, use proper keccak256 hashing
//         $signatures = [
//             'verifyCertificate' => '0x12345678',
//             'getCertificate' => '0x87654321',
//             'issueCertificate' => '0xabcdef12'
//         ];
//         return $signatures[$functionName] ?? '0x00000000';
//     }

//     public function generateCertificateHash($certificateData) {
//         return hash('sha256', json_encode($certificateData));
//     }
// }
