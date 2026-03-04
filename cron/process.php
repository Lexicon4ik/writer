#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Process step - AI processing and validation of scraped articles.
 *
 * Usage: php cron/process.php [--channel=1,2,3]
 */

require_once __DIR__ . '/../config/app.php';

use NewsBot\Core\{Lock, Logger, ShutdownHandler};

// Parse arguments
$channelIds = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--channel=')) {
        $channelIds = array_map('intval', explode(',', substr($arg, 10)));
    }
}

// Initialize
ShutdownHandler::init();

// Acquire lock
$lockName = 'process';
$lock = new Lock($lockName, $channelIds);
if (!$lock->acquire()) {
    Logger::warning('Process script already running, skipping', ['channels' => $channelIds]);
    exit(0);
}

try {
    Logger::info('Process step started', ['channels' => $channelIds ?: 'all']);
    $startTime = microtime(true);

    $step = new \NewsBot\Pipeline\ProcessStep();
    $step->run($channelIds);

    $duration = round((microtime(true) - $startTime) * 1000);
    Logger::info('Process step completed', ['duration_ms' => $duration]);

} catch (\Throwable $e) {
    Logger::error('Process step failed: ' . $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
} finally {
    $lock->release();
}
