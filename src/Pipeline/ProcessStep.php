<?php declare(strict_types=1);

namespace NewsBot\Pipeline;

use NewsBot\Core\AlertManager;
use NewsBot\Core\Database;
use NewsBot\Core\Logger;
use NewsBot\Core\Settings;
use NewsBot\Core\ShutdownHandler;
use NewsBot\Models\Article;
use NewsBot\Models\ArticleVersion;
use NewsBot\Models\Channel;
use NewsBot\Models\ChannelSource;
use NewsBot\Services\AiProcessor;
use NewsBot\Services\AiValidator;
use NewsBot\Services\TemporaryApiException;

/**
 * Pipeline Step 3: AI Processing and Validation.
 * Processes scraped articles through AI for each target channel.
 */
class ProcessStep
{
    private AiProcessor $processor;
    private AiValidator $validator;

    // Statistics
    private int $total = 0;
    private int $ok = 0;
    private int $failed = 0;
    private int $skipped = 0;
    private int $forReview = 0;

    // Consecutive API error tracking
    private int $consecutiveApiErrors = 0;
    private string $lastApiError = '';

    private const LIMIT_PER_RUN = 50;
    private const STUCK_TIMEOUT_MINUTES = 10;
    private const CONSECUTIVE_ERROR_THRESHOLD = 5;

    public function __construct()
    {
        $this->processor = new AiProcessor();
        $this->validator = new AiValidator();
    }

