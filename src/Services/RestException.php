<?php declare(strict_types=1);

namespace NewsBot\Services;

/**
 * Exception for REST API publishing errors.
 */
class RestException extends \RuntimeException
{
    /**
     * @param string $message Error message
     * @param int $code HTTP status code (0 = network error)
     * @param array $retryHttpCodes HTTP codes configured for retry on this endpoint
     */
    public function __construct(
        string $message,
        int $code = 0,
        private readonly array $retryHttpCodes = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Permanent error: 4xx except 429. No retry.
     */
    public function isPermanent(): bool
    {
        $code = $this->getCode();
        if ($code === 0) {
            return false; // Network error — not permanent
        }
        return $code >= 400 && $code < 500 && $code !== 429;
    }

    /**
     * Rate limit (429). Retry after delay.
     */
    public function isRateLimit(): bool
    {
        return $this->getCode() === 429;
    }

    /**
     * Should retry based on HTTP code and endpoint retry config.
     */
    public function shouldRetry(): bool
    {
        if ($this->isPermanent()) {
            return false;
        }
        $code = $this->getCode();
        if ($code === 0) {
            return true; // Network error
        }
        if ($code === 429) {
            return true; // Rate limit
        }
        return in_array($code, $this->retryHttpCodes, true);
    }
}
