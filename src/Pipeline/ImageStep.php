<?php declare(strict_types=1);

namespace NewsBot\Pipeline;

use NewsBot\Core\{Database, Logger, ShutdownHandler};
use NewsBot\Models\{Article, ArticleVersion, Channel};
use NewsBot\Services\Image\ImageSelector;

/**
 * Pipeline Step 3.5: Select and attach images to processed article versions.
 * Runs AFTER ProcessStep and BEFORE PublishStep.
 *
 * Processes all article_versions in status 'pending' or 'validated' that
 * belong to channels with image_mode != 'disabled' and don't yet have an image.
 */
class ImageStep
{
    private ImageSelector $selector;

    private int $total     = 0;
    private int $attached  = 0;
    private int $skipped   = 0;
    private int $failed    = 0;

    public function __construct(?ImageSelector $selector = null)
    {
        $this->selector = $selector ?? new ImageSelector();
    }

    public function run(): void
    {
        $startedAt = new \DateTimeImmutable();
        $runId     = $this->logPipelineStart();

        try {
            $versions = $this->getVersionsNeedingImage();

            if (empty($versions)) {
                Logger::debug('ImageStep: no versions need images');
                return;
            }

            Logger::info('ImageStep: starting', ['count' => count($versions)]);

            foreach ($versions as $row) {
                if (ShutdownHandler::shouldShutdown()) {
                    Logger::info('ImageStep: shutdown requested');
                    break;
                }

                $this->total++;
                $this->processVersion($row);
            }

            Logger::info('ImageStep: completed', [
                'total'    => $this->total,
                'attached' => $this->attached,
                'skipped'  => $this->skipped,
                'failed'   => $this->failed,
            ]);

        } finally {
            $this->logPipelineFinish($runId, $startedAt);
        }
    }

    /**
     * Fetch versions that need image processing.
     * Joins channels to filter by image_mode and use_images.
     */
    private function getVersionsNeedingImage(): array
    {
        $sql = "SELECT av.id AS version_id, av.article_id, av.channel_id, av.image_meta
                FROM article_versions av
                JOIN channels c ON c.id = av.channel_id
                WHERE av.status IN ('pending', 'validated')
                  AND c.use_images = 1
                  AND (c.image_mode IS NULL OR c.image_mode != 'disabled')
                  AND NOT EXISTS (
                      SELECT 1 FROM article_version_images avi
                      WHERE avi.article_version_id = av.id
                  )
                ORDER BY av.id ASC
                LIMIT 100";

        return Database::fetchAll($sql);
    }

    private function processVersion(array $row): void
    {
        $versionId = (int)$row['version_id'];
        $articleId = (int)$row['article_id'];
        $channelId = (int)$row['channel_id'];

        $version = ArticleVersion::find($versionId);
        $article = Article::find($articleId);
        $channel = Channel::find($channelId);

        if (!$version || !$article || !$channel) {
            Logger::warning('ImageStep: missing records', ['version_id' => $versionId]);
            $this->skipped++;
            return;
        }

        try {
            $attached = $this->selector->select($version, $article, $channel);

            if ($attached) {
                $this->attached++;
            } else {
                $this->skipped++;
            }
        } catch (\Throwable $e) {
            Logger::error('ImageStep: error attaching image', [
                'version_id' => $versionId,
                'error'      => $e->getMessage(),
            ]);
            $this->failed++;
        }
    }

    private function logPipelineStart(): int
    {
        return Database::insert('pipeline_runs', [
            'step'       => 'image',
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
                'articles_ok'    => $this->attached,
                'articles_failed' => $this->failed,
            ], 'id = ?', [$runId]);
        } catch (\Throwable $e) {
            Logger::warning('ImageStep: failed to log pipeline finish', ['error' => $e->getMessage()]);
        }
    }
}
