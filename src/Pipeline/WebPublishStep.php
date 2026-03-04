<?php declare(strict_types=1);

namespace NewsBot\Pipeline;

use NewsBot\Core\{CircuitBreaker, Database, Logger, ShutdownHandler};
use NewsBot\Models\{Article, ArticleVersion, WebsiteArticleVersion, WebsiteEndpoint};
use NewsBot\Services\{RestPublisher, RestException};

/**
 * Pipeline Step: Publish validated articles to website endpoints via REST.
 *
 * Works independently from Telegram publishing.
 * Reads validated article_versions from the linked source channel,
 * then publishes them to website REST APIs.
 */
class WebPublishStep
{
    private RestPublisher $publisher;

    private int $total     = 0;
    private int $published = 0;
    private int $failed    = 0;
    private int $skipped   = 0;
    private int $retried   = 0;

    private const MAX_PER_RUN_DEFAULT  = 5;
    private const RETRY_MAX_COUNT      = 3;
    private const RETRY_WAIT_MINUTES   = 30;

    public function __construct(?RestPublisher $publisher = null)
    {
        $this->publisher = $publisher ?? new RestPublisher();
    }

    /**
     * Run the web publish step.
     *
     * @param array $endpointIds Endpoint IDs to filter (empty = all active)
     */
    public function run(array $endpointIds = []): void
    {
        $startedAt = new \DateTimeImmutable();
        $runId     = $this->logPipelineStart($endpointIds);

        try {
            $endpoints = $this->getEndpointsToPublish($endpointIds);

            if (empty($endpoints)) {
                Logger::info('WebPublishStep: No active endpoints');
                return;
            }

            Logger::info('WebPublishStep: Starting', ['endpoints_count' => count($endpoints)]);

            foreach ($endpoints as $endpoint) {
                if (ShutdownHandler::shouldShutdown()) {
                    Logger::info('WebPublishStep: Shutdown requested, stopping');
                    break;
                }

                $circuitKey = 'rest_endpoint_' . $endpoint->id;
                if (!CircuitBreaker::isAvailable($circuitKey)) {
                    Logger::warning('WebPublishStep: Circuit breaker open', [
                        'endpoint_id' => $endpoint->id,
                    ]);
                    continue;
                }

                $this->publishForEndpoint($endpoint);
            }

            // Retry failed publications
            $this->retryFailed();

            Logger::info('WebPublishStep: Completed', [
                'total'     => $this->total,
                'published' => $this->published,
                'failed'    => $this->failed,
                'skipped'   => $this->skipped,
                'retried'   => $this->retried,
            ]);

        } finally {
            $this->logPipelineFinish($runId, $startedAt);
        }
    }

    /**
     * Get endpoints to process.
     */
    private function getEndpointsToPublish(array $endpointIds): array
    {
        if (!empty($endpointIds)) {
            $result = [];
            foreach ($endpointIds as $id) {
                $ep = WebsiteEndpoint::find((int)$id);
                if ($ep && $ep->isActive()) {
                    $result[] = $ep;
                }
            }
            return $result;
        }

        return WebsiteEndpoint::getActive();
    }

    /**
     * Publish articles for one endpoint.
     */
    private function publishForEndpoint(WebsiteEndpoint $endpoint): void
    {
        if (!$endpoint->isWithinActiveHours()) {
            Logger::debug('WebPublishStep: Endpoint outside active hours', [
                'endpoint_id' => $endpoint->id,
            ]);
            return;
        }

        $publishedToday = $endpoint->getPublishedTodayCount();
        $maxPerDay      = (int)($endpoint->max_per_day ?? 50);

        if ($publishedToday >= $maxPerDay) {
            Logger::debug('WebPublishStep: Daily limit reached', [
                'endpoint_id'     => $endpoint->id,
                'published_today' => $publishedToday,
                'max_per_day'     => $maxPerDay,
            ]);
            return;
        }

        if (!$endpoint->canPublishNow()) {
            Logger::debug('WebPublishStep: Publish interval not met', [
                'endpoint_id' => $endpoint->id,
            ]);
            return;
        }

        $versions = $this->getVersionsToPublish($endpoint);

        if (empty($versions)) {
            Logger::debug('WebPublishStep: No versions for endpoint', [
                'endpoint_id' => $endpoint->id,
            ]);
            return;
        }

        $remaining  = $maxPerDay - $publishedToday;
        $maxPerRun  = (int)($endpoint->max_per_run ?? self::MAX_PER_RUN_DEFAULT);
        $limit      = min($maxPerRun, $remaining, count($versions));

        Logger::debug('WebPublishStep: Publishing for endpoint', [
            'endpoint_id'        => $endpoint->id,
            'versions_available' => count($versions),
            'limit'              => $limit,
        ]);

        $publishedInRun = 0;

        foreach ($versions as $row) {
            if ($publishedInRun >= $limit) {
                break;
            }

            if (ShutdownHandler::shouldShutdown()) {
                break;
            }

            $circuitKey = 'rest_endpoint_' . $endpoint->id;
            if (!CircuitBreaker::isAvailable($circuitKey)) {
                Logger::warning('WebPublishStep: Circuit breaker opened mid-run', [
                    'endpoint_id' => $endpoint->id,
                ]);
                break;
            }

            $this->total++;

            try {
                $this->publishVersion($row, $endpoint);
                $publishedInRun++;
            } catch (\Throwable $e) {
                Logger::error('WebPublishStep: Version publish error', [
                    'article_id'  => $row['article_id'],
                    'endpoint_id' => $endpoint->id,
                    'error'       => $e->getMessage(),
                ]);
                $this->failed++;
            }
        }
    }

