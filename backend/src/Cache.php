<?php

namespace App;

use Predis\Client as RedisClient;

class Cache {
    private static $instance = null;
    private $driver;
    private $redis = null;
    private $config;
    
    private function __construct() {
        $this->config = require __DIR__ . '/../config.php';
        $this->driver = $this->config['cache']['driver'] ?? 'file';
        
        if ($this->driver === 'redis') {
            $this->initRedis();
        }
        
        // Ensure cache directory exists for file driver
        if ($this->driver === 'file') {
            $cachePath = $this->config['storage']['cache_path'];
            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0755, true);
            }
        }
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initRedis() {
        try {
            $redisConfig = $this->config['redis'];
            $this->redis = new RedisClient([
                'host' => $redisConfig['host'],
                'port' => $redisConfig['port'],
                'password' => $redisConfig['password'] ?? null,
                'database' => $redisConfig['db'] ?? 0
            ]);
            // Test connection
            $this->redis->ping();
        } catch (\Exception $e) {
            // Fallback to file cache if Redis fails
            error_log("Redis connection failed, falling back to file cache: " . $e->getMessage());
            $this->driver = 'file';
            $this->redis = null;
        }
    }
    
    public function get(string $key, $default = null) {
        if ($this->driver === 'redis' && $this->redis !== null) {
            try {
                $value = $this->redis->get($key);
                return $value !== null ? json_decode($value, true) : $default;
            } catch (\Exception $e) {
                error_log("Redis get failed: " . $e->getMessage());
                return $default;
            }
        } else {
            // File cache
            $file = $this->getCacheFile($key);
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['expires']) && $data['expires'] > time()) {
                    return $data['value'];
                } else {
                    // Expired, delete file
                    @unlink($file);
                }
            }
            return $default;
        }
    }
    
    public function set(string $key, $value, int $ttl = null): bool {
        $ttl = $ttl ?? $this->config['cache']['ttl'] ?? 3600;
        
        if ($this->driver === 'redis' && $this->redis !== null) {
            try {
                return $this->redis->setex($key, $ttl, json_encode($value)) === 'OK';
            } catch (\Exception $e) {
                error_log("Redis set failed: " . $e->getMessage());
                return false;
            }
        } else {
            // File cache
            $file = $this->getCacheFile($key);
            $data = [
                'value' => $value,
                'expires' => time() + $ttl
            ];
            return file_put_contents($file, json_encode($data)) !== false;
        }
    }
    
    public function delete(string $key): bool {
        if ($this->driver === 'redis' && $this->redis !== null) {
            try {
                return $this->redis->del($key) > 0;
            } catch (\Exception $e) {
                error_log("Redis delete failed: " . $e->getMessage());
                return false;
            }
        } else {
            $file = $this->getCacheFile($key);
            return file_exists($file) ? unlink($file) : true;
        }
    }
    
    public function flush(): bool {
        if ($this->driver === 'redis' && $this->redis !== null) {
            try {
                return $this->redis->flushdb() === 'OK';
            } catch (\Exception $e) {
                error_log("Redis flush failed: " . $e->getMessage());
                return false;
            }
        } else {
            $cachePath = $this->config['storage']['cache_path'];
            $files = glob($cachePath . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            return true;
        }
    }
    
    private function getCacheFile(string $key): string {
        $cachePath = $this->config['storage']['cache_path'];
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $cachePath . $safeKey . '.cache';
    }
    
    public function remember(string $key, callable $callback, int $ttl = null) {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}
