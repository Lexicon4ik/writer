<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\Database;

/**
 * Custom parser configuration for sources without RSS.
 */
class SourceParser extends BaseModel
{
    protected static string $table = 'source_parsers';

    /**
     * Get the source for this parser.
     */
    public function getSource(): ?Source
    {
        return Source::find((int)$this->source_id);
    }

    /**
     * Increment errors with auto-disable.
     * After max_errors, sets is_active = 0.
     */
    public function incrementErrors(string $errorMessage): void
    {
        $newCount = (int)$this->consecutive_errors + 1;
        $maxErrors = (int)($this->max_errors ?? 5);

        $updates = [
            'consecutive_errors' => $newCount,
            'last_error' => $errorMessage,
        ];

        if ($newCount >= $maxErrors) {
            $updates['is_active'] = 0;
        }

        self::update($this->id, $updates);

        $this->consecutive_errors = $newCount;
        if ($newCount >= $maxErrors) {
            $this->is_active = 0;
        }
    }

    /**
     * Reset errors on successful parse.
     * Updates last_parsed_at and last_articles_count.
     */
    public function resetErrors(int $articlesCount): void
    {
        $updates = [
            'consecutive_errors' => 0,
            'last_error' => null,
            'last_parsed_at' => date('Y-m-d H:i:s'),
            'last_articles_count' => $articlesCount,
        ];

        // Track zero article runs
        if ($articlesCount === 0) {
            $updates['consecutive_zero_articles'] = (int)$this->consecutive_zero_articles + 1;
        } else {
            $updates['consecutive_zero_articles'] = 0;
        }

        self::update($this->id, $updates);

        $this->consecutive_errors = 0;
        $this->last_articles_count = $articlesCount;
    }

    /**
     * Get exclude patterns as PHP array.
     */
    public function getExcludePatterns(): array
    {
        if (empty($this->exclude_patterns)) {
            return [];
        }

        $decoded = json_decode($this->exclude_patterns, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Find parser by source_id.
     */
    public static function findForSource(int $sourceId): ?self
    {
        return self::findBy('source_id', $sourceId);
    }

    /**
     * Get all active parsers.
     */
    public static function getActive(): array
    {
        return self::all('is_active = 1', [], 'id ASC');
    }

    /**
     * Create a copy with limited max_pages (for testing).
     */
    public function withMaxPages(int $maxPages): self
    {
        $data = $this->toArray();
        $data['max_pages'] = $maxPages;
        return new self($data);
    }

    /**
     * Check if this parser is due for a run based on its interval.
     * Returns true when fetch_interval_min is NULL (always run).
     */
    public function isDueForFetch(): bool
    {
        if ($this->fetch_interval_min === null) {
            return true;
        }
        if ($this->last_parsed_at === null) {
            return true;
        }
        $elapsed = time() - strtotime($this->last_parsed_at);
        return $elapsed >= (int)$this->fetch_interval_min * 60;
    }

    /**
     * Check if parser is active.
     */
    public function isActive(): bool
    {
        return (bool)$this->is_active;
    }

    /**
     * Get base URL for link resolution.
     */
    public function getBaseUrl(): string
    {
        return $this->link_base_url ?? dirname($this->list_url);
    }
}
