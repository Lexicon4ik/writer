<?php declare(strict_types=1);

namespace NewsBot\Models;

/**
 * Article version model - processed article for a specific channel.
 */
class ArticleVersion extends BaseModel
{
    protected static string $table = 'article_versions';

    /**
     * Get the parent article.
     */
    public function getArticle(): ?Article
    {
        return Article::find((int)$this->article_id);
    }

    /**
     * Get the channel.
     */
    public function getChannel(): ?Channel
    {
        return Channel::find((int)$this->channel_id);
    }

    /**
     * Find version by article and channel.
     */
    public static function findForArticleChannel(int $articleId, int $channelId): ?self
    {
        $row = \NewsBot\Core\Database::fetchOne(
            "SELECT * FROM article_versions WHERE article_id = ? AND channel_id = ?",
            [$articleId, $channelId]
        );

        return $row ? new self($row) : null;
    }

    /**
     * Create version or return existing (race-condition safe).
     * Uses INSERT IGNORE to handle concurrent inserts.
     *
     * @return array{version: self, created: bool}
     */
    public static function findOrCreate(array $data): array
    {
        $articleId = (int)$data['article_id'];
        $channelId = (int)$data['channel_id'];

        // Try to insert (will be ignored if duplicate key)
        $result = \NewsBot\Core\Database::insertOrIgnore(static::$table, $data);

        if ($result['inserted']) {
            $data['id'] = $result['id'];
            return ['version' => new self($data), 'created' => true];
        }

        // Row exists, fetch it
        $existing = self::findForArticleChannel($articleId, $channelId);
        return ['version' => $existing, 'created' => false];
    }

    /**
     * Get versions pending publication.
     */
    public static function getPendingForChannel(int $channelId, int $limit = 10): array
    {
        return self::all(
            "channel_id = ? AND status IN ('pending', 'validated')",
            [$channelId],
            'created_at ASC',
            $limit
        );
    }

    /**
     * Get versions that failed and can be retried.
     */
    public static function getFailedRetryable(int $maxRetries = 3): array
    {
        return self::all(
            "status = 'failed' AND retry_count < ?",
            [$maxRetries],
            'updated_at ASC',
            50
        );
    }

    /**
     * Check if version is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if version needs review.
     */
    public function needsReview(): bool
    {
        return $this->status === 'manual_review';
    }

    /**
     * Get hashtags as array.
     */
    public function getHashtags(): array
    {
        if (empty($this->hashtags)) {
            return [];
        }

        $decoded = json_decode($this->hashtags, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get filter tags as array.
     */
    public function getFilterTags(): array
    {
        if (empty($this->filter_tags)) {
            return [];
        }

        $decoded = json_decode($this->filter_tags, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Increment retry count.
     */
    public function incrementRetry(): void
    {
        $newCount = (int)$this->retry_count + 1;
        self::update($this->id, ['retry_count' => $newCount]);
        $this->retry_count = $newCount;
    }

    /**
     * Mark as published.
     */
    public function markPublished(int $telegramMessageId): void
    {
        self::update($this->id, [
            'status' => 'published',
            'telegram_message_id' => $telegramMessageId,
            'published_at' => date('Y-m-d H:i:s'),
        ]);

        $this->status = 'published';
        $this->telegram_message_id = $telegramMessageId;
    }
}
