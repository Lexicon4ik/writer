<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\Database;

/**
 * Telegram channel model.
 */
class Channel extends BaseModel
{
    protected static string $table = 'channels';

    /**
     * Get the bot for this channel.
     */
    public function getBot(): ?Bot
    {
        return Bot::find((int)$this->bot_id);
    }

    /**
     * Get sources linked to this channel.
     */
    public function getSources(): array
    {
        $sql = "SELECT s.* FROM sources s
                INNER JOIN channel_sources cs ON cs.source_id = s.id
                WHERE cs.channel_id = ?
                ORDER BY cs.priority DESC, s.name ASC";

        $rows = Database::fetchAll($sql, [$this->id]);
        return array_map(fn($row) => new Source($row), $rows);
    }

    /**
     * Get active feeds for this channel (via sources).
     */
    public function getActiveFeeds(): array
    {
        $sql = "SELECT f.* FROM feeds f
                INNER JOIN sources s ON s.id = f.source_id
                INNER JOIN channel_sources cs ON cs.source_id = s.id
                WHERE cs.channel_id = ?
                  AND s.status = 'active'
                  AND f.status = 'active'
                  AND (f.fetch_interval_min IS NULL
                       OR f.last_fetched_at IS NULL
                       OR f.last_fetched_at <= NOW() - INTERVAL f.fetch_interval_min MINUTE)
                ORDER BY f.id";

        $rows = Database::fetchAll($sql, [$this->id]);
        return array_map(fn($row) => new Feed($row), $rows);
    }

    /**
     * Get all active channels.
     */
    public static function getActive(): array
    {
        return self::all("status = 'active'", [], 'name ASC');
    }

    /**
     * Check if channel is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if channel is within active hours.
     */
    public function isWithinActiveHours(\DateTimeInterface $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable();

        // Convert to channel timezone
        $tz = new \DateTimeZone($this->timezone ?? 'UTC');
        $localTime = $now->setTimezone($tz);

        $currentTime = $localTime->format('H:i:s');
        $start = $this->active_hours_start ?? '08:00:00';
        $end = $this->active_hours_end ?? '22:00:00';

        return $currentTime >= $start && $currentTime <= $end;
    }

    /**
     * Get count of articles published today.
     */
    public function getPublishedTodayCount(): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM article_versions
             WHERE channel_id = ?
               AND status = 'published'
               AND DATE(published_at) = CURDATE()",
            [$this->id]
        );

        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Check if manual review is enabled.
     */
    public function isManualReviewEnabled(): bool
    {
        return (bool)$this->manual_review_enabled;
    }

    /**
     * Get prompt hash for version tracking.
     */
    public function getPromptVersion(): string
    {
        return md5($this->ai_prompt ?? '');
    }
}
