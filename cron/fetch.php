#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Fetch step - collects articles from RSS feeds and custom parsers.
 *
 * Usage: php cron/fetch.php [--channel=1,2,3]
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
$lockName = 'fetch';
$lock = new Lock($lockName, $channelIds);
if (!$lock->acquire()) {
    Logger::warning('Fetch script already running, skipping', ['channels' => $channelIds]);
    exit(0);
}

try {
    Logger::info('Fetch step started', ['channels' => $channelIds ?: 'all']);
    $startTime = microtime(true);

    $step = new \NewsBot\Pipeline\FetchStep();
    $step->run($channelIds);

    $duration = round((microtime(true) - $startTime) * 1000);
    Logger::info('Fetch step completed', ['duration_ms' => $duration]);

} catch (\Throwable $e) {
    Logger::error('Fetch step failed: ' . $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
} finally {
    $lock->release();
}
