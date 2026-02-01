<?php

namespace App;

use Web3\Web3;
use Web3\Contract;

class Blockchain
{
    private Web3 $web3;
    private Contract $contract;

    public function __construct()
    {
        $config = require __DIR__ . '/../config.php';
        $bc = $config['blockchain'];

        if (empty($bc['rpc_url']) || empty($bc['contract_address'])) {
            throw new \Exception('Blockchain configuration missing');
        }

        // ✅ Let Web3 handle the provider internally (version-safe)
        $this->web3 = new Web3($bc['rpc_url']);

        // Load ABI
        $abiPath = __DIR__ . '/../abi/CertificateRegistry.json';
        if (!file_exists($abiPath)) {
            throw new \Exception('Contract ABI file not found');
        }

        $abi = json_decode(file_get_contents($abiPath), true);
        if (!$abi) {
            throw new \Exception('Invalid ABI JSON');
        }

        // ✅ Use provider already initialized by Web3
        $this->contract = new Contract($this->web3->provider, $abi);
        $this->contract->at($bc['contract_address']);
    }

    /* =======================
       BASIC CONNECTIVITY TEST
       ======================= */

public function getCurrentBlock(): int
    {
        $blockNumber = 0;
    
        $this->web3->eth->blockNumber(function ($err, $block) use (&$blockNumber) {
            if ($err !== null) {
                throw new \Exception('Failed to connect to blockchain: ' . $err->getMessage());
            }
    
            // Handle BigInteger properly
            if ($block instanceof \phpseclib\Math\BigInteger) {
                $blockNumber = (int) $block->toString();
            } elseif (is_string($block) && strpos($block, '0x') === 0) {
                $blockNumber = hexdec($block);
            } else {
                $blockNumber = (int) $block;
            }
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
        return hash('sha256', json_encode($certificateData));
    }

    public function issueCertificate($certificateData): array
    {
        try {
            $config = require __DIR__ . '/../config.php';
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
            $certificateHash = $this->generateCertificateHash($certificateData);

            $txHash = null;
            $error = null;

            // Send transaction to smart contract (6 parameters as per ABI)
            $this->contract->send(
                'issueCertificate',
                $certificateId,        // certificateId
                $studentName,          // studentName  
                $universityName,      // universityName
                $courseName,          // courseName
                $issueDate,           // issueDate
                $certificateHash,     // certificateHash
                [
                    'from' => $this->getAddressFromPrivateKey($privateKey),
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
        try {
            $config = require __DIR__ . '/../config.php';
            $privateKey = $config['blockchain']['private_key'];
            
            if (empty($privateKey)) {
                throw new \Exception('Private key not configured for blockchain transactions');
            }

            $txHash = null;
            $error = null;

            $this->contract->send(
                'revokeCertificate',
                [$certificateId],
                [
                    'from' => $this->getAddressFromPrivateKey($privateKey),
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

