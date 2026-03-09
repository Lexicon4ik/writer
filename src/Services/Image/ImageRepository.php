<?php declare(strict_types=1);

namespace NewsBot\Services\Image;

use NewsBot\Core\{Database, Logger};

/**
 * CRUD for the `images` and `article_version_images` tables.
 */
class ImageRepository
{
    /**
     * Find image by SHA-256 hash (deduplication).
     */
    public function findByHash(string $hash): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM images WHERE file_hash = ?",
            [$hash]
        );
    }

    /**
     * Insert or return existing image record.
     * Uses file_hash as dedup key.
     *
     * @param array $data Must contain: source, file_path, file_hash, mime_type
     * @return int image.id
     */
    public function upsert(array $data): int
    {
        $existing = $this->findByHash($data['file_hash']);
        if ($existing) {
            return (int)$existing['id'];
        }

        return Database::insert('images', array_filter([
            'source'        => $data['source'],
            'external_id'   => $data['external_id'] ?? null,
            'file_path'     => $data['file_path'],
            'file_hash'     => $data['file_hash'],
            'width'         => $data['width'] ?? null,
            'height'        => $data['height'] ?? null,
            'file_size'     => $data['file_size'] ?? null,
            'mime_type'     => $data['mime_type'] ?? null,
            'category'      => $data['category'] ?? null,
            'entities'      => isset($data['entities']) ? json_encode($data['entities'], JSON_UNESCAPED_UNICODE) : null,
            'tags'          => isset($data['tags']) ? json_encode($data['tags'], JSON_UNESCAPED_UNICODE) : null,
            'alt_text'      => $data['alt_text'] ?? null,
            'license_type'  => $data['license_type'] ?? null,
            'license_url'   => $data['license_url'] ?? null,
            'photographer'  => $data['photographer'] ?? null,
            'source_url'    => $data['source_url'] ?? null,
        ], fn($v) => $v !== null));
    }

    /**
     * Link an image to an article version.
     * Silently ignores if the (version, position) pair already exists.
     */
    public function linkToVersion(
        int    $versionId,
        int    $imageId,
        string $method,
        int    $position = 1,
        ?float $similarity = null
    ): void {
        try {
            Database::execute(
                "INSERT IGNORE INTO article_version_images
                    (article_version_id, image_id, position, selection_method, similarity_score)
                 VALUES (?, ?, ?, ?, ?)",
                [$versionId, $imageId, $position, $method, $similarity]
            );
        } catch (\Throwable $e) {
            Logger::warning('ImageRepository: linkToVersion failed', [
                'version_id' => $versionId,
                'image_id'   => $imageId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get primary image record for an article version.
     */
    public function getPrimaryForVersion(int $versionId): ?array
    {
        return Database::fetchOne(
            "SELECT i.*, avi.selection_method, avi.position
             FROM article_version_images avi
             JOIN images i ON i.id = avi.image_id
             WHERE avi.article_version_id = ? AND avi.position = 1
             LIMIT 1",
            [$versionId]
        );
    }

    /**
     * Check whether a version already has an image assigned.
     */
    public function hasImage(int $versionId): bool
    {
        $row = Database::fetchOne(
            "SELECT 1 FROM article_version_images WHERE article_version_id = ? LIMIT 1",
            [$versionId]
        );
        return $row !== null;
    }

    /**
     * Find images by category for library mode.
     * Returns images ordered by least-used first to distribute usage evenly.
     * Excludes images already attached to the given article version.
     *
     * @return array[] Array of image rows
     */
    public function findByCategory(string $category, int $versionId, int $limit = 5): array
    {
        return Database::fetchAll(
            "SELECT i.*
             FROM images i
             WHERE i.category = ?
               AND i.id NOT IN (
                   SELECT avi.image_id
                   FROM article_version_images avi
                   WHERE avi.article_version_id = ?
               )
             ORDER BY i.usage_count ASC, i.last_used_at ASC
             LIMIT ?",
            [$category, $versionId, $limit]
        );
    }

    /**
     * Increment usage_count and update last_used_at for an image.
     */
    public function markUsed(int $imageId): void
    {
        Database::execute(
            "UPDATE images SET usage_count = usage_count + 1, last_used_at = NOW() WHERE id = ?",
            [$imageId]
        );
    }

    /**
     * Log an image search query.
     */
    public function logSearch(
        ?int   $versionId,
        string $query,
        string $source,
        int    $resultsCount,
        ?int   $selectedImageId,
        int    $durationMs
    ): void {
        try {
            Database::insert('image_search_log', [
                'article_version_id' => $versionId,
                'query'              => mb_substr($query, 0, 500),
                'source'             => $source,
                'results_count'      => $resultsCount,
                'selected_image_id'  => $selectedImageId,
                'duration_ms'        => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('ImageRepository: logSearch failed', ['error' => $e->getMessage()]);
        }
    }
}
