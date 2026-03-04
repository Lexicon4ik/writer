<?php declare(strict_types=1);

namespace NewsBot\Services;

/**
 * Interface for AI API clients.
 */
interface AiClientInterface
{
    /**
     * Send message to AI API.
     *
     * @param string $systemPrompt System prompt
     * @param string $userMessage User message
     * @param string $model Model identifier
     * @param float $temperature Temperature (0.0 - 2.0)
     * @param int $maxTokens Maximum output tokens
     * @return array {
     *   content: string,
     *   input_tokens: int,
     *   output_tokens: int,
     *   model: string,
     *   provider: string,
     *   total_cost: ?float (USD, from API response if available)
     * }
     * @throws \RuntimeException on permanent errors (400, 401)
     * @throws TemporaryApiException on temporary errors (429, 5xx)
     */
    public function message(
        string $systemPrompt,
        string $userMessage,
        string $model,
        float $temperature = 0.7,
        int $maxTokens = 4096
    ): array;
}
