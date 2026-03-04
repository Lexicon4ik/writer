<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\Database;
use NewsBot\Core\Logger;
use NewsBot\Core\Settings;
use NewsBot\Models\Article;
use NewsBot\Models\ArticleVersion;
use NewsBot\Models\Channel;

/**
 * AI Validator - validates quality of AI-processed articles.
 * Compares original article with translated/adapted version.
 */
class AiValidator
{
    private AiClientInterface $client;

    private const MAX_ORIGINAL_LENGTH = 2000;

    public function __construct()
    {
        $this->client = AiClient::create('validate');
    }

    /**
     * Validate AI processing quality.
     *
     * @param Article $article Original article
     * @param ArticleVersion $version Processed version
     * @param Channel $channel Target channel
     * @return array ['score' => int 1-10, 'notes' => string]
     * @throws TemporaryApiException on temporary API errors
     * @throws \RuntimeException on permanent errors
     */
    public function validate(Article $article, ArticleVersion $version, Channel $channel): array
    {
        // Check if validation should be performed
        if (!$this->shouldValidate($channel, $version)) {
            Logger::debug('Validation skipped', [
                'article_id' => $article->id,
                'channel_id' => $channel->id,
                'mode' => $channel->validation_mode,
            ]);
            return [
                'score' => 10,
                'notes' => 'Validation skipped per channel settings',
            ];
        }

        // Set context for error logging
        if (method_exists($this->client, 'setContext')) {
            $this->client->setContext((int)$article->id, (int)$channel->id, 'validate');
        }

        // Build validation prompt
        $prompt = $this->buildValidationPrompt($article, $version, $channel);
        $systemPrompt = $this->getSystemPrompt();

        // Get model and temperature
        $model = AiClient::getModel('validate');
        $temperature = Settings::getFloat('temperature_validate', 0.3);

        Logger::debug('Validating article', [
            'article_id' => $article->id,
            'version_id' => $version->id,
            'channel_id' => $channel->id,
            'model' => $model,
        ]);

        // Call AI
        $response = $this->client->message($systemPrompt, $prompt, $model, $temperature, 512);

        // Log usage
        $this->logUsage((int)$channel->id, (int)$article->id, $response);

        // Parse response
        $parsed = OpenRouterClient::parseJson($response['content']);

        if ($parsed === null) {
            Logger::warning('Failed to parse validation response', [
                'article_id' => $article->id,
                'version_id' => $version->id,
                'content_length' => strlen($response['content']),
                'content_bytes' => bin2hex(substr($response['content'], 0, 50)),
                'response_preview' => mb_substr($response['content'], 0, 500),
                'finish_reason' => $response['finish_reason'] ?? 'unknown',
            ]);
            // Return moderate score on parse failure
            return [
                'score' => 6,
                'notes' => 'Validation response parsing failed',
            ];
        }

        $score = $this->clampScore((int)($parsed['score'] ?? 6));
        $notes = mb_substr($parsed['notes'] ?? '', 0, 1000);

        Logger::info('Validation completed', [
            'article_id' => $article->id,
            'version_id' => $version->id,
            'score' => $score,
            'notes_preview' => mb_substr($notes, 0, 100),
        ]);

        return [
            'score' => $score,
            'notes' => $notes,
        ];
    }

    /**
     * Check if validation should be performed.
     */
    private function shouldValidate(Channel $channel, ArticleVersion $version): bool
    {
        $mode = $channel->validation_mode ?? 'sample';

        switch ($mode) {
            case 'never':
                return false;

            case 'always':
                return true;

            case 'sample':
                $samplePct = (int)($channel->validation_sample_pct ?? 20);
                return rand(1, 100) <= $samplePct;

            case 'importance_threshold':
                $minImportance = (int)($channel->validation_importance_min ?? 7);
                return ($version->importance_score ?? 0) >= $minImportance;

            default:
                return true;
        }
    }

    /**
     * Build validation prompt.
     */
    private function buildValidationPrompt(Article $article, ArticleVersion $version, Channel $channel): string
    {
        $originalTitle = $article->getTitle();
        $originalText = mb_substr($article->getText(), 0, self::MAX_ORIGINAL_LENGTH);
        $originalLang = $article->original_language ?? 'en';
        $targetLang = $channel->language ?? 'ru';

        // Use custom validation prompt if available
        if (!empty($channel->validation_prompt)) {
            $prompt = $channel->validation_prompt;
            $prompt = str_replace([
                '{{original_title}}',
                '{{original_text}}',
                '{{translated_title}}',
                '{{translated_text}}',
                '{{source_language}}',
                '{{target_language}}',
            ], [
                $originalTitle,
                $originalText,
                $version->title,
                $version->body,
                $originalLang,
                $targetLang,
            ], $prompt);
            return $prompt;
        }

        // Default validation prompt
        return <<<PROMPT
Compare the original article with its translation and rate the quality.

ORIGINAL ({$originalLang}):
{$originalTitle}
{$originalText}

TRANSLATION ({$targetLang}):
{$version->title}
{$version->body}

SCORING:
1 = garbage/unrelated
2-3 = major errors, facts lost
4-5 = acceptable, some inaccuracies
6-7 = good quality
8-10 = excellent

RESPOND WITH ONLY THIS JSON FORMAT:
{"score": 7, "notes": "Good translation, minor style issues"}
PROMPT;
    }

    /**
     * Get system prompt for validation.
     */
    private function getSystemPrompt(): string
    {
        return "You are a translation quality evaluator. " .
            "Output ONLY a JSON object with 'score' (1-10) and 'notes' (brief comment). " .
            "No markdown, no explanation, just raw JSON.";
    }

    /**
     * Log AI usage to database.
     */
    private function logUsage(int $channelId, int $articleId, array $response): void
    {
        $estimatedCost = $this->calculateCost($response);

        try {
            Database::insert('ai_usage_log', [
                'channel_id' => $channelId,
                'article_id' => $articleId,
                'operation' => 'validate',
                'provider' => $response['provider'] ?? 'openrouter',
                'model' => $response['model'],
                'input_tokens' => $response['input_tokens'],
                'output_tokens' => $response['output_tokens'],
                'estimated_cost' => $estimatedCost,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Failed to log validation usage', [
                'error' => $e->getMessage(),
                'channel_id' => $channelId,
                'article_id' => $articleId,
            ]);
        }
    }

    /**
     * Calculate estimated cost.
     */
    private function calculateCost(array $response): float
    {
        // Use total_cost from API if available
        if (isset($response['total_cost']) && $response['total_cost'] !== null) {
            return (float)$response['total_cost'];
        }

        // Haiku pricing (default for validation)
        $inputPrice = Settings::getFloat('ai_input_price_haiku', 0.25);
        $outputPrice = Settings::getFloat('ai_output_price_haiku', 1.25);

        $inputTokens = $response['input_tokens'] ?? 0;
        $outputTokens = $response['output_tokens'] ?? 0;

        $cost = ($inputTokens / 1_000_000) * $inputPrice + ($outputTokens / 1_000_000) * $outputPrice;

        // Add 20% markup for OpenRouter
        if (($response['provider'] ?? 'openrouter') === 'openrouter') {
            $cost *= 1.2;
        }

        return round($cost, 6);
    }

    /**
     * Clamp score to 1-10 range.
     */
    private function clampScore(int $score): int
    {
        return max(1, min(10, $score));
    }
}
