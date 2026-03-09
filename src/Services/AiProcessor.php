<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\Database;
use NewsBot\Core\Logger;
use NewsBot\Core\Settings;
use NewsBot\Models\Article;
use NewsBot\Models\ArticleVersion;
use NewsBot\Models\Channel;
use NewsBot\Models\Source;

/**
 * AI Processor - handles AI-based article processing.
 * Transforms scraped articles into channel-specific versions.
 */
class AiProcessor
{
    private AiClientInterface $client;

    // Model pricing (USD per million tokens) - defaults if not in settings
    private const DEFAULT_PRICES = [
        'claude-sonnet' => ['input' => 3.00, 'output' => 15.00],
        'claude-haiku' => ['input' => 0.25, 'output' => 1.25],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-5-nano' => ['input' => 0.15, 'output' => 0.60],
        'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
        'gemini-flash' => ['input' => 0.10, 'output' => 0.40],
        'llama-3.1-8b' => ['input' => 0.05, 'output' => 0.05],
        'llama-3' => ['input' => 0.05, 'output' => 0.05],
        'default' => ['input' => 0.50, 'output' => 1.50],
    ];

    private const MAX_TEXT_LENGTH = 100000;
    private const CHUNK_SIZE = 25000;

    public function __construct()
    {
        $this->client = AiClient::create('process');
    }

    /**
     * Process article for a specific channel.
     *
     * @param Article $article Article to process
     * @param Channel $channel Target channel
     * @param int $attempt Attempt number (1, 2, 3) - affects temperature
     * @return ArticleVersion|null Null if article should be skipped
     * @throws TemporaryApiException on temporary API errors
     * @throws \RuntimeException on permanent errors
     */
    public function process(Article $article, Channel $channel, int $attempt = 1): ?ArticleVersion
    {
        // Set context for error logging
        if (method_exists($this->client, 'setContext')) {
            $this->client->setContext((int)$article->id, (int)$channel->id, 'process');
        }

        // Prepare text (with chunking if needed)
        $text = $this->prepareText($article, $channel);

        // Get source for context
        $source = $article->getSource() ?? new Source(['name' => 'Unknown', 'site_url' => '']);

        // Build prompt
        $prompt = $this->buildPrompt(
            $channel->ai_prompt ?? '',
            $article,
            $channel,
            $source,
            $text
        );

        // Add JSON reminder for retries
        if ($attempt > 1) {
            $prompt .= "\n\nIMPORTANT: Respond with valid JSON only. No markdown formatting.";
        }

        // Add JSON structure instruction
        $prompt .= $this->getJsonInstruction();

        // Calculate temperature (base + attempt bonus, max 0.8)
        $baseTemp = $channel->ai_temperature ?? Settings::getFloat('temperature_process', 0.4);
        $temperature = min($baseTemp + ($attempt - 1) * 0.1, 0.8);

        // Get model
        $model = $channel->ai_model ?? AiClient::getModel('process');

        Logger::debug('Processing article', [
            'article_id' => $article->id,
            'channel_id' => $channel->id,
            'attempt' => $attempt,
            'temperature' => $temperature,
            'model' => $model,
        ]);

        // Call AI
        $systemPrompt = $this->getSystemPrompt($channel);
        $response = $this->client->message($systemPrompt, $prompt, $model, $temperature, 4096);

        // Log usage
        $this->logUsage((int)$channel->id, (int)$article->id, 'process', $response);

        // Parse JSON response
        $parsed = OpenRouterClient::parseJson($response['content']);

        if ($parsed === null) {
            Logger::warning('Failed to parse AI response as JSON', [
                'article_id' => $article->id,
                'channel_id' => $channel->id,
                'response_preview' => mb_substr($response['content'], 0, 500),
            ]);
            throw new \RuntimeException('Invalid JSON response from AI');
        }

        // Check if AI decided to skip
        if (!empty($parsed['skip'])) {
            Logger::info('Article skipped by AI', [
                'article_id' => $article->id,
                'channel_id' => $channel->id,
                'reason' => $parsed['skip_reason'] ?? 'No reason provided',
            ]);
            return null;
        }

        // Validate required fields
        if (empty($parsed['title']) || empty($parsed['body'])) {
            Logger::warning('AI response missing required fields', [
                'article_id' => $article->id,
                'channel_id' => $channel->id,
                'has_title' => !empty($parsed['title']),
                'has_body' => !empty($parsed['body']),
            ]);
            throw new \RuntimeException('AI response missing required fields (title, body)');
        }

        // Create ArticleVersion (race-condition safe with INSERT IGNORE)
        $result = ArticleVersion::findOrCreate([
            'article_id' => $article->id,
            'channel_id' => $channel->id,
            'title' => mb_substr($parsed['title'], 0, 200),
            'short_title' => mb_substr($parsed['short_title'] ?? '', 0, 80) ?: null,
            'description' => mb_substr($parsed['description'] ?? '', 0, 300) ?: null,
            'body' => $parsed['body'],
            'hashtags' => !empty($parsed['hashtags']) ? json_encode($parsed['hashtags'], JSON_UNESCAPED_UNICODE) : null,
            'filter_tags' => !empty($parsed['filter_tags']) ? json_encode($parsed['filter_tags'], JSON_UNESCAPED_UNICODE) : null,
            'importance_score' => $this->clampScore((int)($parsed['importance_score'] ?? 5)),
            'status' => 'pending',
            'prompt_version' => $channel->getPromptVersion(),
        ]);

        $version = $result['version'];

        // Save image_meta if present (fill even on re-processing when it was NULL)
        if (!empty($parsed['image_meta']) && is_array($parsed['image_meta'])) {
            $imageMeta = json_encode($parsed['image_meta'], JSON_UNESCAPED_UNICODE);
            Database::execute(
                "UPDATE article_versions SET image_meta = ? WHERE id = ? AND image_meta IS NULL",
                [$imageMeta, $version->id]
            );
            // Keep in-memory version consistent
            $version->image_meta = $imageMeta;
        }

        if ($result['created']) {
            Logger::info('Article processed successfully', [
                'article_id' => $article->id,
                'channel_id' => $channel->id,
                'version_id' => $version->id,
                'importance_score' => $parsed['importance_score'] ?? 5,
            ]);
        } else {
            Logger::warning('Version already exists (race condition resolved)', [
                'article_id' => $article->id,
                'channel_id' => $channel->id,
                'version_id' => $version->id,
            ]);
        }

        return $version;
    }

