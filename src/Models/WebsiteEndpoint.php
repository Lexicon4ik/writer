<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\{Crypto, Database};

/**
 * Website REST endpoint model.
 * Represents a website that receives articles via REST API.
 */
class WebsiteEndpoint extends BaseModel
{
    protected static string $table = 'website_endpoints';

    /**
     * Get the source channel (whose article_versions we publish).
     */
    public function getSourceChannel(): ?Channel
    {
        return Channel::find((int)$this->source_channel_id);
    }

    /**
     * Get all active endpoints.
     */
    public static function getActive(): array
    {
        return self::all("status = 'active'", [], 'name ASC');
    }

    /**
     * Check if endpoint is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get decrypted auth credential.
     */
    public function getCredential(): string
    {
        if (empty($this->auth_credential)) {
            return '';
        }
        try {
            return Crypto::decrypt($this->auth_credential);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Get field_mapping as array.
     */
    public function getFieldMapping(): array
    {
        $mapping = $this->field_mapping;
        if (is_string($mapping)) {
            return json_decode($mapping, true) ?? [];
        }
        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Get payload_extras as array.
     */
    public function getPayloadExtras(): array
    {
        $extras = $this->payload_extras;
        if (empty($extras)) {
            return [];
        }
        if (is_string($extras)) {
            return json_decode($extras, true) ?? [];
        }
        return is_array($extras) ? $extras : [];
    }

    /**
     * Get success HTTP codes as int array.
     */
    public function getSuccessHttpCodes(): array
    {
        $codes = $this->success_http_codes ?? '200,201';
        return array_map('intval', array_filter(explode(',', $codes)));
    }

    /**
     * Get retry HTTP codes as int array.
     */
    public function getRetryHttpCodes(): array
    {
        $codes = $this->retry_http_codes ?? '429,500,502,503,504';
        return array_map('intval', array_filter(explode(',', $codes)));
    }

    /**
     * Count articles published today.
     */
    public function getPublishedTodayCount(): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM website_article_versions
             WHERE endpoint_id = ?
               AND status = 'published'
               AND DATE(published_at) = CURDATE()",
            [$this->id]
        );
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Check if endpoint is within active hours (UTC-based).
     */
    public function isWithinActiveHours(): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $currentTime = $now->format('H:i:s');
        $start = $this->active_hours_start ?? '08:00:00';
        $end   = $this->active_hours_end   ?? '22:00:00';

        if ($start > $end) {
            return $currentTime >= $start || $currentTime <= $end;
        }
        return $currentTime >= $start && $currentTime <= $end;
    }

    /**
     * Check if enough time has passed since last publication.
     */
    public function canPublishNow(): bool
    {
        $intervalMin = (int)($this->publish_interval_min ?? 30);

        $last = Database::fetchOne(
            "SELECT published_at FROM website_article_versions
             WHERE endpoint_id = ? AND status = 'published'
             ORDER BY published_at DESC LIMIT 1",
            [$this->id]
        );

        if (!$last || empty($last['published_at'])) {
            return true;
        }

        $elapsed = time() - strtotime($last['published_at']);
        return $elapsed >= ($intervalMin * 60);
    }
}
