<?php declare(strict_types=1);

namespace NewsBot\Pipeline;

use NewsBot\Core\CircuitBreaker;
use NewsBot\Core\Database;
use NewsBot\Core\Logger;
use NewsBot\Core\ShutdownHandler;
use NewsBot\Helpers\LanguageDetector;
use NewsBot\Models\Article;
use NewsBot\Models\ChannelSource;
use NewsBot\Models\Feed;
use NewsBot\Models\Source;
use NewsBot\Services\Deduplicator;
use NewsBot\Services\Scraper;
use NewsBot\Services\TextCleaner;
use NewsBot\Services\UrlCanonizer;

/**
 * Pipeline Step 2: Scrape full article text from source websites.
 */
class ScrapeStep
{
    private Scraper $scraper;
    private ?Deduplicator $deduplicator = null;

    // Statistics
    private int $total = 0;
    private int $ok = 0;
    private int $failed = 0;
    private int $duplicates = 0;

    // Hard limits
    private const MAX_TEXT_CHARS = 500000;
    private const LIMIT_PER_RUN = 100;

    public function __construct()
    {
        $this->scraper = new Scraper();

        // Initialize deduplicator if available
        if (class_exists(Deduplicator::class)) {
            try {
                $this->deduplicator = new Deduplicator();
            } catch (\Throwable $e) {
                Logger::warning('Failed to initialize Deduplicator', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Run the scrape step.
     *
     * @param array $channelIds Channel IDs to filter (empty = all)
     */
    public function run(array $channelIds = []): void
    {
        $startedAt = new \DateTimeImmutable();
        $runId = $this->logPipelineStart($channelIds);

        try {
            // Get articles to scrape
            $articles = $this->getArticlesToScrape($channelIds);

            if (empty($articles)) {
                Logger::info('ScrapeStep: No articles to scrape');
                return;
            }

            Logger::info('ScrapeStep: Starting', ['count' => count($articles)]);

            foreach ($articles as $article) {
                // Graceful shutdown check
                if (ShutdownHandler::shouldShutdown()) {
                    Logger::info('ScrapeStep: Shutdown requested, stopping');
                    break;
                }

                // Circuit breaker check
                if (!CircuitBreaker::isAvailable('scraper')) {
                    Logger::warning('ScrapeStep: Circuit breaker open, stopping');
                    break;
                }

                $this->total++;

                try {
                    $this->processArticle($article);
                } catch (\Throwable $e) {
                    Logger::error('ScrapeStep: Article processing failed', [
                        'article_id' => $article->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->handleScrapeFailure($article);
                }
            }

            Logger::info('ScrapeStep: Completed', [
                'total' => $this->total,
                'ok' => $this->ok,
                'failed' => $this->failed,
                'duplicates' => $this->duplicates,
            ]);

        } finally {
            $this->logPipelineFinish($runId, $startedAt);
        }
    }

    /**
     * Get articles that need scraping.
     */
    private function getArticlesToScrape(array $channelIds): array
    {
        if (empty($channelIds)) {
            return Article::getByStatus('fetched', self::LIMIT_PER_RUN);
        }

        // Filter by channels via sources
        $sourceIds = [];
        foreach ($channelIds as $channelId) {
            $ids = ChannelSource::getSourceIds($channelId);
            $sourceIds = array_merge($sourceIds, $ids);
        }
        $sourceIds = array_unique($sourceIds);

        if (empty($sourceIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($sourceIds), '?'));
        return Article::all(
            "status = 'fetched' AND source_id IN ({$placeholders})",
            $sourceIds,
            'created_at ASC',
            self::LIMIT_PER_RUN
        );
    }

    /**
     * Process a single article.
     */
    private function processArticle(Article $article): void
    {
        // Change status to scraping
        $article->changeStatus('scraping');

        // Get source
        $source = $this->getSourceForArticle($article);
        if ($source === null) {
            Logger::warning('ScrapeStep: Source not found', ['article_id' => $article->id]);
            $article->changeStatus('scrape_failed', ['reason' => 'Source not found']);
            $this->failed++;
            return;
        }

        // Scrape
        $result = $this->scraper->scrape($article, $source);

        if (!$result['success']) {
            $this->handleScrapeFailure($article);
            return;
        }

        // Save scraped data
        $updateData = [
            'scraped_text' => $result['text'],
            'scraped_image_url' => $result['image_url'],
            'original_language' => $result['language'],
        ];

        if ($result['title'] !== null) {
            $updateData['scraped_title'] = $this->truncate($result['title'], 500);
        }

        // Apply hard limit to text
        if ($updateData['scraped_text'] !== null && mb_strlen($updateData['scraped_text']) > self::MAX_TEXT_CHARS) {
            Logger::warning('Text truncated to 500K chars', [
                'article_id' => $article->id,
                'original_length' => mb_strlen($updateData['scraped_text']),
            ]);
            $updateData['scraped_text'] = mb_substr($updateData['scraped_text'], 0, self::MAX_TEXT_CHARS);
        }

        // Check redirect URL for duplicates
        if ($result['effective_url'] !== null && $result['effective_url'] !== $article->url) {
            $redirectHash = UrlCanonizer::hash($result['effective_url']);
            $existing = Article::findByUrlHash($redirectHash);

            if ($existing && (int)$existing->id !== (int)$article->id) {
                Logger::info('Duplicate detected via redirect', [
                    'article_id' => $article->id,
                    'existing_id' => $existing->id,
                    'effective_url' => $result['effective_url'],
                ]);
                $article->changeStatus('duplicate', [
                    'reason' => 'Redirect URL matches existing article',
                    'existing_id' => $existing->id,
                ]);
                $this->duplicates++;
                return;
            }
        }

        // Prepare dedup fields
        $dedupTitle = $updateData['scraped_title']
            ?? $article->scraped_title
            ?? $article->rss_title
            ?? '';
        $dedupTitle = mb_substr($dedupTitle, 0, 200);

        $dedupSummary = TextCleaner::summarize(
            $updateData['scraped_text'] ?? $article->rss_description ?? '',
            450  // Leave room for multi-byte chars and ellipsis
        );
        // Ensure byte-safe truncation for VARCHAR(500)
        $dedupSummary = mb_strcut($dedupSummary, 0, 490);

        $updateData['dedup_title'] = $dedupTitle ?: null;
        $updateData['dedup_summary'] = $dedupSummary ?: null;

        // Save to database
        Article::update((int)$article->id, $updateData);

        // CRITICAL: Reload article after update - BaseModel::update() does NOT update the object
        $article = Article::find((int)$article->id);
        if ($article === null) {
            Logger::error('ScrapeStep: Article disappeared after update', ['id' => $article->id ?? 'unknown']);
            $this->failed++;
            return;
        }

        // Run deduplication check
        if ($this->deduplicator !== null && !empty($article->dedup_title)) {
            try {
                $dupResult = $this->deduplicator->check($article);

                if ($dupResult !== null) {
                    $article->changeStatus('duplicate', [
                        'cluster_id' => $dupResult['cluster_id'],
                        'primary_article_id' => $dupResult['primary_article_id'],
                        'matched_article_id' => $dupResult['matched_article_id'],
                        'confidence' => $dupResult['confidence'],
                        'method' => $dupResult['method'],
                        'reason' => $dupResult['reason'],
                    ]);

                    Article::update((int)$article->id, ['cluster_id' => $dupResult['cluster_id']]);

                    Logger::info('Article marked as duplicate', [
                        'article_id' => $article->id,
                        'cluster_id' => $dupResult['cluster_id'],
                        'method' => $dupResult['method'],
                    ]);

                    $this->duplicates++;
                    return;
                }
            } catch (\Throwable $e) {
                Logger::warning('Deduplication check failed', [
                    'article_id' => $article->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue with scraping - dedup failure shouldn't block the pipeline
            }
        }

        // Success - change status to scraped
        $article->changeStatus('scraped', [
            'method' => $result['method'],
            'text_length' => mb_strlen($result['text'] ?? ''),
        ]);

        $this->ok++;

        Logger::debug('Article scraped successfully', [
            'article_id' => $article->id,
            'method' => $result['method'],
            'text_length' => mb_strlen($result['text'] ?? ''),
            'language' => $result['language'],
        ]);
    }

    /**
     * Handle scrape failure with retry logic.
     */
    private function handleScrapeFailure(Article $article): void
    {
        $attempts = (int)($article->scrape_attempts ?? 0) + 1;

        Article::update((int)$article->id, ['scrape_attempts' => $attempts]);

        if ($attempts < 3) {
            // Return to fetched for retry
            $article->changeStatus('fetched', [
                'reason' => "Scrape attempt {$attempts} failed, will retry",
            ]);
            Logger::info('Article will retry scrape', [
                'article_id' => $article->id,
                'attempt' => $attempts,
            ]);
        } else {
            // Max attempts reached
            $article->changeStatus('scrape_failed', [
                'reason' => 'Max scrape attempts reached',
                'attempts' => $attempts,
            ]);
            Logger::warning('Article scrape failed permanently', [
                'article_id' => $article->id,
                'attempts' => $attempts,
            ]);
        }

        $this->failed++;
    }

    /**
     * Get source for article, handling feed_id vs source_id.
     */
    private function getSourceForArticle(Article $article): ?Source
    {
        // Try via feed first
        if ($article->feed_id) {
            $feed = Feed::find((int)$article->feed_id);
            if ($feed) {
                return $feed->getSource();
            }
            Logger::warning('Feed not found for article', [
                'article_id' => $article->id,
                'feed_id' => $article->feed_id,
            ]);
        }

        // Fallback to direct source_id
        if ($article->source_id) {
            return Source::find((int)$article->source_id);
        }

        return null;
    }

    /**
     * Log pipeline run start.
     */
    private function logPipelineStart(array $channelIds): int
    {
        return Database::insert('pipeline_runs', [
            'step' => 'scrape',
            'channel_ids' => empty($channelIds) ? null : implode(',', $channelIds),
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log pipeline run finish.
     */
    private function logPipelineFinish(int $runId, \DateTimeImmutable $startedAt): void
    {
        $finishedAt = new \DateTimeImmutable();
        $durationMs = (int)(($finishedAt->getTimestamp() - $startedAt->getTimestamp()) * 1000);

        try {
            Database::update('pipeline_runs', [
                'finished_at' => $finishedAt->format('Y-m-d H:i:s'),
                'duration_ms' => $durationMs,
                'articles_total' => $this->total,
                'articles_ok' => $this->ok,
                'articles_failed' => $this->failed,
            ], 'id = ?', [$runId]);
        } catch (\Throwable $e) {
            Logger::warning('Failed to log pipeline finish', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Truncate string to max length.
     */
    private function truncate(?string $str, int $maxLength): ?string
    {
        if ($str === null || $str === '') {
            return null;
        }

        if (mb_strlen($str, 'UTF-8') <= $maxLength) {
            return $str;
        }

        return mb_substr($str, 0, $maxLength, 'UTF-8');
    }
}
