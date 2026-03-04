<?php declare(strict_types=1);

namespace NewsBot\Core;

/**
 * Circuit Breaker pattern for external API calls.
 * Uses dedicated table for atomic operations and race condition protection.
 *
 * States:
 * - closed: Normal operation, requests pass through
 * - open: Service failed, requests are blocked
 * - half_open: Testing if service recovered
 */
class CircuitBreaker
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    public const FAILURE_THRESHOLD = 5;      // Failures before opening
    public const RECOVERY_TIMEOUT = 300;     // Seconds before trying half_open

    /**
     * Check if a service is available.
     *
     * @param string $service Service name (e.g., 'openrouter', 'anthropic', 'telegram')
     * @return bool True if requests should be allowed
     */
    public static function isAvailable(string $service): bool
    {
        $row = Database::fetchOne(
            "SELECT state, last_failure_at FROM circuit_breaker_state WHERE service = ?",
            [$service]
        );

        // No record or closed = available
        if (!$row || $row['state'] === self::STATE_CLOSED) {
            return true;
        }

        if ($row['state'] === self::STATE_OPEN) {
            // Check if recovery timeout has passed
            if ($row['last_failure_at']) {
                $lastFailure = strtotime($row['last_failure_at']);
                if ((time() - $lastFailure) > self::RECOVERY_TIMEOUT) {
                    // Transition to half_open
                    Database::update('circuit_breaker_state', [
                        'state' => self::STATE_HALF_OPEN,
                    ], 'service = ?', [$service]);

                    Logger::info("Circuit breaker half_open for {$service}");
                    return true; // Allow one test request
                }
            }
            return false;
        }

        // half_open = allow test request
        return true;
    }

    /**
     * Record a successful request.
     * Resets failure count and closes circuit.
     */
    public static function recordSuccess(string $service): void
    {
        Database::execute(
            "INSERT INTO circuit_breaker_state (service, state, failure_count, last_success_at)
             VALUES (?, 'closed', 0, NOW())
             ON DUPLICATE KEY UPDATE
                state = 'closed',
                failure_count = 0,
                last_success_at = NOW()",
            [$service]
        );
    }

    /**
     * Record a failed request.
     * Uses atomic increment to prevent race conditions.
     *
     * IMPORTANT: Uses failure_count + 1 in IF() because UPDATE hasn't applied yet
     * when the condition is evaluated.
     */
    public static function recordFailure(string $service): void
    {
        Database::execute(
            "INSERT INTO circuit_breaker_state (service, failure_count, last_failure_at, state)
             VALUES (?, 1, NOW(), 'closed')
             ON DUPLICATE KEY UPDATE
                failure_count = failure_count + 1,
                last_failure_at = NOW(),
                state = IF(failure_count + 1 >= ?, 'open', state)",
            [$service, self::FAILURE_THRESHOLD]
        );

        // Check if breaker opened (for logging)
        $row = Database::fetchOne(
            "SELECT state, failure_count FROM circuit_breaker_state WHERE service = ?",
            [$service]
        );

        if ($row && $row['state'] === self::STATE_OPEN) {
            Logger::warning("Circuit breaker opened for {$service}", [
                'failures' => (int)$row['failure_count'],
            ]);
        }
    }

    /**
     * Get current state of a service.
     */
    public static function getState(string $service): string
    {
        $row = Database::fetchOne(
            "SELECT state FROM circuit_breaker_state WHERE service = ?",
            [$service]
        );

        return $row['state'] ?? self::STATE_CLOSED;
    }

    /**
     * Force reset a circuit breaker (admin action).
     */
    public static function reset(string $service): void
    {
        Database::execute(
            "INSERT INTO circuit_breaker_state (service, state, failure_count)
             VALUES (?, 'closed', 0)
             ON DUPLICATE KEY UPDATE
                state = 'closed',
                failure_count = 0,
                last_failure_at = NULL",
            [$service]
        );

        Logger::info("Circuit breaker reset for {$service}");
    }

    /**
     * Get status of all circuit breakers.
     */
    public static function getAllStates(): array
    {
        return Database::fetchAll(
            "SELECT service, state, failure_count, last_failure_at, last_success_at
             FROM circuit_breaker_state
             ORDER BY service"
        );
    }
}
