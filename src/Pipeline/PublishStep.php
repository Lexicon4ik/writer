<?php declare(strict_types=1);

namespace NewsBot\Pipeline;

use NewsBot\Core\{Database, Logger, CircuitBreaker, ShutdownHandler};
use NewsBot\Models\{Article, ArticleVersion, Channel, Bot};
use NewsBot\Services\{Publisher, TelegramRateLimiter, TelegramException};

/**
 * Pipeline Step 4: Publish validated articles to Telegram channels.
 */
class PublishStep
{
    private Publisher $publisher;

    // Statistics
    private int $total = 0;
    private int $published = 0;
    private int $failed = 0;
    private int $skipped = 0;
    private int $retried = 0;

    // Per-bot rate limiting
    private array $lastSendTime = [];

    // Constants
    private const MAX_PER_RUN_DEFAULT = 10;
    private const MIN_BOT_INTERVAL_SEC = 2;
    private const RETRY_MAX_COUNT = 3;
    private const RETRY_WAIT_MINUTES = 30;

    public function __construct(?Publisher $publisher = null)
    {
        $this->publisher = $publisher ?? new Publisher();
    }

    /**
     * Run the publish step.
     *
     * @param array $channelIds Channel IDs to filter (empty = all)
     */
    public function run(array $channelIds = []): void
    {
        $startedAt = new \DateTimeImmutable();
        $runId = $this->logPipelineStart($channelIds);

        try {
            // Get active channels
            $channels = $this->getChannelsToPublish($channelIds);

            if (empty($channels)) {
                Logger::info('PublishStep: No active channels to publish');
                return;
            }

            Logger::info('PublishStep: Starting', ['channels_count' => count($channels)]);

            foreach ($channels as $channel) {
                // Graceful shutdown check
                if (ShutdownHandler::shouldShutdown()) {
                    Logger::info('PublishStep: Shutdown requested, stopping');
                    break;
                }

                // Circuit breaker check
                if (!CircuitBreaker::isAvailable('telegram')) {
                    Logger::warning('PublishStep: Telegram circuit breaker is open, stopping');
                    break;
                }

                $this->publishForChannel($channel);
            }

            // Retry failed publications
            $this->retryFailedPublications();

            Logger::info('PublishStep: Completed', [
                'total' => $this->total,
                'published' => $this->published,
                'failed' => $this->failed,
                'skipped' => $this->skipped,
                'retried' => $this->retried,
            ]);

        } finally {
            $this->logPipelineFinish($runId, $startedAt);
        }
    }

    /**
     * Get channels to publish to.
     */
    private function getChannelsToPublish(array $channelIds): array
    {
        if (!empty($channelIds)) {
            $channels = [];
            foreach ($channelIds as $id) {
                $channel = Channel::find((int)$id);
                if ($channel && $channel->isActive()) {
                    $channels[] = $channel;
                }
            }
            return $channels;
        }

        return Channel::getActive();
    }

