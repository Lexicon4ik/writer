<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\Database;

/**
 * Channel-Source many-to-many relationship model.
 */
class ChannelSource extends BaseModel
{
    protected static string $table = 'channel_sources';

    /**
     * Get channel IDs linked to a source.
     *
     * @return int[] Array of channel_id
     */
    public static function getChannelIds(int $sourceId): array
    {
        $rows = Database::fetchAll(
            "SELECT channel_id FROM channel_sources WHERE source_id = ?",
            [$sourceId]
        );

        return array_map(fn($row) => (int)$row['channel_id'], $rows);
    }

    /**
     * Get source IDs linked to a channel.
     *
     * @return int[] Array of source_id
     */
    public static function getSourceIds(int $channelId): array
    {
        $rows = Database::fetchAll(
            "SELECT source_id FROM channel_sources WHERE channel_id = ?",
            [$channelId]
        );

        return array_map(fn($row) => (int)$row['source_id'], $rows);
    }

    /**
     * Check if a link exists between channel and source.
     */
    public static function linkExists(int $channelId, int $sourceId): bool
    {
        $row = Database::fetchOne(
            "SELECT 1 FROM channel_sources WHERE channel_id = ? AND source_id = ?",
            [$channelId, $sourceId]
        );

        return $row !== null;
    }

    /**
     * Create a link between channel and source.
     */
    public static function link(int $channelId, int $sourceId, int $priority = 0): void
    {
        Database::execute(
            "INSERT IGNORE INTO channel_sources (channel_id, source_id, priority) VALUES (?, ?, ?)",
            [$channelId, $sourceId, $priority]
        );
    }

    /**
     * Remove a link between channel and source.
     */
    public static function unlink(int $channelId, int $sourceId): void
    {
        Database::delete(
            'channel_sources',
            'channel_id = ? AND source_id = ?',
            [$channelId, $sourceId]
        );
    }

    /**
     * Get all links for a channel with source details.
     */
    public static function getWithSources(int $channelId): array
    {
        return Database::fetchAll(
            "SELECT cs.*, s.name as source_name, s.site_url, s.status as source_status
             FROM channel_sources cs
             JOIN sources s ON s.id = cs.source_id
             WHERE cs.channel_id = ?
             ORDER BY cs.priority DESC, s.name",
            [$channelId]
        );
    }
}