    /**
     * Prepare text for processing, with chunking if too large.
     */
    private function prepareText(Article $article, Channel $channel): string
    {
        $text = $article->getText();

        // Check if chunking is needed
        if (mb_strlen($text) <= self::MAX_TEXT_LENGTH) {
            return $text;
        }

        Logger::info('Text too large, performing chunked summarization', [
            'article_id' => $article->id,
            'original_length' => mb_strlen($text),
        ]);

        // Split into chunks by paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $chunks = [];
        $currentChunk = '';

        foreach ($paragraphs as $para) {
            if (mb_strlen($currentChunk . "\n\n" . $para) > self::CHUNK_SIZE && $currentChunk !== '') {
                $chunks[] = $currentChunk;
                $currentChunk = $para;
            } else {
                $currentChunk .= ($currentChunk !== '' ? "\n\n" : '') . $para;
            }
        }
        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        Logger::debug('Split text into chunks', [
            'article_id' => $article->id,
            'chunk_count' => count($chunks),
        ]);

        // Summarize each chunk
        $summaries = [];
        $chunkSystemPrompt = "You are a news summarizer. Summarize the following text fragment in 2-3 paragraphs, preserving key facts, figures, and quotes.";

        foreach ($chunks as $i => $chunk) {
            if (method_exists($this->client, 'setContext')) {
                $this->client->setContext((int)$article->id, (int)$channel->id, 'process_chunk');
            }

            $response = $this->client->message(
                $chunkSystemPrompt,
                "Summarize this fragment:\n\n" . $chunk,
                AiClient::getModel('process'),
                0.3,
                1024
            );

            $this->logUsage((int)$channel->id, (int)$article->id, 'process_chunk', $response);

            $summaries[] = $response['content'];

            Logger::debug('Chunk summarized', [
                'article_id' => $article->id,
                'chunk' => $i + 1,
                'chunk_length' => mb_strlen($chunk),
                'summary_length' => mb_strlen($response['content']),
            ]);
        }

        return implode("\n\n", $summaries);
    }

    /**
     * Build prompt with placeholder substitution.
     */
    private function buildPrompt(string $template, Article $article, Channel $channel, Source $source, string $text): string
    {
        $title = $article->getTitle();

        $replacements = [
            '{{article_title}}' => $title,
            '{{article_text}}' => $text,
            '{{article_url}}' => $article->url,
            '{{source_name}}' => $source->name ?? 'Unknown',
            '{{source_language}}' => $article->original_language ?? 'en',
            '{{channel_language}}' => $channel->language ?? 'ru',
            '{{channel_topic}}' => $channel->topic ?? '',
        ];

        $prompt = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        // If template is empty, use default
        if (trim($prompt) === '' || $prompt === $template) {
            $prompt = "Process the following article for publication.\n\n";
            $prompt .= "ARTICLE TITLE: {$title}\n\n";
            $prompt .= "ARTICLE TEXT:\n{$text}\n\n";
            $prompt .= "SOURCE: {$source->name}\n";
            $prompt .= "ORIGINAL LANGUAGE: " . ($article->original_language ?? 'en') . "\n";
            $prompt .= "TARGET LANGUAGE: " . ($channel->language ?? 'ru') . "\n";
            if ($channel->topic) {
                $prompt .= "CHANNEL TOPIC: {$channel->topic}\n";
            }
        }

        return $prompt;
    }