    /**
     * Publish articles for a specific channel.
     */
    private function publishForChannel(Channel $channel): void
    {
        // Check schedule (active hours in channel timezone)
        if (!$this->isWithinActiveHours($channel)) {
            Logger::debug('Channel outside active hours', [
                'channel_id' => $channel->id,
                'timezone' => $channel->timezone ?? 'UTC',
            ]);
            return;
        }

        // Check daily limit
        $publishedToday = $channel->getPublishedTodayCount();
        $maxPerDay = (int)($channel->max_per_day ?? 50);

        if ($publishedToday >= $maxPerDay) {
            Logger::debug('Channel daily limit reached', [
                'channel_id' => $channel->id,
                'published_today' => $publishedToday,
                'max_per_day' => $maxPerDay,
            ]);
            return;
        }

        // Check publish interval
        if (!$this->canPublishNow($channel)) {
            Logger::debug('Channel publish interval not met', [
                'channel_id' => $channel->id,
                'interval_min' => $channel->publish_interval_min ?? 5,
            ]);
            return;
        }

        // Get validated versions for this channel
        $versions = $this->getVersionsToPublish($channel);

        if (empty($versions)) {
            Logger::debug('No validated versions for channel', ['channel_id' => $channel->id]);
            return;
        }

        // Get bot for rate limiting
        $bot = $channel->getBot();
        if (!$bot) {
            Logger::warning('Channel has no bot', ['channel_id' => $channel->id]);
            return;
        }

        // Remaining slots for today
        $remainingSlots = $maxPerDay - $publishedToday;
        $maxPerRun = (int)($channel->max_per_run ?? self::MAX_PER_RUN_DEFAULT);
        $limit = min($maxPerRun, $remainingSlots, count($versions));

        Logger::debug('Publishing for channel', [
            'channel_id' => $channel->id,
            'versions_available' => count($versions),
            'limit' => $limit,
        ]);

        $publishedInRun = 0;

        foreach ($versions as $version) {
            if ($publishedInRun >= $limit) {
                break;
            }

            // Graceful shutdown check
            if (ShutdownHandler::shouldShutdown()) {
                Logger::info('PublishStep: Shutdown during channel publishing');
                break;
            }

            // Circuit breaker check
            if (!CircuitBreaker::isAvailable('telegram')) {
                Logger::warning('PublishStep: Circuit breaker opened, stopping channel');
                break;
            }

            $this->total++;

            try {
                $this->publishVersion($version, $channel, $bot);
                $publishedInRun++;
            } catch (\Throwable $e) {
                Logger::error('PublishStep: Version publish error', [
                    'version_id' => $version->id,
                    'error' => $e->getMessage(),
                ]);
                $this->failed++;
            }
        }
    }

    /**
     * Check if channel is within active hours (in channel timezone).
     */
    private function isWithinActiveHours(Channel $channel): bool
    {
        $timezone = $channel->timezone ?? 'UTC';

        try {
            $tz = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            Logger::warning('Invalid channel timezone, using UTC', [
                'channel_id' => $channel->id,
                'timezone' => $timezone,
            ]);
            $tz = new \DateTimeZone('UTC');
        }

        $now = new \DateTimeImmutable('now', $tz);
        $currentTime = $now->format('H:i:s');

        $start = $channel->active_hours_start ?? '08:00:00';
        $end = $channel->active_hours_end ?? '22:00:00';

        // Handle overnight periods (e.g., 22:00 - 06:00)
        if ($start > $end) {
            // Active from start to midnight OR from midnight to end
            return $currentTime >= $start || $currentTime <= $end;
        }

        return $currentTime >= $start && $currentTime <= $end;
    }

    /**
     * Check if enough time has passed since last publication.
     */
    private function canPublishNow(Channel $channel): bool
    {
        $intervalMin = (int)($channel->publish_interval_min ?? 5);

        $lastPublished = Database::fetchOne(
            "SELECT published_at FROM article_versions
             WHERE channel_id = ? AND status = 'published'
             ORDER BY published_at DESC LIMIT 1",
            [$channel->id]
        );

        if (!$lastPublished || empty($lastPublished['published_at'])) {
            return true;
        }

        $lastTime = strtotime($lastPublished['published_at']);
        $elapsed = time() - $lastTime;

        return $elapsed >= ($intervalMin * 60);
    }