    /**
     * Get validated article_versions for the endpoint's source channel
     * that haven't been published to this website yet.
     */
    private function getVersionsToPublish(WebsiteEndpoint $endpoint): array
    {
        $channelId = (int)$endpoint->source_channel_id;

        // Get validated versions from source channel, excluding:
        // - articles already published (or skipped/cancelled) to THIS endpoint
        // - duplicate/expired/cancelled articles
        $sql = "SELECT av.*, a.url as article_url, a.cluster_id,
                       a.scraped_image_url, a.rss_image_url,
                       a.rss_pub_date, a.created_at as article_created_at,
                       a.source_id
                FROM article_versions av
                JOIN articles a ON a.id = av.article_id
                WHERE av.channel_id = ?
                  AND av.status IN ('validated', 'published')
                  AND a.status NOT IN ('duplicate', 'expired', 'cancelled')
                  AND NOT EXISTS (
                      SELECT 1 FROM website_article_versions wav
                      WHERE wav.article_id = av.article_id
                        AND wav.endpoint_id = ?
                        AND wav.status IN ('published', 'publishing', 'cancelled', 'skipped')
                  )
                ORDER BY av.importance_score DESC, a.created_at ASC
                LIMIT 50";

        return Database::fetchAll($sql, [$channelId, $endpoint->id]);
    }

    /**
     * Publish a single version row to the endpoint.
     */
    private function publishVersion(array $row, WebsiteEndpoint $endpoint): void
    {
        $articleId   = (int)$row['article_id'];
        $endpointId  = (int)$endpoint->id;
        $circuitKey  = 'rest_endpoint_' . $endpointId;

        // Get or create tracking record
        $webVersion = WebsiteArticleVersion::findOrCreate($articleId, $endpointId);

        // Get full models
        $version = ArticleVersion::findForArticleChannel($articleId, (int)$endpoint->source_channel_id);
        $article = Article::find($articleId);

        if (!$version || !$article) {
            Logger::warning('WebPublishStep: Missing version or article', [
                'article_id'  => $articleId,
                'endpoint_id' => $endpointId,
            ]);
            WebsiteArticleVersion::update((int)$webVersion->id, ['status' => 'skipped']);
            $this->skipped++;
            return;
        }

        // Mark as publishing
        WebsiteArticleVersion::update((int)$webVersion->id, ['status' => 'publishing']);

        try {
            $result = $this->publisher->publish($version, $article, $endpoint);

            WebsiteArticleVersion::update((int)$webVersion->id, [
                'status'       => 'published',
                'external_id'  => $result['external_id'],
                'external_url' => $result['external_url'],
                'last_error'   => null,
                'last_http_code' => null,
                'published_at' => date('Y-m-d H:i:s'),
            ]);

            CircuitBreaker::recordSuccess($circuitKey);
            $this->published++;

            Logger::info('WebPublishStep: Published', [
                'article_id'   => $articleId,
                'endpoint_id'  => $endpointId,
                'external_id'  => $result['external_id'],
                'external_url' => $result['external_url'],
            ]);

        } catch (RestException $e) {
            CircuitBreaker::recordFailure($circuitKey);

            if ($e->isPermanent()) {
                WebsiteArticleVersion::update((int)$webVersion->id, [
                    'status'        => 'cancelled',
                    'last_error'    => $e->getMessage(),
                    'last_http_code' => $e->getCode(),
                ]);
                $this->failed++;

                Logger::error('WebPublishStep: Permanent REST error', [
                    'article_id'  => $articleId,
                    'endpoint_id' => $endpointId,
                    'error'       => $e->getMessage(),
                    'http_code'   => $e->getCode(),
                ]);
            } else {
                $this->markFailed($webVersion, $e->getMessage(), $e->getCode());
            }
        }
    }

