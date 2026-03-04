<?php declare(strict_types=1);

/**
 * Web Admin Router
 * Entry point for admin interface with security headers and CSRF protection
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../helpers/Lang.php';

use NewsBot\Core\Logger;
use NewsBot\Web\Helpers\Lang;

// Start session with secure settings
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', '1');
}
session_start();

// Initialize language system
Lang::init(__DIR__ . '/../lang');

// Handle language switching via ?lang=xx
if (isset($_GET['lang']) && Lang::isValidLocale($_GET['lang'])) {
    Lang::setLocale($_GET['lang']);
    // Redirect to remove lang parameter from URL
    $url = strtok($_SERVER['REQUEST_URI'], '?');
    $params = $_GET;
    unset($params['lang']);
    if ($params) {
        $url .= '?' . http_build_query($params);
    }
    header("Location: {$url}");
    exit;
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; font-src https://cdn.jsdelivr.net; img-src 'self' data: https:");

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Session timeout (2 hours)
$sessionTimeout = 7200;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $sessionTimeout) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$_SESSION['last_activity'] = time();

// Determine page and action
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Public pages that don't require authentication
$publicPages = ['login'];

// Check authorization (health.php is a separate file)
if (!in_array($page, $publicPages) && empty($_SESSION['admin_id'])) {
    header('Location: ?page=login');
    exit;
}

// Controller mapping
$controllers = [
    'login'     => 'AuthController',
    'dashboard' => 'DashboardController',
    'bots'      => 'BotController',
    'channels'  => 'ChannelController',
    'sources'   => 'SourceController',
    'parsers'   => 'ParserController',
    'articles'  => 'ArticleController',
    'stats'     => 'StatsController',
    'settings'  => 'SettingsController',
    'websites'  => 'WebsiteController',
];

$controllerName = $controllers[$page] ?? null;

if (!$controllerName) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

$controllerClass = "NewsBot\\Web\\Controllers\\{$controllerName}";

// Check if controller class exists
if (!class_exists($controllerClass)) {
    http_response_code(404);
    echo "Controller not found: {$controllerName}";
    exit;
}

try {
    $controller = new $controllerClass();

    // Call action
    if (method_exists($controller, $action)) {
        $controller->$action($id);
    } else {
        http_response_code(404);
        echo "Action not found: {$action}";
    }
} catch (\Throwable $e) {
    Logger::error('Web controller error', [
        'page' => $page,
        'action' => $action,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    http_response_code(500);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal server error']);
    } else {
        echo '<h1>Error</h1>';
        echo '<p>An error occurred. Please try again later.</p>';
        if (($_ENV['APP_DEBUG'] ?? '') === 'true') {
            echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
    }
}