    /**
     * Get validated versions for publication with deduplication.
     */
    private function getVersionsToPublish(Channel $channel): array
    {
        $minImportance = (int)($channel->min_importance_score ?? 1);

        // Deduplication level 3: exclude articles/clusters already published to this channel
        $sql = "SELECT av.*, a.importance_score, a.created_at as article_created_at
                FROM article_versions av
                JOIN articles a ON a.id = av.article_id
                WHERE av.channel_id = ?
                  AND av.status = 'validated'
                  AND av.importance_score >= ?
                  AND a.status NOT IN ('duplicate', 'expired', 'cancelled')
                  AND NOT EXISTS (
                      SELECT 1 FROM article_versions av2
                      WHERE av2.channel_id = av.channel_id
                        AND av2.status = 'published'
                        AND (
                            av2.article_id = av.article_id
                            OR (a.cluster_id IS NOT NULL AND EXISTS (
                                SELECT 1 FROM articles a2
                                WHERE a2.id = av2.article_id
                                  AND a2.cluster_id = a.cluster_id
                            ))
                        )
                  )
                ORDER BY av.importance_score DESC, a.created_at ASC
                LIMIT 50";

        $rows = Database::fetchAll($sql, [$channel->id, $minImportance]);

        return array_map(fn($row) => new ArticleVersion($row), $rows);
    }