    /**
     * Mark a website version as failed for retry.
     */
    private function markFailed(WebsiteArticleVersion $webVersion, string $error, int $httpCode = 0): void
    {
        $retryCount = (int)($webVersion->retry_count ?? 0) + 1;

        WebsiteArticleVersion::update((int)$webVersion->id, [
            'status'         => 'failed',
            'retry_count'    => $retryCount,
            'last_error'     => $error,
            'last_http_code' => $httpCode ?: null,
        ]);

        $this->failed++;

        Logger::warning('WebPublishStep: Version marked failed', [
            'id'          => $webVersion->id,
            'retry_count' => $retryCount,
            'error'       => $error,
        ]);
    }

    /**
     * Retry failed publications that are old enough and haven't exceeded max retries.
     */
    private function retryFailed(): void
    {
        $sql = "SELECT wav.*, we.source_channel_id, we.id as ep_id
                FROM website_article_versions wav
                JOIN website_endpoints we ON we.id = wav.endpoint_id
                WHERE wav.status = 'failed'
                  AND wav.updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
                  AND wav.retry_count < we.max_retries
                ORDER BY wav.updated_at ASC
                LIMIT 20";

        $rows = Database::fetchAll($sql, [self::RETRY_WAIT_MINUTES]);

        if (empty($rows)) {
            return;
        }

        Logger::info('WebPublishStep: Retrying failed publications', ['count' => count($rows)]);

        foreach ($rows as $row) {
            if (ShutdownHandler::shouldShutdown()) {
                break;
            }

            $endpointId  = (int)$row['endpoint_id'];
            $articleId   = (int)$row['article_id'];
            $channelId   = (int)$row['source_channel_id'];
            $circuitKey  = 'rest_endpoint_' . $endpointId;

            if (!CircuitBreaker::isAvailable($circuitKey)) {
                continue;
            }

            $endpoint = WebsiteEndpoint::find($endpointId);
            $version  = ArticleVersion::findForArticleChannel($articleId, $channelId);
            $article  = Article::find($articleId);

            if (!$endpoint || !$version || !$article) {
                continue;
            }

            $webVersion = new WebsiteArticleVersion($row);

            try {
                $result = $this->publisher->publish($version, $article, $endpoint);

                WebsiteArticleVersion::update((int)$webVersion->id, [
                    'status'         => 'published',
                    'external_id'    => $result['external_id'],
                    'external_url'   => $result['external_url'],
                    'last_error'     => null,
                    'last_http_code' => null,
                    'published_at'   => date('Y-m-d H:i:s'),
                ]);

                CircuitBreaker::recordSuccess($circuitKey);
                $this->retried++;

                Logger::info('WebPublishStep: Retry succeeded', [
                    'article_id'  => $articleId,
                    'endpoint_id' => $endpointId,
                ]);

            } catch (RestException $e) {
                CircuitBreaker::recordFailure($circuitKey);

                $updates = [
                    'retry_count'    => (int)$webVersion->retry_count + 1,
                    'last_error'     => $e->getMessage(),
                    'last_http_code' => $e->getCode() ?: null,
                ];

                if ($e->isPermanent()) {
                    $updates['status'] = 'cancelled';
                } else {
                    $updates['status'] = 'failed';
                }

                WebsiteArticleVersion::update((int)$webVersion->id, $updates);

                Logger::warning('WebPublishStep: Retry failed', [
                    'article_id'  => $articleId,
                    'endpoint_id' => $endpointId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    private function logPipelineStart(array $endpointIds): int
    {
        return Database::insert('pipeline_runs', [
            'step'       => 'web_publish',
            'channel_ids' => empty($endpointIds) ? null : implode(',', $endpointIds),
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function logPipelineFinish(int $runId, \DateTimeImmutable $startedAt): void
    {
        $finishedAt = new \DateTimeImmutable();
        $durationMs = (int)(($finishedAt->getTimestamp() - $startedAt->getTimestamp()) * 1000);

        try {
            Database::update('pipeline_runs', [
                'finished_at'    => $finishedAt->format('Y-m-d H:i:s'),
                'duration_ms'    => $durationMs,
                'articles_total' => $this->total,
                'articles_ok'    => $this->published,
                'articles_failed' => $this->failed,
            ], 'id = ?', [$runId]);
        } catch (\Throwable $e) {
            Logger::warning('WebPublishStep: Failed to log finish', ['error' => $e->getMessage()]);
        }
    }
}
