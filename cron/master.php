#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Master pipeline script.
 * Runs all pipeline steps sequentially: fetch → scrape → process → publish.
 *
 * Usage: php cron/master.php
 * Crontab: * * * * * php /var/www/html/writer/cron/master.php
 */

require_once __DIR__ . '/../config/app.php';

use NewsBot\Core\{Lock, Logger, ShutdownHandler, Settings};

// Initialize shutdown handler
ShutdownHandler::init();

// Acquire lock
$lock = new Lock('master');
if (!$lock->acquire()) {
    Logger::warning('Master script already running, skipping');
    exit(0);
}

try {
    Logger::info('Master pipeline started');
    $startTime = microtime(true);

    // Step 1: Fetch
    Settings::clearCache();
    if (ShutdownHandler::shouldShutdown()) {
        Logger::info('Shutdown before fetch');
        goto done;
    }
    $fetchStep = new \NewsBot\Pipeline\FetchStep();
    $fetchStep->run();
    Logger::debug('Fetch step completed');

    // Step 2: Scrape
    Settings::clearCache();
    if (ShutdownHandler::shouldShutdown()) {
        Logger::info('Shutdown before scrape');
        goto done;
    }
    $scrapeStep = new \NewsBot\Pipeline\ScrapeStep();
    $scrapeStep->run();
    Logger::debug('Scrape step completed');

    // Step 3: Process
    Settings::clearCache();
    if (ShutdownHandler::shouldShutdown()) {
        Logger::info('Shutdown before process');
        goto done;
    }
    $processStep = new \NewsBot\Pipeline\ProcessStep();
    $processStep->run();
    Logger::debug('Process step completed');

    // Step 3.5: Image selection
    Settings::clearCache();
    if (ShutdownHandler::shouldShutdown()) {
        Logger::info('Shutdown before image');
        goto done;
    }
    $imageStep = new \NewsBot\Pipeline\ImageStep();
    $imageStep->run();
    Logger::debug('Image step completed');

    // Step 4: Publish to Telegram
    Settings::clearCache();
    if (ShutdownHandler::shouldShutdown()) {
        Logger::info('Shutdown before publish');
        goto done;
    }
    $publishStep = new \NewsBot\Pipeline\PublishStep();
    $publishStep->run();
    Logger::debug('Publish step completed');

    // Step 5: Publish to websites (REST)
    Settings::clearCache();
    if (ShutdownHandler::shouldShutdown()) {
        Logger::info('Shutdown before web publish');
        goto done;
    }
    $webPublishStep = new \NewsBot\Pipeline\WebPublishStep();
    $webPublishStep->run();
    Logger::debug('Web publish step completed');

    done:
    $duration = round((microtime(true) - $startTime) * 1000);
    Logger::info('Master pipeline completed', ['duration_ms' => $duration]);

} catch (\Throwable $e) {
    Logger::error('Master pipeline failed: ' . $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
} finally {
    $lock->release();
}