    /**
     * Publish a single version.
     */
    private function publishVersion(ArticleVersion $version, Channel $channel, Bot $bot): void
    {
        // Apply rate limiting
        TelegramRateLimiter::wait();
        $this->waitForBotRateLimit((int)$bot->id);

        // Get article
        $article = $version->getArticle();
        if (!$article) {
            Logger::warning('Version has no article', ['version_id' => $version->id]);
            $this->markVersionFailed($version, 'Article not found');
            return;
        }

        // Mark as publishing
        ArticleVersion::update((int)$version->id, ['status' => 'publishing']);

        try {
            $messageId = $this->publisher->publish($version, $article, $channel);

            if ($messageId) {
                // Success - update version
                ArticleVersion::update((int)$version->id, [
                    'telegram_message_id' => $messageId,
                    'status' => 'published',
                    'published_at' => date('Y-m-d H:i:s'),
                ]);

                // Update article status
                $article->changeStatus('published', [
                    'action' => 'auto_publish',
                    'channel_id' => $channel->id,
                    'message_id' => $messageId,
                ]);

                CircuitBreaker::recordSuccess('telegram');
                $this->published++;

                Logger::info('Published article', [
                    'version_id' => $version->id,
                    'article_id' => $article->id,
                    'channel_id' => $channel->id,
                    'message_id' => $messageId,
                ]);
            } else {
                // Soft failure (temporary)
                $this->markVersionFailed($version, 'Publisher returned null');
                CircuitBreaker::recordFailure('telegram');
            }

        } catch (TelegramException $e) {
            CircuitBreaker::recordFailure('telegram');

            if ($e->isPermanent()) {
                // Permanent error - don't retry
                ArticleVersion::update((int)$version->id, [
                    'status' => 'cancelled',
                    'validation_notes' => 'Telegram error: ' . $e->getMessage(),
                ]);
                $this->failed++;

                Logger::error('Permanent Telegram error', [
                    'version_id' => $version->id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);
            } else {
                // Temporary error - can retry
                $this->markVersionFailed($version, $e->getMessage());
            }
        }
    }

    /**
     * Mark version as failed for retry.
     */
    private function markVersionFailed(ArticleVersion $version, string $reason): void
    {
        $retryCount = (int)($version->retry_count ?? 0) + 1;

        ArticleVersion::update((int)$version->id, [
            'status' => 'failed',
            'retry_count' => $retryCount,
            'validation_notes' => 'Publish failed: ' . $reason,
        ]);

        $this->failed++;

        Logger::warning('Version marked as failed', [
            'version_id' => $version->id,
            'retry_count' => $retryCount,
            'reason' => $reason,
        ]);
    }

    /**
     * Wait for per-bot rate limit.
     * Ensures minimum delay between messages from the same bot.
     */
    private function waitForBotRateLimit(int $botId): void
    {
        $now = microtime(true);
        $lastSend = $this->lastSendTime[$botId] ?? 0.0;
        $elapsed = $now - $lastSend;

        if ($elapsed < self::MIN_BOT_INTERVAL_SEC) {
            $wait = (int)ceil(self::MIN_BOT_INTERVAL_SEC - $elapsed);
            Logger::debug('Bot rate limit: waiting', [
                'bot_id' => $botId,
                'wait_sec' => $wait,
            ]);
            sleep($wait);
        }

        $this->lastSendTime[$botId] = microtime(true);
    }

    /**
     * Retry failed publications older than RETRY_WAIT_MINUTES.
     */
    private function retryFailedPublications(): void
    {
        $sql = "SELECT * FROM article_versions
                WHERE status = 'failed'
                  AND updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
                  AND retry_count < ?
                ORDER BY updated_at ASC
                LIMIT 20";

        $rows = Database::fetchAll($sql, [self::RETRY_WAIT_MINUTES, self::RETRY_MAX_COUNT]);

        if (empty($rows)) {
            return;
        }

        Logger::info('Retrying failed publications', ['count' => count($rows)]);

        foreach ($rows as $row) {
            // Graceful shutdown check
            if (ShutdownHandler::shouldShutdown()) {
                break;
            }

            // Circuit breaker check
            if (!CircuitBreaker::isAvailable('telegram')) {
                break;
            }

            $version = new ArticleVersion($row);
            $article = $version->getArticle();
            $channel = $version->getChannel();

            if (!$article || !$channel) {
                Logger::warning('Cannot retry: missing article or channel', [
                    'version_id' => $version->id,
                ]);
                continue;
            }

            $bot = $channel->getBot();
            if (!$bot || !$bot->isActive()) {
                continue;
            }

            try {
                // Apply rate limiting
                TelegramRateLimiter::wait();
                $this->waitForBotRateLimit((int)$bot->id);

                $messageId = $this->publisher->publish($version, $article, $channel);

                if ($messageId) {
                    ArticleVersion::update((int)$version->id, [
                        'telegram_message_id' => $messageId,
                        'status' => 'published',
                        'published_at' => date('Y-m-d H:i:s'),
                    ]);

                    // Update article status
                    if ($article) {
                        $article->changeStatus('published', [
                            'action' => 'auto_publish_retry',
                            'channel_id' => $channel->id,
                            'message_id' => $messageId,
                        ]);
                    }

                    CircuitBreaker::recordSuccess('telegram');
                    $this->retried++;

                    Logger::info('Retry successful', [
                        'version_id' => $version->id,
                        'message_id' => $messageId,
                    ]);
                } else {
                    // Increment retry count
                    ArticleVersion::update((int)$version->id, [
                        'retry_count' => (int)$version->retry_count + 1,
                    ]);
                    CircuitBreaker::recordFailure('telegram');
                }

            } catch (TelegramException $e) {
                CircuitBreaker::recordFailure('telegram');

                ArticleVersion::update((int)$version->id, [
                    'retry_count' => (int)$version->retry_count + 1,
                    'validation_notes' => 'Retry failed: ' . $e->getMessage(),
                ]);

                Logger::warning('Retry failed', [
                    'version_id' => $version->id,
                    'error' => $e->getMessage(),
                ]);

                // On permanent error, mark as cancelled
                if ($e->isPermanent()) {
                    ArticleVersion::update((int)$version->id, [
                        'status' => 'cancelled',
                    ]);
                }

            } catch (\Throwable $e) {
                ArticleVersion::update((int)$version->id, [
                    'retry_count' => (int)$version->retry_count + 1,
                ]);

                Logger::error('Retry error', [
                    'version_id' => $version->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Log pipeline run start.
     */
    private function logPipelineStart(array $channelIds): int
    {
        return Database::insert('pipeline_runs', [
            'step' => 'publish',
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
                'articles_ok' => $this->published,
                'articles_failed' => $this->failed,
            ], 'id = ?', [$runId]);
        } catch (\Throwable $e) {
            Logger::warning('Failed to log pipeline finish', ['error' => $e->getMessage()]);
        }
    }
}
