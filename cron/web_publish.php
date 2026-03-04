#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Web publish step - sends processed articles to website REST endpoints.
 *
 * Usage: php cron/web_publish.php [--endpoint=1,2,3]
 * Can be run standalone or as part of master.php pipeline.
 */

require_once __DIR__ . '/../config/app.php';

use NewsBot\Core\{Lock, Logger, ShutdownHandler};

// Parse arguments
$endpointIds = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--endpoint=')) {
        $endpointIds = array_map('intval', explode(',', substr($arg, 11)));
    }
}

// Initialize
ShutdownHandler::init();

// Acquire lock
$lockName = 'web_publish';
$lock = new Lock($lockName, $endpointIds);
if (!$lock->acquire()) {
    Logger::warning('Web publish script already running, skipping', ['endpoints' => $endpointIds]);
    exit(0);
}

try {
    Logger::info('Web publish step started', ['endpoints' => $endpointIds ?: 'all']);
    $startTime = microtime(true);

    $step = new \NewsBot\Pipeline\WebPublishStep();
    $step->run($endpointIds);

    $duration = round((microtime(true) - $startTime) * 1000);
    Logger::info('Web publish step completed', ['duration_ms' => $duration]);

} catch (\Throwable $e) {
    Logger::error('Web publish step failed: ' . $e->getMessage(), [
        'exception' => get_class($e),
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
    ]);
} finally {
    $lock->release();
}
