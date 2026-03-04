<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\Database;
use NewsBot\Core\Logger;
use NewsBot\Core\Settings;
use NewsBot\Models\Article;
use NewsBot\Models\ArticleClusterMember;

/**
 * 3-level deduplication service.
 * Level 1: URL hash (in FetchStep)
 * Level 2a: MinHash pre-filter (obvious copy-paste)
 * Level 2b: AI title comparison (semantic duplicates)
 * Level 3: Cluster-based (in PublishStep)
 */
class Deduplicator
{
    private AiClientInterface $client;
    private const BATCH_SIZE = 150;
    private const MINHASH_THRESHOLD = 0.8;
    private const AI_CONFIDENCE_THRESHOLD = 0.7;

    public function __construct()
    {
        $this->client = AiClient::create('deduplicate');
    }

    /**
     * Check if article is a duplicate.
     *
     * @param Article $article Article to check (must have dedup_title filled)
     * @return array|null null if unique, or duplicate info
     */
    public function check(Article $article): ?array
    {
        // Verify dedup_title is filled
        if (empty($article->dedup_title)) {
            Logger::debug('Deduplicator: no dedup_title, skipping', ['article_id' => $article->id]);
            return null;
        }

        // Stage A: MinHash pre-filter (if enabled and text is long enough)
        $minhashEnabled = Settings::getBool('dedup_minhash_enabled', true);
        $scrapedText = $article->scraped_text ?? '';
        $language = $article->original_language ?? 'en';
        $wordCount = TextCleaner::countWords($scrapedText, $language);

        if ($minhashEnabled && $wordCount > 100) {
            $minhashResult = $this->quickMinHashCheck($article, $scrapedText, $language);
            if ($minhashResult !== null) {
                return $minhashResult;
            }
        }

        // Stage B: AI title check
        return $this->aiTitleCheck($article);
    }

    /**
     * Quick MinHash check for obvious copy-paste duplicates.
     */
    private function quickMinHashCheck(Article $article, string $text, string $language): ?array
    {
        // Compute signature
        $signature = MinHash::compute($text, $language);

        // Save fingerprint for future comparisons
        MinHash::saveFingerprint((int)$article->id, $signature);

        // Find duplicates
        $match = MinHash::findExactDuplicates(
            $signature,
            (int)$article->id,
            self::MINHASH_THRESHOLD,
            72
        );

        if ($match === null) {
            return null;
        }

        Logger::info('MinHash duplicate found', [
            'article_id' => $article->id,
            'matched_id' => $match['article_id'],
            'similarity' => $match['similarity'],
        ]);

        // Create or update cluster
        return $this->clusterArticles(
            $article,
            $match['article_id'],
            $match['similarity'],
            'MinHash similarity: ' . round($match['similarity'] * 100) . '%',
            'minhash'
        );
    }

