<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\CircuitBreaker;
use NewsBot\Core\Crypto;
use NewsBot\Core\Database;
use NewsBot\Core\Logger;
use NewsBot\Core\Settings;

/**
 * Google Gemini API client.
 * Native integration for Gemini models (like gemini-2.5-flash).
 */
class GeminiClient implements AiClientInterface
{
    // The base endpoint URL pattern. Note: we append the model and the key in the request
    public const ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const MAX_RETRIES = 3;
    private const RETRY_DELAYS = [1, 3, 9];

    private string $apiKey;
    private string $providerName = 'gemini';
    private ?string $currentModel = null;
    private ?int $contextArticleId = null;
    private ?int $contextChannelId = null;
    private string $contextOperation = 'process';

    public function __construct()
    {
        $raw = Settings::get('gemini_api_key');
        if (empty($raw)) {
            throw new \RuntimeException('Gemini API key not configured');
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
     * Send message to Gemini API with retry logic.
     */
    public function message(
        string $systemPrompt,
        string $userMessage,
        string $model,
        float $temperature = 0.7,
        int $maxTokens = 4096
    ): array {
        // Normalize model name (remove google/ prefix if exists)
        $model = $this->normalizeModel($model);
        $this->currentModel = $model;

        // Check CircuitBreaker
        if (!CircuitBreaker::isAvailable('gemini')) {
            $this->logError('circuit_breaker', null, 'Circuit breaker is open', 1);
            throw new TemporaryApiException('Gemini circuit breaker is open', 60);
        }

        // Format Gemini Request Payload
        // https://ai.google.dev/api/rest/v1beta/models/generateContent
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userMessage]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        $headers = [
            'Content-Type: application/json',
        ];

        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $result = $this->doRequest($payload, $headers, $attempt);
                CircuitBreaker::recordSuccess('gemini');
                return $result;
            } catch (TemporaryApiException $e) {
                $lastException = $e;
                CircuitBreaker::recordFailure('gemini');

                // Wait before retry (exponential backoff)
                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAYS[$attempt - 1] ?? 9;
                    Logger::warning('Gemini retry', [
                        'attempt' => $attempt,
                        'delay' => $delay,
                        'error' => $e->getMessage(),
                    ]);
                    sleep($delay);
                }
            } catch (\RuntimeException $e) {
                // Permanent error, don't retry
                CircuitBreaker::recordFailure('gemini');
                throw $e;
            }
        }

        throw $lastException ?? new TemporaryApiException('Gemini request failed after retries', 60);
    }

    /**
     * Execute single HTTP request to Gemini.
     */
    private function doRequest(array $payload, array $headers, int $attempt): array
    {
        $url = self::ENDPOINT_BASE . $this->currentModel . ':generateContent?key=' . $this->apiKey;

        $ch = curl_init($url);
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
            $this->logError('rate_limit', $httpCode, 'Rate limit exceeded', $attempt);
            throw new TemporaryApiException('Rate limit exceeded', 60);
        }

        if ($httpCode >= 500) {
            $errorMsg = $data['error']['message'] ?? 'Server error ' . $httpCode;
            $this->logError('server_error', $httpCode, $errorMsg, $attempt);
            throw new TemporaryApiException('Server error: ' . $httpCode, 60);
        }

        if ($httpCode === 401 || $httpCode === 403) {
            $this->logError('auth', $httpCode, 'Authentication failed', $attempt);
            throw new \RuntimeException('Gemini authentication failed: ' . $httpCode);
        }

        if ($httpCode === 400) {
            $errorMsg = $data['error']['message'] ?? $response;
            $this->logError('bad_request', $httpCode, $errorMsg, $attempt);
            throw new \RuntimeException('Gemini bad request: ' . $errorMsg);
        }

        if ($httpCode !== 200 || empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            $errorMsg = $data['error']['message'] ?? $response;
            $this->logError('parse_error', $httpCode, 'Invalid response: ' . $errorMsg, $attempt);
            throw new \RuntimeException('Gemini API error: ' . $errorMsg);
        }

        $finishReason = $data['candidates'][0]['finishReason'] ?? 'STOP';
        if ($finishReason === 'MAX_TOKENS') {
            Logger::warning('AI response truncated due to maxOutputTokens', [
                'model' => $this->currentModel,
                'article_id' => $this->contextArticleId,
            ]);
        }

        return [
            'content' => $data['candidates'][0]['content']['parts'][0]['text'],
            'input_tokens' => (int)($data['usageMetadata']['promptTokenCount'] ?? 0),
            'output_tokens' => (int)($data['usageMetadata']['candidatesTokenCount'] ?? 0),
            'model' => $this->currentModel,
            'provider' => $this->providerName,
            'total_cost' => null, // Gemini API doesn't return cost in the response currently
        ];
    }

    /**
     * Normalize model name - remove google/ prefix.
     */
    private function normalizeModel(string $model): string
    {
        return preg_replace('/^google\//', '', $model);
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

        Logger::error('Gemini API error', [
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
        return OpenRouterClient::parseJson($response);
    }
}
