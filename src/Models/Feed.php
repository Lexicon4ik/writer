<?php declare(strict_types=1);

namespace NewsBot\Models;

/**
 * RSS/Atom feed model.
 */
class Feed extends BaseModel
{
    protected static string $table = 'feeds';

    /**
     * Get source for this feed.
     */
    public function getSource(): ?Source
    {
        return Source::find((int)$this->source_id);
    }

    /**
     * Increment consecutive errors count.
     * Auto-disables feed after max_errors.
     */
    public function incrementErrors(string $errorMessage = ''): void
    {
        $newCount = (int)$this->consecutive_errors + 1;
        $maxErrors = (int)($this->max_errors ?? 5);

        $updates = [
            'consecutive_errors' => $newCount,
            'last_error' => $errorMessage ?: null,
        ];

        if ($newCount >= $maxErrors) {
            $updates['status'] = 'auto_disabled';
        }

        self::update($this->id, $updates);

        $this->consecutive_errors = $newCount;
        if ($newCount >= $maxErrors) {
            $this->status = 'auto_disabled';
        }
    }

    /**
     * Reset consecutive errors count.
     */
    public function resetErrors(): void
    {
        self::update($this->id, [
            'consecutive_errors' => 0,
            'last_error' => null,
            'last_fetched_at' => date('Y-m-d H:i:s'),
        ]);

        $this->consecutive_errors = 0;
        $this->last_error = null;
    }

    /**
     * Update last fetched timestamp.
     */
    public function markFetched(): void
    {
        self::update($this->id, [
            'last_fetched_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get all active feeds.
     */
    public static function getActive(): array
    {
        return self::all("status = 'active'");
    }

    /**
     * Check if feed is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if feed is auto-disabled.
     */
    public function isAutoDisabled(): bool
    {
        return $this->status === 'auto_disabled';
    }

    /**
     * Check if this feed is due for fetching based on its interval.
     * Returns true when fetch_interval_min is NULL (always fetch).
     */
    public function isDueForFetch(): bool
    {
        if ($this->fetch_interval_min === null) {
            return true;
        }
        if ($this->last_fetched_at === null) {
            return true;
        }
        $elapsed = time() - strtotime($this->last_fetched_at);
        return $elapsed >= (int)$this->fetch_interval_min * 60;
    }

    /**
     * Re-enable an auto-disabled feed.
     */
    public function reenable(): void
    {
        self::update($this->id, [
            'status' => 'active',
            'consecutive_errors' => 0,
            'last_error' => null,
        ]);

        $this->status = 'active';
        $this->consecutive_errors = 0;
    }
}
