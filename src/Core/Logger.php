<?php declare(strict_types=1);

namespace NewsBot\Core;

/**
 * File logger with JSON Lines format and rotation.
 */
class Logger
{
    public const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
    public const MAX_FILES = 5;

    private static ?string $logFile = null;

    /**
     * Get log file path.
     */
    private static function getLogFile(): string
    {
        if (self::$logFile === null) {
            self::$logFile = ROOT_DIR . '/logs/app.log';
        }
        return self::$logFile;
    }

    /**
     * Log debug message.
     */
    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    /**
     * Log info message.
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /**
     * Log warning message.
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /**
     * Log error message.
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * Write log entry.
     */
    private static function write(string $level, string $message, array $context): void
    {
        $logFile = self::getLogFile();
        $logDir = dirname($logFile);

        // Ensure log directory exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Check for rotation
        self::rotate();

        // Write log line
        $line = self::formatLine($level, $message, $context);
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Format log entry as JSON line.
     */
    private static function formatLine(string $level, string $message, array $context): string
    {
        return json_encode([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => $level,
            'message' => $message,
            'context' => $context ?: new \stdClass(), // {} if empty
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * Rotate log file if needed.
     */
    private static function rotate(): void
    {
        $logFile = self::getLogFile();

        if (!file_exists($logFile)) {
            return;
        }

        $size = filesize($logFile);
        if ($size === false || $size < self::MAX_FILE_SIZE) {
            return;
        }

        // Rotate: app.log.5 → delete, app.log.4 → app.log.5, etc.
        for ($i = self::MAX_FILES; $i >= 1; $i--) {
            $old = "{$logFile}.{$i}";
            if ($i === self::MAX_FILES) {
                if (file_exists($old)) {
                    unlink($old);
                }
            } else {
                if (file_exists($old)) {
                    rename($old, "{$logFile}." . ($i + 1));
                }
            }
        }

        // app.log → app.log.1
        rename($logFile, "{$logFile}.1");
    }

    /**
     * Set custom log file path (for testing).
     */
    public static function setLogFile(string $path): void
    {
        self::$logFile = $path;
    }

    /**
     * Reset to default log file (for testing).
     */
    public static function resetLogFile(): void
    {
        self::$logFile = null;
    }
}