    /**
     * Run the process step.
     *
     * @param array $channelIds Channel IDs to filter (empty = all)
     */
    public function run(array $channelIds = []): void
    {
        $startedAt = new \DateTimeImmutable();
        $runId = $this->logPipelineStart($channelIds);

        try {
            // Check AI budget before starting
            if (!$this->checkBudget()) {
                return;
            }

            // Recover stuck articles
            $this->recoverStuckArticles();

            // Get articles to process
            $articles = $this->getArticlesToProcess($channelIds);

            if (empty($articles)) {
                Logger::info('ProcessStep: No articles to process');
                return;
            }

            Logger::info('ProcessStep: Starting', ['count' => count($articles)]);

            foreach ($articles as $article) {
                // Graceful shutdown check
                if (ShutdownHandler::shouldShutdown()) {
                    Logger::info('ProcessStep: Shutdown requested, stopping');
                    break;
                }

                // Check consecutive API errors threshold
                if ($this->consecutiveApiErrors >= self::CONSECUTIVE_ERROR_THRESHOLD) {
                    Logger::warning('ProcessStep: Too many consecutive API errors, stopping', [
                        'consecutive_errors' => $this->consecutiveApiErrors,
                        'last_error' => $this->lastApiError,
                    ]);
                    AlertManager::sendConsecutiveApiErrorsAlert(
                        $this->consecutiveApiErrors,
                        $this->lastApiError
                    );
                    break;
                }

                // Re-check budget before each article
                if (!$this->checkBudget()) {
                    break;
                }

                $this->total++;

                try {
                    $this->processArticle($article, $channelIds);
                    // Reset consecutive error counter on success
                    $this->consecutiveApiErrors = 0;
                } catch (TemporaryApiException $e) {
                    // Track consecutive API errors
                    $this->consecutiveApiErrors++;
                    $this->lastApiError = $e->getMessage();
                    Logger::error('ProcessStep: Temporary API error', [
                        'article_id' => $article->id,
                        'error' => $e->getMessage(),
                        'consecutive_errors' => $this->consecutiveApiErrors,
                    ]);
                    $article->changeStatus('scraped', [
                        'reason' => 'Temporary API error, will retry',
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable $e) {
                    Logger::error('ProcessStep: Article processing failed', [
                        'article_id' => $article->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $article->changeStatus('process_failed', [
                        'error' => $e->getMessage(),
                    ]);
                    $this->failed++;
                }
            }

            Logger::info('ProcessStep: Completed', [
                'total' => $this->total,
                'ok' => $this->ok,
                'failed' => $this->failed,
                'skipped' => $this->skipped,
                'for_review' => $this->forReview,
            ]);

        } finally {
            $this->logPipelineFinish($runId, $startedAt);
        }
    }

    /**
     * Check if AI budget is available.
     */
    private function checkBudget(): bool
    {
        $row = Database::fetchOne(
            "SELECT COALESCE(SUM(estimated_cost), 0) as total FROM ai_usage_log WHERE DATE(created_at) = CURDATE()"
        );
        $todaySpent = (float)($row['total'] ?? 0);
        $budget = Settings::getFloat('ai_daily_budget', 10.00);

        if ($todaySpent >= $budget) {
            Logger::warning('AI daily budget exceeded', [
                'spent' => $todaySpent,
                'budget' => $budget,
            ]);
            AlertManager::send(
                "AI budget exhausted: \${$todaySpent} / \${$budget}. Processing paused.",
                'critical'
            );
            return false;
        }

        return true;
    }

    /**
     * Recover articles stuck in 'processing' status.
     */
    private function recoverStuckArticles(): void
    {
        $stuckArticles = Database::fetchAll(
            "SELECT id FROM articles
             WHERE status = 'processing'
             AND updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [self::STUCK_TIMEOUT_MINUTES]
        );

        foreach ($stuckArticles as $row) {
            $article = Article::find((int)$row['id']);
            if ($article) {
                $article->changeStatus('scraped', [
                    'reason' => 'Recovered from stuck processing state',
                ]);
                Logger::warning('Recovered stuck article', ['article_id' => $row['id']]);
            }
        }

        if (!empty($stuckArticles)) {
            Logger::info('Recovered stuck articles', ['count' => count($stuckArticles)]);
        }
    }

    /**
     * Get articles that need processing with atomic capture.
     *
     * Uses FOR UPDATE SKIP LOCKED to prevent race conditions:
     * - Multiple processes won't grab the same articles
     * - Already-locked rows are skipped, not blocked
     * - Status is changed to 'processing' atomically
     */
    private function getArticlesToProcess(array $channelIds): array
    {
        // Build WHERE clause
        $where = "status = 'scraped'";
        $params = [];

        if (!empty($channelIds)) {
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
            $where .= " AND source_id IN ({$placeholders})";
            $params = $sourceIds;
        }

        // Atomic capture with transaction + FOR UPDATE SKIP LOCKED
        Database::beginTransaction();

        try {
            // Select and lock articles, skipping already-locked ones
            $params[] = self::LIMIT_PER_RUN;
            $rows = Database::fetchAll(
                "SELECT id FROM articles WHERE {$where} ORDER BY created_at ASC LIMIT ? FOR UPDATE SKIP LOCKED",
                $params
            );

            if (empty($rows)) {
                Database::commit();
                return [];
            }

            $ids = array_column($rows, 'id');
            $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));

            // Atomically change status to 'processing'
            Database::execute(
                "UPDATE articles SET status = 'processing', updated_at = NOW() WHERE id IN ({$idPlaceholders})",
                $ids
            );

            // Log status changes for captured articles
            foreach ($ids as $articleId) {
                Database::insert('article_status_log', [
                    'article_id' => $articleId,
                    'old_status' => 'scraped',
                    'new_status' => 'processing',
                    'details' => json_encode(['action' => 'atomic_capture']),
                ]);
            }

            Database::commit();

            Logger::debug('Atomically captured articles for processing', [
                'count' => count($ids),
                'ids' => $ids,
            ]);

            // Load full Article objects
            return Article::all("id IN ({$idPlaceholders})", $ids, 'created_at ASC', count($ids));

        } catch (\Throwable $e) {
            Database::rollback();
            Logger::error('Failed to capture articles atomically', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process a single article.
     */
    private function processArticle(Article $article, array $filterChannelIds): void
    {
        // CRITICAL: Reload article from DB - cluster_id may have changed
        $article = Article::find((int)$article->id);
        if ($article === null) {
            Logger::warning('Article disappeared during processing', ['id' => $article->id ?? 'unknown']);
            return;
        }

        // Verify article is still in 'processing' status (was set atomically in getArticlesToProcess)
        if ($article->status !== 'processing') {
            Logger::warning('Article status changed unexpectedly, skipping', [
                'article_id' => $article->id,
                'expected' => 'processing',
                'actual' => $article->status,
            ]);
            return;
        }

        // Determine target channels
        $targetChannelIds = $this->getTargetChannels($article);

        // Apply filter if specified
        if (!empty($filterChannelIds)) {
            $targetChannelIds = array_intersect($targetChannelIds, $filterChannelIds);
        }

        if (empty($targetChannelIds)) {
            Logger::info('No target channels for article', ['article_id' => $article->id]);
            $article->changeStatus('processed', ['reason' => 'No target channels']);
            $this->ok++;
            return;
        }

        // Status is already 'processing' (set atomically in getArticlesToProcess)

        // Track results per channel
        $successCount = 0;
        $temporaryFailedCount = 0;
        $permanentFailedCount = 0;
        $skippedCount = 0;
        $cancelledCount = 0;

        foreach ($targetChannelIds as $channelId) {
            // Check shutdown
            if (ShutdownHandler::shouldShutdown()) {
                Logger::info('ProcessStep: Shutdown during channel processing');
                break;
            }

            // Re-check budget before each AI call
            if (!$this->checkBudget()) {
                $temporaryFailedCount++;
                continue;
            }

            $channel = Channel::find($channelId);
            if (!$channel || !$channel->isActive()) {
                continue;
            }

            try {
                $result = $this->processArticleForChannel($article, $channel);

                switch ($result) {
                    case 'success':
                        $successCount++;
                        break;
                    case 'skipped':
                        $skippedCount++;
                        break;
                    case 'cancelled':
                        $cancelledCount++;
                        break;
                    case 'review':
                        $this->forReview++;
                        $successCount++; // Count as partial success
                        break;
                }
            } catch (TemporaryApiException $e) {
                Logger::warning('Temporary API error for channel', [
                    'article_id' => $article->id,
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                ]);
                $temporaryFailedCount++;
            } catch (\RuntimeException $e) {
                Logger::error('Permanent error for channel', [
                    'article_id' => $article->id,
                    'channel_id' => $channelId,
                    'error' => $e->getMessage(),
                ]);
                $permanentFailedCount++;
            }
        }

        // Determine final article status
        $this->setFinalArticleStatus(
            $article,
            $successCount,
            $temporaryFailedCount,
            $permanentFailedCount,
            $skippedCount,
            $cancelledCount
        );
    }

    /**
     * Get target channels for an article (including cluster expansion).
     */
    private function getTargetChannels(Article $article): array
    {
        // Get channels from article's source
        $channelIds = ChannelSource::getChannelIds((int)$article->source_id);

        // Expand through cluster if article is part of one
        if ($article->cluster_id) {
            $clusterMembers = Database::fetchAll(
                "SELECT DISTINCT a.source_id
                 FROM article_cluster_members acm
                 JOIN articles a ON a.id = acm.article_id
                 WHERE acm.cluster_id = ?",
                [$article->cluster_id]
            );

            foreach ($clusterMembers as $member) {
                $memberChannels = ChannelSource::getChannelIds((int)$member['source_id']);
                $channelIds = array_merge($channelIds, $memberChannels);
            }
        }

        // Filter to active channels only
        $activeChannelIds = [];
        foreach (array_unique($channelIds) as $channelId) {
            $channel = Channel::find($channelId);
            if ($channel && $channel->isActive()) {
                $activeChannelIds[] = $channelId;
            }
        }

        return $activeChannelIds;
    }

    /**
     * Process article for a specific channel.
     *
     * @return string 'success'|'skipped'|'cancelled'|'review'
     */
    private function processArticleForChannel(Article $article, Channel $channel): string
    {
        // Check if version already exists
        $existing = ArticleVersion::findForArticleChannel((int)$article->id, (int)$channel->id);
        if ($existing) {
            Logger::debug('Version already exists', [
                'article_id' => $article->id,
                'channel_id' => $channel->id,
                'version_id' => $existing->id,
            ]);
            return 'success';
        }

        // Process with retry logic
        $version = null;
        $bestVersion = null;
        $bestScore = 0;
        $attempt = 1;
        $maxAttempts = 3;

        while ($attempt <= $maxAttempts) {
            // Process article
            $version = $this->processor->process($article, $channel, $attempt);

            // Skip case
            if ($version === null) {
                $this->createSkippedVersion($article, $channel);
                return 'skipped';
            }

            // Validate
            $validation = $this->validator->validate($article, $version, $channel);
            $score = $validation['score'];

            // Update version with validation results
            ArticleVersion::update((int)$version->id, [
                'validation_score' => $score,
                'validation_notes' => $validation['notes'],
            ]);

            // Track best version
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestVersion = $version;
            }

            // Score 1 = cancelled (critical quality issues)
            if ($score === 1) {
                ArticleVersion::update((int)$version->id, ['status' => 'cancelled']);
                Logger::info('Article cancelled due to score 1', [
                    'article_id' => $article->id,
                    'channel_id' => $channel->id,
                    'version_id' => $version->id,
                ]);
                return 'cancelled';
            }

            // Check if passes minimum validation score
            $minScore = (int)($channel->min_validation_score ?? 5);

            if ($score >= $minScore) {
                // Check importance threshold
                $minImportance = (int)($channel->min_importance_score ?? 1);
                $importance = (int)($version->importance_score ?? 0);

                if ($importance < $minImportance) {
                    ArticleVersion::update((int)$version->id, ['status' => 'skipped']);
                    Logger::info('Article skipped due to low importance', [
                        'article_id' => $article->id,
                        'channel_id' => $channel->id,
                        'importance' => $importance,
                        'min_importance' => $minImportance,
                    ]);
                    return 'skipped';
                }

                ArticleVersion::update((int)$version->id, ['status' => 'validated']);
                return 'success';
            }

            // Score 2-5 with attempts remaining: retry with higher temperature
            if ($score >= 2 && $score < $minScore && $attempt < $maxAttempts) {
                $prevScore = $score;

                // Delete current version before retry
                ArticleVersion::delete((int)$version->id);

                $attempt++;

                // After retry, check if new score is better
                // If not improving, stop retrying
                Logger::debug('Retrying with higher temperature', [
                    'article_id' => $article->id,
                    'channel_id' => $channel->id,
                    'attempt' => $attempt,
                    'prev_score' => $prevScore,
                ]);

                continue;
            }

            // Score 2-3 and max attempts reached: manual review
            if ($score <= 3 && $attempt >= $maxAttempts) {
                ArticleVersion::update((int)$version->id, ['status' => 'manual_review']);
                return 'review';
            }

            // Score 4+ but below min_validation_score and max attempts: manual review
            if ($score < $minScore && $attempt >= $maxAttempts) {
                if ($channel->isManualReviewEnabled()) {
                    ArticleVersion::update((int)$version->id, ['status' => 'manual_review']);
                    return 'review';
                } else {
                    // Accept with lower score if manual review is disabled
                    // But still check importance threshold
                    $minImportance = (int)($channel->min_importance_score ?? 1);
                    $importance = (int)($version->importance_score ?? 0);

                    if ($importance < $minImportance) {
                        ArticleVersion::update((int)$version->id, ['status' => 'skipped']);
                        return 'skipped';
                    }

                    ArticleVersion::update((int)$version->id, ['status' => 'validated']);
                    return 'success';
                }
            }

            break;
        }

        // Fallback: use best version we got
        if ($bestVersion) {
            $minScore = (int)($channel->min_validation_score ?? 5);
            if ($bestScore >= $minScore) {
                // Check importance threshold
                $minImportance = (int)($channel->min_importance_score ?? 1);
                $importance = (int)($bestVersion->importance_score ?? 0);

                if ($importance < $minImportance) {
                    ArticleVersion::update((int)$bestVersion->id, ['status' => 'skipped']);
                    return 'skipped';
                }

                ArticleVersion::update((int)$bestVersion->id, ['status' => 'validated']);
                return 'success';
            } else {
                ArticleVersion::update((int)$bestVersion->id, ['status' => 'manual_review']);
                return 'review';
            }
        }

        return 'success';
    }

    /**
     * Create a skipped version record (race-condition safe).
     */
    private function createSkippedVersion(Article $article, Channel $channel): void
    {
        // Use findOrCreate to handle concurrent inserts
        ArticleVersion::findOrCreate([
            'article_id' => $article->id,
            'channel_id' => $channel->id,
            'title' => 'Skipped',
            'body' => 'Article was skipped by AI',
            'status' => 'skipped',
            'prompt_version' => $channel->getPromptVersion(),
        ]);
    }

    /**
     * Set final article status based on channel processing results.
     */
    private function setFinalArticleStatus(
        Article $article,
        int $successCount,
        int $temporaryFailedCount,
        int $permanentFailedCount,
        int $skippedCount,
        int $cancelledCount
    ): void {
        $totalChannels = $successCount + $temporaryFailedCount + $permanentFailedCount + $skippedCount + $cancelledCount;

        // All channels successful (including partial success with review)
        if ($successCount > 0 && $temporaryFailedCount === 0 && $permanentFailedCount === 0) {
            $article->changeStatus('processed', [
                'success_count' => $successCount,
                'skipped_count' => $skippedCount,
            ]);
            $this->ok++;
            return;
        }

        // Has temporary failures - return to scraped for retry
        if ($temporaryFailedCount > 0) {
            $article->changeStatus('scraped', [
                'reason' => 'Temporary API failures, will retry',
                'temporary_failed' => $temporaryFailedCount,
                'success' => $successCount,
            ]);
            // Don't count as failed - will be retried
            return;
        }

        // All permanent failures
        if ($permanentFailedCount === $totalChannels && $permanentFailedCount > 0) {
            $article->changeStatus('process_failed', [
                'reason' => 'All channels had permanent failures',
                'failed_count' => $permanentFailedCount,
            ]);
            $this->failed++;
            return;
        }

        // All skipped
        if ($skippedCount === $totalChannels && $skippedCount > 0) {
            $article->changeStatus('skipped', [
                'reason' => 'AI decided to skip for all channels',
            ]);
            $this->skipped++;
            return;
        }

        // All cancelled (score 1)
        if ($cancelledCount === $totalChannels && $cancelledCount > 0) {
            $article->changeStatus('cancelled', [
                'reason' => 'All versions had critical quality issues (score 1)',
            ]);
            $this->failed++;
            return;
        }

        // Mixed case: some success + some permanent failures (no temporary)
        if ($successCount > 0 && $temporaryFailedCount === 0) {
            $article->changeStatus('processed', [
                'success_count' => $successCount,
                'failed_count' => $permanentFailedCount,
                'skipped_count' => $skippedCount,
                'note' => 'Partially successful',
            ]);
            $this->ok++;
            return;
        }

        // Fallback - should not normally reach here
        $article->changeStatus('process_failed', [
            'reason' => 'Unknown processing outcome',
            'success' => $successCount,
            'temporary_failed' => $temporaryFailedCount,
            'permanent_failed' => $permanentFailedCount,
            'skipped' => $skippedCount,
            'cancelled' => $cancelledCount,
        ]);
        $this->failed++;
    }

    /**
     * Log pipeline run start.
     */
    private function logPipelineStart(array $channelIds): int
    {
        return Database::insert('pipeline_runs', [
            'step' => 'process',
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
}
