<?php declare(strict_types=1);

/**
 * Health Check Endpoint
 * Returns JSON with system health status.
 * Protected by optional bearer token from settings.health_check_token.
 */

require_once __DIR__ . '/../../config/app.php';

use NewsBot\Core\{Database, Settings};

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Allowed IPs (localhost always allowed)
$allowedIps = ['127.0.0.1', '::1'];

// Get health check token from settings (if configured)
try {
    $healthToken = Settings::get('health_check_token', '');
} catch (\Throwable $e) {
    // Database not available, still try to respond
    $healthToken = '';
}

// Authentication check
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$bearerToken = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
    $bearerToken = $matches[1];
}

$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($clientIp, ',') !== false) {
    $clientIp = trim(explode(',', $clientIp)[0]);
}

// Check authorization
$isAllowedIp = in_array($clientIp, $allowedIps);
$hasValidToken = !empty($healthToken) && $bearerToken === $healthToken;
$tokenNotRequired = empty($healthToken);

if (!$isAllowedIp && !$hasValidToken && !$tokenNotRequired) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$checks = [];
$errors = [];

// 1. Database connectivity
try {
    Database::query("SELECT 1");
    $checks['db'] = true;
} catch (\Throwable $e) {
    $checks['db'] = false;
    $errors[] = 'Database connection failed';
}

// 2. Last pipeline runs
try {
    $lastFetch = Database::fetchOne(
        "SELECT MAX(finished_at) as last FROM pipeline_runs WHERE step = 'fetch' AND finished_at IS NOT NULL"
    );
    $checks['last_fetch'] = $lastFetch['last'] ?? null;

    $lastScrape = Database::fetchOne(
        "SELECT MAX(finished_at) as last FROM pipeline_runs WHERE step = 'scrape' AND finished_at IS NOT NULL"
    );
    $checks['last_scrape'] = $lastScrape['last'] ?? null;

    $lastProcess = Database::fetchOne(
        "SELECT MAX(finished_at) as last FROM pipeline_runs WHERE step = 'process' AND finished_at IS NOT NULL"
    );
    $checks['last_process'] = $lastProcess['last'] ?? null;

    $lastPublish = Database::fetchOne(
        "SELECT MAX(finished_at) as last FROM pipeline_runs WHERE step = 'publish' AND finished_at IS NOT NULL"
    );
    $checks['last_publish'] = $lastPublish['last'] ?? null;

    // Check if pipeline is stale (no runs in last 30 minutes)
    $lastRun = Database::fetchOne(
        "SELECT MAX(finished_at) as last FROM pipeline_runs WHERE finished_at IS NOT NULL"
    );
    if ($lastRun['last']) {
        $minutesAgo = (time() - strtotime($lastRun['last'])) / 60;
        $checks['pipeline_stale'] = $minutesAgo > 30;
        if ($checks['pipeline_stale']) {
            $errors[] = 'Pipeline stale: last run was ' . round($minutesAgo) . ' minutes ago';
        }
    } else {
        $checks['pipeline_stale'] = true;
        $errors[] = 'No pipeline runs found';
    }
} catch (\Throwable $e) {
    $checks['last_fetch'] = null;
    $checks['last_publish'] = null;
    $checks['pipeline_stale'] = true;
    $errors[] = 'Failed to check pipeline status';
}

// 3. Manual review count
try {
    $reviewCount = Database::fetchOne(
        "SELECT COUNT(*) as cnt FROM article_versions WHERE status = 'manual_review'"
    );
    $checks['manual_review_count'] = (int)($reviewCount['cnt'] ?? 0);
} catch (\Throwable $e) {
    $checks['manual_review_count'] = 0;
}

// 4. Auto-disabled feeds
try {
    $disabledCount = Database::fetchOne(
        "SELECT COUNT(*) as cnt FROM feeds WHERE status = 'auto_disabled'"
    );
    $checks['auto_disabled_feeds'] = (int)($disabledCount['cnt'] ?? 0);
    if ($checks['auto_disabled_feeds'] > 0) {
        $errors[] = $checks['auto_disabled_feeds'] . ' feeds auto-disabled';
    }
} catch (\Throwable $e) {
    $checks['auto_disabled_feeds'] = 0;
}

// 5. AI budget usage
try {
    $todayCost = Database::fetchOne(
        "SELECT COALESCE(SUM(estimated_cost), 0) as cost FROM ai_usage_log WHERE DATE(created_at) = CURDATE()"
    );
    $budget = Settings::getFloat('ai_daily_budget', 10.0);
    $checks['ai_budget_used'] = round((float)($todayCost['cost'] ?? 0), 4);
    $checks['ai_budget_limit'] = $budget;

    if ($budget > 0 && $checks['ai_budget_used'] >= $budget) {
        $errors[] = 'AI budget exhausted';
    }
} catch (\Throwable $e) {
    $checks['ai_budget_used'] = 0;
    $checks['ai_budget_limit'] = 10.0;
}

// Determine overall health
$healthy = $checks['db'] === true && !($checks['pipeline_stale'] ?? true);

http_response_code($healthy ? 200 : 503);

echo json_encode([
    'status' => $healthy ? 'ok' : 'error',
    'db' => $checks['db'] ?? false,
    'last_fetch' => $checks['last_fetch'] ?? null,
    'last_publish' => $checks['last_publish'] ?? null,
    'errors' => $errors,
    'checks' => $checks,
    'timestamp' => date('c'),
], JSON_PRETTY_PRINT);
