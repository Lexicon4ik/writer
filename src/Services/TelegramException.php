<?php declare(strict_types=1);

namespace NewsBot\Services;

/**
 * Exception for Telegram API errors.
 */
class TelegramException extends \Exception
{
    /**
     * Check if this is a permanent error (should not retry).
     */
    public function isPermanent(): bool
    {
        // 400 = Bad Request (wrong parameters, chat not found, message not found, etc.)
        // 403 = Forbidden (bot kicked, blocked, not admin, etc.)
        return in_array($this->code, [400, 403]);
    }

    /**
     * Check if this is a rate limit error.
     */
    public function isRateLimit(): bool
    {
        return $this->code === 429;
    }

    /**
     * Check if this is a network error.
     */
    public function isNetworkError(): bool
    {
        // cURL error codes are typically 1-99
        return $this->code > 0 && $this->code < 100;
    }

    /**
     * Get human-readable error type.
     */
    public function getErrorType(): string
    {
        return match (true) {
            $this->code === 400 => 'bad_request',
            $this->code === 403 => 'forbidden',
            $this->code === 429 => 'rate_limit',
            $this->code >= 500 => 'server_error',
            $this->isNetworkError() => 'network_error',
            default => 'unknown',
        };
    }
}
