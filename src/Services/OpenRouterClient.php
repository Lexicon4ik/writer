<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\CircuitBreaker;
use NewsBot\Core\Crypto;
use NewsBot\Core\Database;
use NewsBot\Core\Logger;
use NewsBot\Core\Settings;

/**
 * OpenRouter API client (OpenAI-compatible endpoint).
 * Primary AI provider with CircuitBreaker, retry logic, and error logging.
 */
class OpenRouterClient implements AiClientInterface
{
    public const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
    public const GENERATION_ENDPOINT = 'https://openrouter.ai/api/v1/generation';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAYS = [1, 3, 9]; // Exponential backoff in seconds

    private string $apiKey;
    private string $providerName = 'openrouter';
    private ?string $currentModel = null;
    private ?int $contextArticleId = null;
    private ?int $contextChannelId = null;
    private string $contextOperation = 'process';

    public function __construct()
    {
        $raw = Settings::get('openrouter_api_key');
        if (empty($raw)) {
            throw new \RuntimeException('OpenRouter API key not configured');
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
     * Send message to OpenRouter API with retry and CircuitBreaker.
     */
    public function message(
        string $systemPrompt,
        string $userMessage,
        string $model,
        float $temperature = 0.7,
        int $maxTokens = 4096
    ): array {
        $this->currentModel = $model;

        // Check CircuitBreaker
        if (!CircuitBreaker::isAvailable('openrouter')) {
            $this->logError('circuit_breaker', null, 'Circuit breaker is open', 1);
            throw new TemporaryApiException('OpenRouter circuit breaker is open', 60);
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://writer.lexik.online',
            'X-Title: NewsBot Writer',
        ];

        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $result = $this->doRequest($payload, $headers, $attempt);
                CircuitBreaker::recordSuccess('openrouter');
                return $result;
            } catch (TemporaryApiException $e) {
                $lastException = $e;
                CircuitBreaker::recordFailure('openrouter');

                // Check if we should use fallback model
                if ($attempt === 1 && Settings::get('model_fallback_enabled') === '1') {
                    $fallbackModel = AiClient::getModel($this->contextOperation, true);
                    if ($fallbackModel && $fallbackModel !== $model) {
                        Logger::info('Switching to fallback model', [
                            'from' => $model,
                            'to' => $fallbackModel,
                            'reason' => $e->getMessage(),
                        ]);
                        $payload['model'] = $fallbackModel;
                        $this->currentModel = $fallbackModel;
                        // Don't sleep for fallback, just retry immediately
                        continue;
                    }
                }

                // Wait before retry (exponential backoff)
                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAYS[$attempt - 1] ?? 9;
                    Logger::warning('OpenRouter retry', [
                        'attempt' => $attempt,
                        'delay' => $delay,
                        'error' => $e->getMessage(),
                    ]);
                    sleep($delay);
                }
            } catch (\RuntimeException $e) {
                // Permanent error, don't retry
                CircuitBreaker::recordFailure('openrouter');
                throw $e;
            }
        }

