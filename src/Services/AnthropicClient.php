<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\CircuitBreaker;
use NewsBot\Core\Crypto;
use NewsBot\Core\Database;
use NewsBot\Core\Logger;
use NewsBot\Core\Settings;

/**
 * Anthropic API client (fallback provider).
 * Direct API access when OpenRouter is unavailable.
 */
class AnthropicClient implements AiClientInterface
{
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    public const API_VERSION = '2024-10-22';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAYS = [1, 3, 9];

    private string $apiKey;
    private string $providerName = 'anthropic';
    private ?string $currentModel = null;
    private ?int $contextArticleId = null;
    private ?int $contextChannelId = null;
    private string $contextOperation = 'process';

    public function __construct()
    {
        $raw = Settings::get('anthropic_api_key');
        if (empty($raw)) {
            throw new \RuntimeException('Anthropic API key not configured');
        }
        $this->apiKey = Crypto::decryptSafe($raw);
    }

    /**
     * Set context for error logging.
     */
    public function setContext(?int $articleId, ?int $channelId, string $operation = 'process'): self
    {
        $this->contextArticleId = $articleId;
        $this->contextChannelId = $channelId;
        $this->contextOperation = $operation;
        return $this;
    }

    /**
     * Send message to Anthropic API with retry logic.
     */
    public function message(
        string $systemPrompt,
        string $userMessage,
        string $model,
        float $temperature = 0.7,
        int $maxTokens = 4096
    ): array {
        // Normalize model name (remove anthropic/ prefix)
        $model = $this->normalizeModel($model);
        $this->currentModel = $model;

        // Check CircuitBreaker
        if (!CircuitBreaker::isAvailable('anthropic')) {
            $this->logError('circuit_breaker', null, 'Circuit breaker is open', 1);
            throw new TemporaryApiException('Anthropic circuit breaker is open', 60);
        }

        $payload = [
            'model' => $model,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        $headers = [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: ' . self::API_VERSION,
            'Content-Type: application/json',
        ];

        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $result = $this->doRequest($payload, $headers, $attempt);
                CircuitBreaker::recordSuccess('anthropic');
                return $result;
            } catch (TemporaryApiException $e) {
                $lastException = $e;
                CircuitBreaker::recordFailure('anthropic');

                // Wait before retry (exponential backoff)
                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAYS[$attempt - 1] ?? 9;
                    Logger::warning('Anthropic retry', [
                        'attempt' => $attempt,
                        'delay' => $delay,
                        'error' => $e->getMessage(),
                    ]);
                    sleep($delay);
                }
            } catch (\RuntimeException $e) {
                // Permanent error, don't retry
                CircuitBreaker::recordFailure('anthropic');
                throw $e;
            }
        }

        throw $lastException ?? new TemporaryApiException('Anthropic request failed after retries', 60);
    }

    /**
     * Execute single HTTP request to Anthropic.
     */
    private function doRequest(array $payload, array $headers, int $attempt): array
    {
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->logError('timeout', null, 'cURL error: ' . $error, $attempt);
            throw new TemporaryApiException('cURL error: ' . $error, 60);
        }

        $data = json_decode($response, true);

        // Handle errors based on HTTP code
        if ($httpCode === 429) {
            $retryAfter = 60;
            if (isset($data['error']['message']) && preg_match('/(\d+)\s*seconds?/i', $data['error']['message'], $m)) {
                $retryAfter = (int)$m[1];
            }
            $this->logError('rate_limit', $httpCode, 'Rate limit exceeded', $attempt);
            throw new TemporaryApiException('Rate limit exceeded', $retryAfter);
        }

        if ($httpCode >= 500) {
            $errorMsg = $data['error']['message'] ?? 'Server error ' . $httpCode;
            $this->logError('server_error', $httpCode, $errorMsg, $attempt);
            throw new TemporaryApiException('Server error: ' . $httpCode, 60);
        }

        if ($httpCode === 401 || $httpCode === 403) {
            $this->logError('auth', $httpCode, 'Authentication failed', $attempt);
            throw new \RuntimeException('Anthropic authentication failed: ' . $httpCode);
        }

        if ($httpCode === 400) {
            $errorMsg = $data['error']['message'] ?? $response;
            $this->logError('bad_request', $httpCode, $errorMsg, $attempt);
            throw new \RuntimeException('Anthropic bad request: ' . $errorMsg);
        }

        if ($httpCode !== 200 || !isset($data['content'][0]['text'])) {
            $errorMsg = $data['error']['message'] ?? $response;
            $this->logError('parse_error', $httpCode, 'Invalid response: ' . $errorMsg, $attempt);
            throw new \RuntimeException('Anthropic API error: ' . $errorMsg);
        }

        return [
            'content' => $data['content'][0]['text'],
            'input_tokens' => (int)($data['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int)($data['usage']['output_tokens'] ?? 0),
            'model' => $payload['model'],
            'provider' => $this->providerName,
            'total_cost' => null, // Anthropic direct API doesn't return cost
        ];
    }

    /**
     * Normalize model name - remove anthropic/ prefix.
     */
    private function normalizeModel(string $model): string
    {
        return preg_replace('/^anthropic\//', '', $model);
    }

    /**
     * Log error to ai_errors table.
     */
    private function logError(string $errorType, ?int $httpCode, string $message, int $retryAttempt): void
    {
        try {
            Database::insert('ai_errors', [
                'article_id' => $this->contextArticleId,
                'channel_id' => $this->contextChannelId,
                'operation' => $this->contextOperation,
                'provider' => $this->providerName,
                'model' => $this->currentModel,
                'error_type' => $errorType,
                'http_code' => $httpCode,
                'error_message' => mb_substr($message, 0, 5000),
                'retry_attempt' => $retryAttempt,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Failed to log AI error', [
                'error' => $e->getMessage(),
                'original_error' => $message,
            ]);
        }

        Logger::error('Anthropic API error', [
            'error_type' => $errorType,
            'http_code' => $httpCode,
            'model' => $this->currentModel,
            'article_id' => $this->contextArticleId,
            'channel_id' => $this->contextChannelId,
            'attempt' => $retryAttempt,
            'message' => mb_substr($message, 0, 500),
        ]);
    }

    /**
     * Parse JSON from AI response (may be wrapped in markdown code blocks).
     */
    public static function parseJson(string $response): ?array
    {
        // Try direct parse first
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Try to extract from markdown code block
        if (preg_match('/```(?:json)?\s*\n?([\s\S]*?)\n?```/', $response, $matches)) {
            $data = json_decode(trim($matches[1]), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }

        // Try to find JSON object in text (more flexible regex)
        if (preg_match('/\{(?:[^{}]|(?:\{[^{}]*\}))*\}/s', $response, $matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }

        return null;
    }
}
