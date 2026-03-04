<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\Database;

/**
 * Article status log model - tracks all status transitions.
 */
class StatusLog extends BaseModel
{
    protected static string $table = 'article_status_log';

    /**
     * Get the article for this log entry.
     */
    public function getArticle(): ?Article
    {
        return Article::find((int)$this->article_id);
    }

    /**
     * Get status history for an article.
     */
    public static function getHistoryForArticle(int $articleId, int $limit = 50): array
    {
        return self::all(
            'article_id = ?',
            [$articleId],
            'created_at DESC',
            $limit
        );
    }

    /**
     * Get details as array.
     */
    public function getDetails(): array
    {
        if (empty($this->details)) {
            return [];
        }

        $decoded = json_decode($this->details, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get recent status changes (for monitoring).
     */
    public static function getRecent(int $limit = 100): array
    {
        return self::all('1=1', [], 'created_at DESC', $limit);
    }

    /**
     * Count transitions to a specific status.
     */
    public static function countTransitionsTo(string $status, string $since = '1 hour'): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM article_status_log
             WHERE new_status = ?
               AND created_at > DATE_SUB(NOW(), INTERVAL {$since})",
            [$status]
        );

        return (int)($row['cnt'] ?? 0);
    }
}
