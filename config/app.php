<?php declare(strict_types=1);

/**
 * Application configuration
 * Loads Composer autoloader and .env via phpdotenv
 */

// Composer autoloader (PSR-4: NewsBot\Web\Controllers\ → web/Controllers/, NewsBot\ → src/)
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad(); // Doesn't fail if .env doesn't exist (production may use system env vars)

// Path constants
define('ROOT_DIR', dirname(__DIR__));
define('LOG_DIR', ROOT_DIR . '/logs');
define('LOCK_DIR', ROOT_DIR . '/locks');

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', (int)($_ENV['DB_PORT'] ?? 3306));
define('DB_NAME', $_ENV['DB_NAME'] ?? 'newsbot_writer');
define('DB_USER', $_ENV['DB_USER'] ?? 'newsbot');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');