    /**
     * AI-based title comparison for semantic duplicates.
     */
    private function aiTitleCheck(Article $article): ?array
    {
        $maxBatches = Settings::getInt('dedup_max_batches', 3);
        $maxArticles = Settings::getInt('dedup_max_articles', 450);

        // Load existing titles
        $existingTitles = $this->loadExistingTitles(
            (int)$article->id,
            72,
            $maxArticles
        );

        if (empty($existingTitles)) {
            Logger::debug('Deduplicator: no existing titles to compare', ['article_id' => $article->id]);
            return null;
        }

        // Split into batches
        $batches = array_chunk($existingTitles, self::BATCH_SIZE);
        $batches = array_slice($batches, 0, $maxBatches);

        foreach ($batches as $batchIdx => $batch) {
            $result = $this->checkBatch(
                $article,
                $batch,
                $batchIdx + 1,
                count($batches)
            );

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Check a single batch of titles against the article.
     */
    private function checkBatch(Article $article, array $titles, int $batchNum, int $totalBatches): ?array
    {
        $prompt = $this->buildDedupPrompt(
            $article->dedup_title,
            $article->dedup_summary ?? '',
            $titles
        );

        try {
            $model = AiClient::getModel('deduplicate');
            $temperature = AiClient::getTemperature('deduplicate');

            $response = $this->client->message(
                $this->getSystemPrompt(),
                $prompt,
                $model,
                $temperature,
                500
            );

            // Log AI usage
            $this->logAiUsage(
                (int)$article->id,
                $response['input_tokens'],
                $response['output_tokens'],
                $model,
                $response['provider'] ?? 'openrouter',
                $response['total_cost'] ?? null
            );

            // Parse response
            $data = AnthropicClient::parseJson($response['content']);
            if ($data === null) {
                Logger::warning('Deduplicator: invalid JSON response', [
                    'article_id' => $article->id,
                    'response' => mb_substr($response['content'], 0, 500),
                ]);
                return null;
            }

            // Check for duplicate
            if (!($data['is_duplicate'] ?? false)) {
                Logger::debug('Deduplicator: batch not duplicate', [
                    'article_id' => $article->id,
                    'batch' => $batchNum,
                ]);
                return null;
            }

            $confidence = (float)($data['confidence'] ?? 0);
            $matchedId = $data['matched_id'] ?? null;
            $reason = $data['reason'] ?? 'AI detected duplicate';

            // Validate confidence threshold
            if ($confidence < self::AI_CONFIDENCE_THRESHOLD) {
                Logger::debug('Deduplicator: confidence below threshold', [
                    'article_id' => $article->id,
                    'confidence' => $confidence,
                    'threshold' => self::AI_CONFIDENCE_THRESHOLD,
                ]);
                return null;
            }

            // Validate matched_id exists in the batch
            $validMatch = false;
            foreach ($titles as $title) {
                if ((int)$title['id'] === (int)$matchedId) {
                    $validMatch = true;
                    break;
                }
            }

            if (!$validMatch) {
                Logger::warning('Deduplicator: matched_id not in batch', [
                    'article_id' => $article->id,
                    'matched_id' => $matchedId,
                ]);
                return null;
            }

            Logger::info('AI duplicate found', [
                'article_id' => $article->id,
                'matched_id' => $matchedId,
                'confidence' => $confidence,
                'reason' => $reason,
            ]);

            // Create or update cluster
            return $this->clusterArticles(
                $article,
                (int)$matchedId,
                $confidence,
                $reason,
                'ai_titles'
            );

        } catch (TemporaryApiException $e) {
            Logger::warning('Deduplicator: temporary API error', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
            return null;

        } catch (\Throwable $e) {
            Logger::error('Deduplicator: AI check failed', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Load existing titles for comparison.
     * Uses short_title from article_versions if available.
     */
    private function loadExistingTitles(int $excludeArticleId, int $hours = 72, int $limit = 450): array
    {
        // MariaDB 10.6 compatible query using subquery for best version
        $sql = "
            SELECT
                a.id,
                COALESCE(best_av.short_title, a.dedup_title) as title,
                COALESCE(best_av.description, a.dedup_summary, '') as summary,
                a.source_id
            FROM articles a
            LEFT JOIN (
                SELECT av.article_id, av.short_title, av.description
                FROM article_versions av
                INNER JOIN (
                    SELECT article_id, MIN(id) as min_id
                    FROM article_versions WHERE short_title IS NOT NULL
                    GROUP BY article_id
                ) first ON av.id = first.min_id
            ) best_av ON best_av.article_id = a.id
            WHERE a.id != ?
              AND a.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
              AND a.status NOT IN ('scrape_failed', 'expired', 'cancelled')
              AND (a.dedup_title IS NOT NULL OR best_av.short_title IS NOT NULL)
            ORDER BY a.created_at DESC
            LIMIT ?
        ";

        return Database::fetchAll($sql, [$excludeArticleId, $hours, $limit]);
    }

    /**
     * Build AI prompt for deduplication.
     */
    private function buildDedupPrompt(string $newTitle, string $newSummary, array $existingTitles): string
    {
        $lines = ["НОВАЯ СТАТЬЯ:", "Заголовок: {$newTitle}"];

        if (!empty($newSummary)) {
            $lines[] = "Описание: {$newSummary}";
        }

        $lines[] = "";
        $lines[] = "СУЩЕСТВУЮЩИЕ СТАТЬИ:";

        foreach ($existingTitles as $idx => $item) {
            $num = $idx + 1;
            $id = $item['id'];
            $title = $item['title'] ?? '';
            $summary = $item['summary'] ?? '';

            $line = "{$num}. [ID:{$id}] {$title}";
            if (!empty($summary)) {
                $line .= " | " . mb_substr($summary, 0, 100);
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Get system prompt for deduplication.
     */
    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Ты — система определения дубликатов новостей. Тебе дан заголовок и краткое описание новой статьи, а также нумерованный список существующих статей (заголовок + описание). Определи, есть ли среди существующих статья о ТОМ ЖЕ САМОМ событии, даже если она написана другими словами или на другом языке.

Критерии дубликата:
- Описывает то же конкретное событие (не просто ту же тему).
- «Наводнение в Чианг Рай 5 октября» и «Flood hits Chiang Rai» — дубликат.
- «Наводнение в Чианг Рай» и «Наводнение в Бангкоке» — НЕ дубликат (разные события).
- «Биткоин вырос до $100k» и «Bitcoin reaches six figures» — дубликат.

Ответь строго в формате JSON, без текста до или после:
{"is_duplicate": true/false, "matched_id": число_ID_или_null, "confidence": 0.0-1.0, "reason": "краткое пояснение"}
PROMPT;
    }

    /**
     * Create or update cluster for duplicate articles.
     *
     * @return array Cluster info for changeStatus details
     */
    private function clusterArticles(
        Article $newArticle,
        int $matchedArticleId,
        float $similarity,
        string $reason,
        string $method
    ): array {
        Database::beginTransaction();

        try {
            // Check if matched article is already in a cluster
            $existingClusterId = ArticleClusterMember::getClusterIdForArticle($matchedArticleId);

            if ($existingClusterId !== null) {
                // Add to existing cluster
                $clusterId = $existingClusterId;
                ArticleClusterMember::addMember($clusterId, (int)$newArticle->id, $similarity);
            } else {
                // Create new cluster with matched article as initial member
                $clusterId = ArticleClusterMember::createCluster($matchedArticleId);
                // Add matched article to cluster_members with similarity 1.0
                // (already done in createCluster)

                // Add new article to cluster
                ArticleClusterMember::addMember($clusterId, (int)$newArticle->id, $similarity);
            }

            // Determine primary article
            $primaryArticleId = $this->selectPrimaryArticle($clusterId);

            // Update cluster primary
            Database::update('article_clusters', [
                'primary_article_id' => $primaryArticleId,
            ], 'id = ?', [$clusterId]);

            Database::commit();

            Logger::info('Articles clustered', [
                'cluster_id' => $clusterId,
                'new_article' => $newArticle->id,
                'matched_article' => $matchedArticleId,
                'primary_article' => $primaryArticleId,
                'method' => $method,
            ]);

            return [
                'cluster_id' => $clusterId,
                'primary_article_id' => $primaryArticleId,
                'matched_article_id' => $matchedArticleId,
                'confidence' => $similarity,
                'reason' => $reason,
                'method' => $method,
            ];

        } catch (\Throwable $e) {
            Database::rollback();
            Logger::error('Failed to cluster articles', [
                'error' => $e->getMessage(),
                'new_article' => $newArticle->id,
                'matched_article' => $matchedArticleId,
            ]);
            throw $e;
        }
    }

    /**
     * Select primary article in cluster.
     * Priority: authority_rank ASC → text length DESC → created_at ASC
     */
    private function selectPrimaryArticle(int $clusterId): int
    {
        $row = Database::fetchOne("
            SELECT a.id
            FROM article_cluster_members acm
            JOIN articles a ON a.id = acm.article_id
            JOIN sources s ON s.id = a.source_id
            WHERE acm.cluster_id = ?
            ORDER BY s.authority_rank ASC, LENGTH(COALESCE(a.scraped_text, '')) DESC, a.created_at ASC
            LIMIT 1
        ", [$clusterId]);

        return (int)$row['id'];
    }

    /**
     * Log AI usage for billing tracking.
     */
    private function logAiUsage(
        int $articleId,
        int $inputTokens,
        int $outputTokens,
        string $model,
        string $provider,
        ?float $totalCost
    ): void {
        // Calculate cost if not provided by API
        if ($totalCost === null) {
            // Haiku pricing (rough estimate)
            $inputCostPer1k = 0.00025;
            $outputCostPer1k = 0.00125;
            $totalCost = ($inputTokens / 1000 * $inputCostPer1k) + ($outputTokens / 1000 * $outputCostPer1k);

            // Add OpenRouter markup if applicable
            if ($provider === 'openrouter') {
                $totalCost *= 1.2;
            }
        }

        Database::insert('ai_usage_log', [
            'article_id' => $articleId,
            'channel_id' => null,
            'operation' => 'deduplicate',
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost' => $totalCost,
        ]);
    }
}