    /**
     * Get system prompt for processing.
     */
    private function getSystemPrompt(Channel $channel): string
    {
        $lang = $channel->language ?? 'ru';
        $topic = $channel->topic ?? 'news';

        return "You are a professional news editor preparing content for a {$lang} language Telegram channel about {$topic}. " .
            "Your task is to process, translate (if needed), and adapt news articles while preserving accuracy and key facts. " .
            "Always respond with a valid JSON object.";
    }

    /**
     * Get JSON structure instruction.
     */
    private function getJsonInstruction(): string
    {
        return <<<'JSON'


Return the result strictly in JSON format (no markdown wrapping).

IMPORTANT: If the article matches ANY rule from the SKIP ENTIRELY section in the prompt above, you MUST return:
{
  "skip": true,
  "skip_reason": "brief explanation why this article should be skipped"
}

Do NOT process skipped articles. Do NOT assign a low importance_score as a substitute for skip.

For articles that should be published, return:
{
  "title": "headline, max 200 characters",
  "short_title": "short headline, max 80 characters",
  "description": "brief description, max 300 characters",
  "body": "main post text with formatting",
  "hashtags": ["tag1", "tag2"],
  "filter_tags": ["category1", "category2"],
  "importance_score": number from 1 to 10,
  "skip": false,
  "skip_reason": "",
  "image_meta": {
    "event_title": "short English event name (e.g. 'Bangkok Protests 2026')",
    "main_entity": "main subject (person, place, organization)",
    "entity_type": "person|place|event|object|abstract",
    "category": "politics|economics|technology|science|sports|culture|disaster|crime|other",
    "scene_type": "portrait|group|outdoor_crowd|indoor|aerial|object|abstract",
    "emotion": "neutral|positive|tense|dramatic|celebratory",
    "image_queries": ["English search query 1", "English search query 2"]
  }
}
JSON;
    }

    /**
     * Log AI usage to database.
     */
    private function logUsage(int $channelId, int $articleId, string $operation, array $response): void
    {
        $estimatedCost = $this->calculateCost($response);

        try {
            Database::insert('ai_usage_log', [
                'channel_id' => $channelId,
                'article_id' => $articleId,
                'operation' => $operation,
                'provider' => $response['provider'] ?? 'openrouter',
                'model' => $response['model'],
                'input_tokens' => $response['input_tokens'],
                'output_tokens' => $response['output_tokens'],
                'estimated_cost' => $estimatedCost,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Failed to log AI usage', [
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
        // Use total_cost from API if available (OpenRouter provides this)
        if (isset($response['total_cost']) && $response['total_cost'] !== null) {
            return (float)$response['total_cost'];
        }

        // Otherwise calculate manually
        $model = $response['model'] ?? '';
        $inputTokens = $response['input_tokens'] ?? 0;
        $outputTokens = $response['output_tokens'] ?? 0;

        // Find matching price tier
        $prices = self::DEFAULT_PRICES['default'];
        foreach (self::DEFAULT_PRICES as $modelKey => $modelPrices) {
            if ($modelKey !== 'default' && stripos($model, $modelKey) !== false) {
                $prices = $modelPrices;
                break;
            }
        }

        // Check settings for custom prices
        $settingsInputKey = 'ai_input_price_' . preg_replace('/[^a-z0-9]/', '_', strtolower($model));
        $settingsOutputKey = 'ai_output_price_' . preg_replace('/[^a-z0-9]/', '_', strtolower($model));

        $inputPrice = Settings::getFloat($settingsInputKey, $prices['input']);
        $outputPrice = Settings::getFloat($settingsOutputKey, $prices['output']);

        // Calculate cost
        $cost = ($inputTokens / 1_000_000) * $inputPrice + ($outputTokens / 1_000_000) * $outputPrice;

        // Add 20% markup for OpenRouter
        if (($response['provider'] ?? 'openrouter') === 'openrouter') {
            $cost *= 1.2;
        }

        return round($cost, 6);
    }

    /**
     * Clamp importance score to 1-10 range.
     */
    private function clampScore(int $score): int
    {
        return max(1, min(10, $score));
    }
}
