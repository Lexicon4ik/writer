<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\Database;
use NewsBot\Core\Logger;

/**
 * Article model - main pipeline entity.
 */
class Article extends BaseModel
{
    protected static string $table = 'articles';

    /**
     * Change article status with logging.
     * ALWAYS writes to article_status_log.
     *
     * @param string $newStatus New status
     * @param array $details Additional context for the log
     */
    public function changeStatus(string $newStatus, array $details = []): void
    {
        $oldStatus = $this->status;

        if ($oldStatus === $newStatus) {
            return; // No change needed
        }

        // Check if already in a transaction (to avoid nested transaction error)
        $ownTransaction = !Database::inTransaction();
        if ($ownTransaction) {
            Database::beginTransaction();
        }

        try {
            // Update article status
            Database::update(
                'articles',
                ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [$this->id]
            );

            // Log status change
            Database::insert('article_status_log', [
                'article_id' => $this->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'details' => !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            ]);

            if ($ownTransaction) {
                Database::commit();
            }

            $this->status = $newStatus;
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                Database::rollback();
            }
            Logger::error('Failed to change article status', [
                'article_id' => $this->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get all versions of this article.
     */
    public function getVersions(): array
    {
        return ArticleVersion::all('article_id = ?', [$this->id]);
    }

    /**
     * Get version for a specific channel.
     */
    public function getVersionForChannel(int $channelId): ?ArticleVersion
    {
        return ArticleVersion::findBy('article_id', $this->id)
            ? ArticleVersion::all('article_id = ? AND channel_id = ?', [$this->id, $channelId])[0] ?? null
            : null;
    }

    /**
     * Get fingerprint for this article.
     */
    public function getFingerprint(): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM article_fingerprints WHERE article_id = ?",
            [$this->id]
        );
    }

    /**
     * Get cluster this article belongs to.
     */
    public function getCluster(): ?array
    {
        if (!$this->cluster_id) {
            return null;
        }

        return Database::fetchOne(
            "SELECT * FROM article_clusters WHERE id = ?",
            [$this->cluster_id]
        );
    }

    /**
     * Get source for this article.
     */
    public function getSource(): ?Source
    {
        return Source::find((int)$this->source_id);
    }

    /**
     * Get feed for this article (null for custom parser sources).
     */
    public function getFeed(): ?Feed
    {
        return $this->feed_id ? Feed::find((int)$this->feed_id) : null;
    }

    /**
     * Get status history.
     */
    public function getStatusHistory(int $limit = 50): array
    {
        return StatusLog::getHistoryForArticle((int)$this->id, $limit);
    }

    /**
     * Find article by URL hash.
     */
    public static function findByUrlHash(string $urlHash): ?self
    {
        return self::findBy('url_hash', $urlHash);
    }

    /**
     * Get articles by status.
     */
    public static function getByStatus(string $status, int $limit = 100): array
    {
        return self::all(
            'status = ?',
            [$status],
            'created_at ASC',
            $limit
        );
    }

    /**
     * Get articles that need scraping.
     */
    public static function getNeedingScrape(int $limit = 50): array
    {
        return self::all(
            "status = 'fetched'",
            [],
            'created_at ASC',
            $limit
        );
    }

    /**
     * Get articles that need processing.
     */
    public static function getNeedingProcess(int $limit = 50): array
    {
        return self::all(
            "status = 'scraped'",
            [],
            'created_at ASC',
            $limit
        );
    }

    /**
     * Check if article is duplicate.
     */
    public function isDuplicate(): bool
    {
        return $this->status === 'duplicate';
    }

    /**
     * Get title (scraped or RSS).
     */
    public function getTitle(): string
    {
        return $this->scraped_title ?? $this->rss_title ?? '';
    }

    /**
     * Get text content (scraped or RSS).
     */
    public function getText(): string
    {
        return $this->scraped_text ?? $this->rss_content ?? $this->rss_description ?? '';
    }

    /**
     * Get image URL (scraped or RSS).
     */
    public function getImageUrl(): ?string
    {
        return $this->scraped_image_url ?? $this->rss_image_url;
    }
}
