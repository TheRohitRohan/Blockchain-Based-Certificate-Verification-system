<?php

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Helper function to get env variable with fallback
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }
        
        // Convert string booleans
        if (strtolower($value) === 'true') return true;
        if (strtolower($value) === 'false') return false;
        
        return $value;
    }
}

// FIX 4: JWT secret must be explicitly set - no insecure fallback
$jwtSecret = env('JWT_SECRET');
if (empty($jwtSecret)) {
    throw new \RuntimeException("JWT_SECRET environment variable must be set. Do not use default values in production.");
}

return [
    'database' => [
        'host' => env('DB_HOST'),
        'port' => (int)env('DB_PORT', 3306),
        'dbname' => env('DB_NAME'),
        'username' => env('DB_USER'),
        'password' => env('DB_PASS'),
        'charset' => env('DB_CHARSET')
    ],
    'blockchain' => [
        'rpc_url' => env('BLOCKCHAIN_RPC'),
        'contract_address' => env('CONTRACT_ADDRESS'),
        'private_key' => env('BLOCKCHAIN_PRIVATE_KEY'),
        'default_address' => env('BLOCKCHAIN_DEFAULT_ADDRESS'),
        'wallet_address' => env('BLOCKCHAIN_WALLET_ADDRESS'),
        'gas_limit' => (int)env('BLOCKCHAIN_GAS_LIMIT'),
        'chain_id' => (int)env('BLOCKCHAIN_CHAIN_ID')
    ],
    'jwt' => [
        'secret' => $jwtSecret,
        'algorithm' => env('JWT_ALGORITHM'),
        'expiration' => (int)env('JWT_EXPIRATION')
    ],
    'app' => [
        'base_url'     => env('APP_URL'),
        'frontend_url' => env('FRONTEND_URL'),
        'api_url'      => env('API_URL'),
        'env'          => env('APP_ENV'),
        'debug'        => env('APP_DEBUG')
    ],
    'storage' => [
        'pdf_path' => __DIR__ . '/' . env('STORAGE_PDF_PATH'),
        'qr_path' => __DIR__ . '/' . env('STORAGE_QR_PATH'),
        'certs_path' => __DIR__ . '/' . env('STORAGE_CERTS_PATH'),
        'cache_path' => __DIR__ . '/' . env('STORAGE_CACHE_PATH'),
        'base_url' => env('STORAGE_BASE_URL')
    ],
    'redis' => [
        'host' => env('REDIS_HOST'),
        'port' => (int)env('REDIS_PORT'),
        'password' => env('REDIS_PASSWORD'),
        'db' => (int)env('REDIS_DB')
    ],
    'cache' => [
        'driver' => env('CACHE_DRIVER'),
        'ttl' => (int)env('CACHE_TTL'),
        'verification_ttl' => (int)env('VERIFICATION_CACHE_TTL')
    ],
    'signing' => [
        'default_cert_path' => env('DEFAULT_SIGNING_CERT_PATH'),
        'default_cert_password' => env('DEFAULT_SIGNING_CERT_PASSWORD'),
        'require_signature' => env('REQUIRE_SIGNATURE'),
        'key_encryption_secret' => env('KEY_ENCRYPTION_SECRET'),
    ],
    'supabase' => [
        'url'         => env('SUPABASE_URL'),
        'service_key' => env('SUPABASE_SERVICE_KEY'),
        'public_url'  => env('SUPABASE_PUBLIC_URL'),
    ],
    'mail' => [
        'host'       => env('MAIL_HOST', 'smtp.gmail.com'),
        'port'       => (int) env('MAIL_PORT', 587),
        'username'   => env('MAIL_USERNAME'),
        'password'   => env('MAIL_PASSWORD'),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'from_address' => env('MAIL_FROM_ADDRESS'),
        'from_name'    => env('MAIL_FROM_NAME', 'Certificate Verification System'),
    ]
];

