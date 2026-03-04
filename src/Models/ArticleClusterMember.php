<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\Database;

/**
 * Article cluster member model - links articles to duplicate clusters.
 */
class ArticleClusterMember extends BaseModel
{
    protected static string $table = 'article_cluster_members';

    /**
     * Find all members of a cluster.
     *
     * @return self[]
     */
    public static function findByCluster(int $clusterId): array
    {
        return self::all(
            'cluster_id = ?',
            [$clusterId],
            'added_at ASC'
        );
    }

    /**
     * Add an article to a cluster.
     */
    public static function addMember(int $clusterId, int $articleId, ?float $similarity = null): void
    {
        Database::execute(
            "INSERT IGNORE INTO article_cluster_members (cluster_id, article_id, similarity)
             VALUES (?, ?, ?)",
            [$clusterId, $articleId, $similarity]
        );

        // Update article count in cluster
        Database::execute(
            "UPDATE article_clusters SET article_count = article_count + 1 WHERE id = ?",
            [$clusterId]
        );
    }

    /**
     * Get the article for this member.
     */
    public function getArticle(): ?Article
    {
        return Article::find((int)$this->article_id);
    }

    /**
     * Get the cluster.
     */
    public function getCluster(): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM article_clusters WHERE id = ?",
            [$this->cluster_id]
        );
    }

    /**
     * Check if an article is in any cluster.
     */
    public static function isInCluster(int $articleId): bool
    {
        $row = Database::fetchOne(
            "SELECT 1 FROM article_cluster_members WHERE article_id = ?",
            [$articleId]
        );

        return $row !== null;
    }

    /**
     * Get cluster ID for an article.
     */
    public static function getClusterIdForArticle(int $articleId): ?int
    {
        $row = Database::fetchOne(
            "SELECT cluster_id FROM article_cluster_members WHERE article_id = ?",
            [$articleId]
        );

        return $row ? (int)$row['cluster_id'] : null;
    }

    /**
     * Create a new cluster with initial article.
     */
    public static function createCluster(int $primaryArticleId): int
    {
        $clusterId = Database::insert('article_clusters', [
            'primary_article_id' => $primaryArticleId,
            'article_count' => 1,
        ]);

        Database::insert('article_cluster_members', [
            'cluster_id' => $clusterId,
            'article_id' => $primaryArticleId,
            'similarity' => 1.0,
        ]);

        return $clusterId;
    }
}
