<?php declare(strict_types=1);

namespace NewsBot\Services;

/**
 * Exception for temporary API errors (429 rate limit, 5xx server errors).
 * Contains retry-after hint.
 */
class TemporaryApiException extends \RuntimeException
{
    private int $retryAfter;

    public function __construct(string $message, int $retryAfter = 60, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->retryAfter = $retryAfter;
    }

    /**
     * Get recommended retry delay in seconds.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
