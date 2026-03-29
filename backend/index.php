<?php
// Railway Nginx Fallback Router
// Nginx ignores .htaccess, and by default routes all missing paths to /index.php.
// This file catches those requests and hands them off to your actual API router.

require_once __DIR__ . '/api/index.php';
