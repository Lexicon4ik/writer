<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\Logger;

/**
 * Global rate limiter for Telegram Bot API.
 * Prevents exceeding Telegram's rate limits (~30 messages/second globally).
 */
class TelegramRateLimiter
{
    /**
     * Maximum messages per second (with safety margin).
     * Telegram limit is 30/sec, we use 25 for safety.
     */
    public const MAX_PER_SECOND = 25;

    /**
     * Timestamps of recent requests (microtime float).
     * @var float[]
     */
    private static array $requestTimes = [];

    /**
     * Wait if necessary to respect rate limits.
     * Call this before every Telegram API request.
     */
    public static function wait(): void
    {
        $now = microtime(true);

        // Clean up old entries (older than 1 second)
        self::$requestTimes = array_filter(
            self::$requestTimes,
            fn(float $time) => ($now - $time) < 1.0
        );

        // Check if we're at the limit
        $count = count(self::$requestTimes);
        if ($count >= self::MAX_PER_SECOND) {
            // Calculate how long to wait
            $oldestTime = min(self::$requestTimes);
            $waitTime = 1.0 - ($now - $oldestTime);

            if ($waitTime > 0) {
                Logger::debug('Rate limit reached, waiting', [
                    'requests_in_window' => $count,
                    'wait_ms' => round($waitTime * 1000),
                ]);

                // Sleep for the calculated time plus small buffer
                usleep((int)(($waitTime + 0.05) * 1_000_000));
            }

            // Clean up again after sleeping
            $now = microtime(true);
            self::$requestTimes = array_filter(
                self::$requestTimes,
                fn(float $time) => ($now - $time) < 1.0
            );
        }

        // Record this request
        self::$requestTimes[] = microtime(true);
    }

    /**
     * Get current request count in the last second.
     */
    public static function getCurrentCount(): int
    {
        $now = microtime(true);
        return count(array_filter(
            self::$requestTimes,
            fn(float $time) => ($now - $time) < 1.0
        ));
    }

    /**
     * Reset rate limiter state (for testing).
     */
    public static function reset(): void
    {
        self::$requestTimes = [];
    }

    /**
     * Check if we can make a request without waiting.
     */
    public static function canRequest(): bool
    {
        $now = microtime(true);
        $recentCount = count(array_filter(
            self::$requestTimes,
            fn(float $time) => ($now - $time) < 1.0
        ));

        return $recentCount < self::MAX_PER_SECOND;
    }
}
