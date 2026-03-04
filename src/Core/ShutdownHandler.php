<?php declare(strict_types=1);

namespace NewsBot\Core;

/**
 * Graceful shutdown handler for cron scripts.
 * Handles SIGTERM/SIGINT signals and file-based shutdown flag.
 */
class ShutdownHandler
{
    private static bool $shutdown = false;
    private static bool $initialized = false;

    /**
     * Initialize shutdown handling.
     * Call at the start of cron scripts.
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // Check if pcntl is available (not on Windows/some shared hosting)
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [self::class, 'handleSignal']);
            pcntl_signal(SIGINT, [self::class, 'handleSignal']);
        } else {
            // Fallback: use file-based shutdown
            // Admin can create locks/shutdown file to stop processing
            Logger::debug('pcntl not available, using file-based shutdown');
        }

        self::$initialized = true;
    }

    /**
     * Handle OS signal.
     */
    public static function handleSignal(int $signal): void
    {
        self::$shutdown = true;
        Logger::info('Received signal ' . $signal . ', finishing current work...');
    }

    /**
     * Check if shutdown was requested.
     * Call between processing items to allow graceful exit.
     */
    public static function shouldShutdown(): bool
    {
        // pcntl signal already received
        if (self::$shutdown) {
            return true;
        }

        // Check for shutdown file flag
        $shutdownFile = ROOT_DIR . '/locks/shutdown';
        return file_exists($shutdownFile);
    }

    /**
     * Create shutdown flag file (for admin use).
     */
    public static function requestShutdown(): void
    {
        $shutdownFile = ROOT_DIR . '/locks/shutdown';
        $lockDir = dirname($shutdownFile);

        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }

        file_put_contents($shutdownFile, (string)time());
        Logger::info('Shutdown requested via file flag');
    }

    /**
     * Clear shutdown flag file.
     */
    public static function clearShutdown(): void
    {
        $shutdownFile = ROOT_DIR . '/locks/shutdown';
        if (file_exists($shutdownFile)) {
            unlink($shutdownFile);
        }
        self::$shutdown = false;
    }

    /**
     * Reset state (for testing).
     */
    public static function reset(): void
    {
        self::$shutdown = false;
        self::$initialized = false;
    }
}
