<?php declare(strict_types=1);

namespace NewsBot\Models;

/**
 * Scrape rule model for content extraction.
 */
class ScrapeRule extends BaseModel
{
    protected static string $table = 'scrape_rules';

    /**
     * Get source for this rule (null for universal rules).
     */
    public function getSource(): ?Source
    {
        return $this->source_id ? Source::find((int)$this->source_id) : null;
    }

    /**
     * Check if this is a universal rule.
     */
    public function isUniversal(): bool
    {
        return $this->source_id === null;
    }

    /**
     * Get remove selectors as array.
     */
    public function getRemoveSelectors(): array
    {
        if (empty($this->remove_selectors)) {
            return [];
        }

        $decoded = json_decode($this->remove_selectors, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get rules for a specific source.
     */
    public static function getForSource(int $sourceId): array
    {
        return self::all(
            'source_id = ? OR source_id IS NULL',
            [$sourceId],
            'CASE WHEN source_id IS NULL THEN 1 ELSE 0 END, priority DESC'
        );
    }

    /**
     * Get universal rules only.
     */
    public static function getUniversal(): array
    {
        return self::all('source_id IS NULL', [], 'priority DESC');
    }
}
