<?php

/**
 * PHPUnit Bootstrap
 *
 * Loads Composer autoloader, dotenv, and shared TestState.
 * Uses the REAL .env and database — no fakes, no SQLite, no overrides.
 */

// Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Shared test state (not autoloaded — plain class outside App\ namespace)
require_once __DIR__ . '/TestState.php';

// Load .env from project root (same file the app uses)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Sanity check: JWT_SECRET must be set
if (empty($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET'))) {
    throw new \RuntimeException(
        "JWT_SECRET is not set in .env — test suite requires the real environment.\n"
        . "Ensure backend/.env exists and contains JWT_SECRET."
    );
}
