<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\Database;

/**
 * Website article version model.
 * Tracks publication status of an article to a specific website endpoint.
 */
class WebsiteArticleVersion extends BaseModel
{
    protected static string $table = 'website_article_versions';

    /**
     * Get the parent article.
     */
    public function getArticle(): ?Article
    {
        return Article::find((int)$this->article_id);
    }

    /**
     * Get the website endpoint.
     */
    public function getEndpoint(): ?WebsiteEndpoint
    {
        return WebsiteEndpoint::find((int)$this->endpoint_id);
    }

    /**
     * Find record for article+endpoint pair.
     */
    public static function findForArticleEndpoint(int $articleId, int $endpointId): ?self
    {
        $row = Database::fetchOne(
            "SELECT * FROM website_article_versions WHERE article_id = ? AND endpoint_id = ?",
            [$articleId, $endpointId]
        );
        return $row ? new self($row) : null;
    }

    /**
     * Race-condition-safe find or create using INSERT IGNORE.
     */
    public static function findOrCreate(int $articleId, int $endpointId): self
    {
        Database::query(
            "INSERT IGNORE INTO website_article_versions (article_id, endpoint_id, status)
             VALUES (?, ?, 'pending')",
            [$articleId, $endpointId]
        );

        return self::findForArticleEndpoint($articleId, $endpointId)
            ?? new self(['article_id' => $articleId, 'endpoint_id' => $endpointId, 'status' => 'pending']);
    }

    /**
     * Check if published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
