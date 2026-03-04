<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\Database;

/**
 * News source model.
 */
class Source extends BaseModel
{
    protected static string $table = 'sources';

    /**
     * Get feeds for this source.
     */
    public function getFeeds(): array
    {
        return Feed::all('source_id = ?', [$this->id]);
    }

    /**
     * Get active feeds for this source.
     */
    public function getActiveFeeds(): array
    {
        return Feed::all("source_id = ? AND status = 'active'", [$this->id]);
    }

    /**
     * Get scrape rules for this source, sorted by priority DESC.
     */
    public function getScrapeRules(): array
    {
        // Get source-specific rules first, then universal rules
        return ScrapeRule::all(
            'source_id = ? OR source_id IS NULL',
            [$this->id],
            'CASE WHEN source_id IS NULL THEN 1 ELSE 0 END, priority DESC'
        );
    }

    /**
     * Get channels that use this source.
     */
    public function getChannels(): array
    {
        $sql = "SELECT c.* FROM channels c
                INNER JOIN channel_sources cs ON cs.channel_id = c.id
                WHERE cs.source_id = ?
                ORDER BY c.name";

        $rows = Database::fetchAll($sql, [$this->id]);
        return array_map(fn($row) => new Channel($row), $rows);
    }

    /**
     * Get custom parser configuration if scrape_strategy = 'custom_parser'.
     */
    public function getParser(): ?SourceParser
    {
        if ($this->scrape_strategy !== 'custom_parser') {
            return null;
        }

        return SourceParser::findForSource((int)$this->id);
    }

    /**
     * Get all active sources.
     */
    public static function getActive(): array
    {
        return self::all("status = 'active'", [], 'name ASC');
    }

    /**
     * Check if source is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if source uses custom parser.
     */
    public function usesCustomParser(): bool
    {
        return $this->scrape_strategy === 'custom_parser';
    }

    /**
     * Check if source is RSS-only.
     */
    public function isRssOnly(): bool
    {
        return $this->scrape_strategy === 'rss_only';
    }

    /**
     * Get request delay in milliseconds.
     */
    public function getRequestDelayMs(): int
    {
        return (int)($this->request_delay_ms ?? 2000);
    }
}