        // All retries exhausted
        throw $lastException ?? new TemporaryApiException('OpenRouter request failed after retries', 60);
    }

    /**
     * Execute single HTTP request to OpenRouter.
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
            $retryAfter = $this->parseRetryAfter($response);
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
            throw new \RuntimeException('OpenRouter authentication failed: ' . $httpCode);
        }

        if ($httpCode === 400) {
            $errorMsg = $data['error']['message'] ?? $response;
            $this->logError('bad_request', $httpCode, $errorMsg, $attempt);
            throw new \RuntimeException('OpenRouter bad request: ' . $errorMsg);
        }

        if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
            $errorMsg = $data['error']['message'] ?? $response;
            $this->logError('parse_error', $httpCode, 'Invalid response: ' . $errorMsg, $attempt);
            throw new \RuntimeException('OpenRouter API error: ' . $errorMsg);
        }

        // Check for truncated response
        $finishReason = $data['choices'][0]['finish_reason'] ?? 'stop';
        if ($finishReason === 'length') {
            Logger::warning('AI response truncated due to max_tokens', [
                'model' => $data['model'] ?? $payload['model'],
                'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'article_id' => $this->contextArticleId,
            ]);
        }

        $generationId = $data['id'] ?? null;

        return [
            'content' => $data['choices'][0]['message']['content'],
            'input_tokens' => (int)($data['usage']['prompt_tokens'] ?? 0),
            'output_tokens' => (int)($data['usage']['completion_tokens'] ?? 0),
            'model' => $data['model'] ?? $payload['model'],
            'provider' => $this->providerName,
            'total_cost' => $this->fetchGenerationCost($generationId),
            'finish_reason' => $finishReason,
        ];
    }

    /**
     * Fetch exact cost from OpenRouter Generation API.
     * Falls back to null (manual calculation used instead).
     */
    private function fetchGenerationCost(?string $generationId): ?float
    {
        if ($generationId === null) {
            return null;
        }

        $ch = curl_init(self::GENERATION_ENDPOINT . '?id=' . urlencode($generationId));
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        $cost = $data['data']['total_cost'] ?? null;

        return $cost !== null ? (float)$cost : null;
    }

    /**
     * Parse retry-after from response.
     */
    private function parseRetryAfter(string $response): int
    {
        $data = json_decode($response, true);
        if (isset($data['error']['metadata']['retry_after'])) {
            return (int)$data['error']['metadata']['retry_after'];
        }
        return 60;
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

        Logger::error('OpenRouter API error', [
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

        // Extract content from markdown code block (even incomplete)
        $json = $response;
        if (preg_match('/```(?:json)?\s*\n?([\s\S]*?)(?:\n?```|$)/', $response, $matches)) {
            $json = trim($matches[1]);
        }

        // Try direct parse of extracted content
        $data = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Try to find JSON object start
        $jsonStart = strpos($json, '{');
        if ($jsonStart !== false) {
            $json = substr($json, $jsonStart);
        }

        // Try parse again
        $data = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Try to repair truncated JSON
        $repaired = self::repairTruncatedJson($json);
        if ($repaired !== null) {
            $data = json_decode($repaired, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                Logger::debug('Repaired truncated JSON successfully');
                return $data;
            }
        }

        return null;
    }

    /**
     * Attempt to repair truncated JSON by completing missing parts.
     */
    private static function repairTruncatedJson(string $json): ?string
    {
        // Count braces and brackets
        $openBraces = substr_count($json, '{');
        $closeBraces = substr_count($json, '}');
        $openBrackets = substr_count($json, '[');
        $closeBrackets = substr_count($json, ']');

        // If balanced, not truncated in structure
        if ($openBraces === $closeBraces && $openBrackets === $closeBrackets) {
            return null;
        }

        // Check if we're inside an unclosed string (odd number of unescaped quotes)
        $inString = false;
        $escaped = false;
        for ($i = 0; $i < strlen($json); $i++) {
            $char = $json[$i];
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char === '"') {
                $inString = !$inString;
            }
        }

        // If inside string, close it
        if ($inString) {
            $json .= '"';
        }

        // Remove trailing incomplete parts (like "key": "val or "key": [)
        $json = preg_replace('/,\s*"[^"]*"?\s*:\s*("[^"]*)?$/', '', $json);
        $json = preg_replace('/,\s*"[^"]*"?\s*:\s*\[?\s*$/', '', $json);
        $json = preg_replace('/,\s*$/', '', $json);

        // Add missing closing brackets and braces
        $missingBrackets = $openBrackets - $closeBrackets;
        $missingBraces = $openBraces - $closeBraces;

        if ($missingBrackets > 0) {
            $json .= str_repeat(']', $missingBrackets);
        }
        if ($missingBraces > 0) {
            $json .= str_repeat('}', $missingBraces);
        }

        return $json;
    }
}
