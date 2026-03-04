<?php declare(strict_types=1);

namespace NewsBot\Web\Controllers;

use NewsBot\Core\Database;
use NewsBot\Models\Article;
use NewsBot\Models\ArticleVersion;
use NewsBot\Models\Channel;
use NewsBot\Models\Source;
use NewsBot\Models\StatusLog;
use NewsBot\Services\Publisher;

/**
 * Controller for managing articles and their versions.
 */
class ArticleController extends BaseController
{
    /**
     * List articles with filters and pagination.
     */
    public function index(?int $id = null): void
    {
        // Build filter conditions
        $where = ['1=1'];
        $params = [];

        // Status filter
        $status = $_GET['status'] ?? '';
        if (!empty($status)) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }

        // Channel filter
        $channelId = (int)($_GET['channel_id'] ?? 0);
        if ($channelId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM article_versions av WHERE av.article_id = a.id AND av.channel_id = ?)';
            $params[] = $channelId;
        }

        // Source filter
        $sourceId = (int)($_GET['source_id'] ?? 0);
        if ($sourceId > 0) {
            $where[] = 'a.source_id = ?';
            $params[] = $sourceId;
        }

        // Date range filter
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        if (!empty($dateFrom)) {
            $where[] = 'a.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if (!empty($dateTo)) {
            $where[] = 'a.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        // Count query
        $countSql = "SELECT COUNT(*) as cnt FROM articles a WHERE {$whereClause}";

        // Data query - exclude heavy fields (scraped_text, rss_content)
        $dataSql = "
            SELECT
                a.id,
                a.url,
                COALESCE(a.scraped_title, a.rss_title) as title,
                a.source_id,
                a.status,
                (SELECT MAX(av2.importance_score) FROM article_versions av2 WHERE av2.article_id = a.id) as importance_score,
                a.cluster_id,
                a.created_at,
                s.name as source_name,
                (SELECT COUNT(*) FROM article_versions av WHERE av.article_id = a.id) as version_count
            FROM articles a
            LEFT JOIN sources s ON s.id = a.source_id
            WHERE {$whereClause}
            ORDER BY a.created_at DESC
        ";

        $result = $this->paginate($countSql, $dataSql, $params, 50);

        // Get filter options
        $channels = Database::fetchAll('SELECT id, name FROM channels ORDER BY name');
        $sources = Database::fetchAll('SELECT id, name FROM sources ORDER BY name');
        $statuses = Database::fetchAll('SELECT DISTINCT status FROM articles ORDER BY status');

        $this->render('articles/index', [
            'pageTitle' => 'Статьи',
            'articles' => $result['items'],
            'pagination' => $result['pagination'],
            'channels' => $channels,
            'sources' => $sources,
            'statuses' => array_column($statuses, 'status'),
            'filters' => [
                'status' => $status,
                'channel_id' => $channelId,
                'source_id' => $sourceId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * View single article with versions and history.
     */
    public function view(?int $id = null): void
    {
        $id = (int)($_GET['id'] ?? $id ?? 0);
        $article = Article::find($id);

        if (!$article) {
            $this->setFlash('danger', 'Статья не найдена');
            $this->redirect('?page=articles');
            return;
        }

        // Get source
        $source = $article->getSource();

        // Get all versions with channel info
        $versions = Database::fetchAll('
            SELECT
                av.*,
                c.name as channel_name,
                c.post_template
            FROM article_versions av
            LEFT JOIN channels c ON c.id = av.channel_id
            WHERE av.article_id = ?
            ORDER BY av.created_at DESC
        ', [$id]);

        // Get status history
        $statusHistory = StatusLog::getHistoryForArticle($id, 50);

        // Get cluster info if exists
        $cluster = null;
        $clusterArticles = [];
        if ($article->cluster_id) {
            $cluster = Database::fetchOne('
                SELECT * FROM article_clusters WHERE id = ?
            ', [$article->cluster_id]);

            if ($cluster) {
                $clusterArticles = Database::fetchAll('
                    SELECT
                        a.id,
                        COALESCE(a.scraped_title, a.rss_title) as title,
                        a.status,
                        a.created_at,
                        (a.id = ac.primary_article_id) as is_primary
                    FROM article_cluster_members acm
                    JOIN articles a ON a.id = acm.article_id
                    JOIN article_clusters ac ON ac.id = acm.cluster_id
                    WHERE acm.cluster_id = ?
                    ORDER BY is_primary DESC, a.created_at ASC
                ', [$article->cluster_id]);
            }
        }

        // Build post previews for each version
        $publisher = new Publisher();
        foreach ($versions as &$version) {
            $versionModel = new ArticleVersion($version);
            $channelModel = Channel::find((int)$version['channel_id']);
            if ($channelModel) {
                $version['post_preview'] = $publisher->buildPost($versionModel, $article, $channelModel);
            } else {
                $version['post_preview'] = '';
            }
        }

        $this->render('articles/view', [
            'pageTitle' => 'Статья #' . $id,
            'article' => $article,
            'source' => $source,
            'versions' => $versions,
            'statusHistory' => $statusHistory,
            'cluster' => $cluster,
            'clusterArticles' => $clusterArticles,
        ]);
    }

    /**
     * Reprocess single article.
     */
    public function reprocess(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? $id ?? 0);
        $article = Article::find($id);

        if (!$article) {
            $this->setFlash('danger', 'Статья не найдена');
            $this->redirect('?page=articles');
            return;
        }

        try {
            Database::beginTransaction();

            // Delete unpublished versions
            Database::execute(
                "DELETE FROM article_versions
                 WHERE article_id = ?
                   AND status NOT IN ('published', 'edited')",
                [$id]
            );

            // Reset article status to scraped
            $article->changeStatus('scraped', ['action' => 'manual_reprocess']);

            Database::commit();
            $this->setFlash('success', 'Статья отправлена на переобработку');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->setFlash('danger', 'Ошибка: ' . $e->getMessage());
        }

        $this->redirect("?page=articles&action=view&id={$id}");
    }

    /**
     * Manually publish a specific version.
     */
    public function publish_manual(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $articleId = (int)($_POST['id'] ?? $id ?? 0);
        $versionId = (int)($_POST['version_id'] ?? 0);

        $version = ArticleVersion::find($versionId);
        if (!$version || (int)$version->article_id !== $articleId) {
            $this->setFlash('danger', 'Версия статьи не найдена');
            $this->redirect('?page=articles');
            return;
        }

        $article = Article::find($articleId);
        $channel = Channel::find((int)$version->channel_id);

        if (!$article || !$channel) {
            $this->setFlash('danger', 'Статья или канал не найдены');
            $this->redirect('?page=articles');
            return;
        }

        try {
            $publisher = new Publisher();
            $messageId = $publisher->publish($version, $article, $channel);

            if ($messageId) {
                $version->markPublished($messageId);
                $article->changeStatus('published', [
                    'action' => 'manual_publish',
                    'channel_id' => $channel->id,
                    'message_id' => $messageId,
                ]);
                $this->setFlash('success', "Статья опубликована в канал {$channel->name}");
            } else {
                $this->setFlash('danger', 'Не удалось опубликовать статью');
            }
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'Ошибка публикации: ' . $e->getMessage());
        }

        $this->redirect("?page=articles&action=view&id={$articleId}");
    }

    /**
     * Cancel article and unpublished versions.
     */
    public function cancel(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? $id ?? 0);
        $article = Article::find($id);

        if (!$article) {
            $this->setFlash('danger', 'Статья не найдена');
            $this->redirect('?page=articles');
            return;
        }

        try {
            Database::beginTransaction();

            // Cancel unpublished versions
            Database::execute(
                "UPDATE article_versions
                 SET status = 'cancelled'
                 WHERE article_id = ?
                   AND status NOT IN ('published', 'edited', 'deleted')",
                [$id]
            );

            // Cancel article
            $article->changeStatus('cancelled', ['action' => 'manual_cancel']);

            Database::commit();
            $this->setFlash('success', 'Статья отменена');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->setFlash('danger', 'Ошибка: ' . $e->getMessage());
        }

        $this->redirect("?page=articles&action=view&id={$id}");
    }

    /**
     * Edit post form.
     */
    public function edit_post(?int $id = null): void
    {
        $versionId = (int)($_GET['version_id'] ?? $_POST['version_id'] ?? 0);
        $version = ArticleVersion::find($versionId);

        if (!$version) {
            $this->setFlash('danger', 'Версия не найдена');
            $this->redirect('?page=articles');
            return;
        }

        $article = Article::find((int)$version->article_id);
        $channel = Channel::find((int)$version->channel_id);

        if (!$article || !$channel) {
            $this->setFlash('danger', 'Статья или канал не найдены');
            $this->redirect('?page=articles');
            return;
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $newTitle = trim($_POST['title'] ?? '');
            $newBody = trim($_POST['body'] ?? '');

            if (empty($newTitle) || empty($newBody)) {
                $this->setFlash('danger', 'Заголовок и текст обязательны');
                $this->redirect("?page=articles&action=edit_post&version_id={$versionId}");
                return;
            }

            try {
                // Update version
                ArticleVersion::update($versionId, [
                    'title' => $newTitle,
                    'body' => $newBody,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                // Refresh version data
                $version = ArticleVersion::find($versionId);

                // If published, update in Telegram
                if ($version->status === 'published' && !empty($version->telegram_message_id)) {
                    $publisher = new Publisher();
                    $success = $publisher->editPost($version, $channel);

                    if ($success) {
                        ArticleVersion::update($versionId, ['status' => 'edited']);
                        $this->setFlash('success', 'Пост обновлён в Telegram');
                    } else {
                        $this->setFlash('warning', 'Версия сохранена, но не удалось обновить в Telegram');
                    }
                } else {
                    $this->setFlash('success', 'Версия сохранена');
                }
            } catch (\Throwable $e) {
                $this->setFlash('danger', 'Ошибка: ' . $e->getMessage());
            }

            $this->redirect("?page=articles&action=view&id={$article->id}");
            return;
        }

        $this->render('articles/edit_post', [
            'pageTitle' => 'Редактирование поста',
            'version' => $version,
            'article' => $article,
            'channel' => $channel,
        ]);
    }

    /**
     * Delete post from Telegram.
     */
    public function delete_post(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $versionId = (int)($_POST['version_id'] ?? 0);
        $version = ArticleVersion::find($versionId);

        if (!$version) {
            $this->setFlash('danger', 'Версия не найдена');
            $this->redirect('?page=articles');
            return;
        }

        $articleId = (int)$version->article_id;
        $channel = Channel::find((int)$version->channel_id);

        if (!$channel) {
            $this->setFlash('danger', 'Канал не найден');
            $this->redirect("?page=articles&action=view&id={$articleId}");
            return;
        }

        if (empty($version->telegram_message_id)) {
            $this->setFlash('danger', 'Пост не был опубликован в Telegram');
            $this->redirect("?page=articles&action=view&id={$articleId}");
            return;
        }

        try {
            $publisher = new Publisher();
            $success = $publisher->deletePost($version, $channel);

            if ($success) {
                ArticleVersion::update($versionId, ['status' => 'deleted']);
                $this->setFlash('success', 'Пост удалён из Telegram');
            } else {
                $this->setFlash('danger', 'Не удалось удалить пост из Telegram');
            }
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'Ошибка: ' . $e->getMessage());
        }

        $this->redirect("?page=articles&action=view&id={$articleId}");
    }

    /**
     * Bulk actions on articles.
     */
    public function bulk(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $action = $_POST['bulk_action'] ?? '';
        $articleIds = array_map('intval', $_POST['article_ids'] ?? []);

        if (empty($articleIds)) {
            $this->setFlash('warning', 'Не выбраны статьи');
            $this->redirect('?page=articles');
            return;
        }

        $count = 0;

        switch ($action) {
            case 'reprocess':
                $count = $this->bulkReprocess($articleIds);
                $this->setFlash('success', "Переобработано статей: {$count}");
                break;

            case 'cancel':
                $count = $this->bulkCancel($articleIds);
                $this->setFlash('success', "Отменено статей: {$count}");
                break;

            case 'publish':
                $count = $this->bulkPublish($articleIds);
                $this->setFlash('success', "Опубликовано версий: {$count}");
                break;

            default:
                $this->setFlash('danger', 'Неизвестное действие');
        }

        $this->redirect('?page=articles');
    }

    /**
     * Bulk reprocess articles.
     */
    private function bulkReprocess(array $articleIds): int
    {
        $count = 0;
        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));

        try {
            Database::beginTransaction();

            // Delete unpublished versions
            Database::execute(
                "DELETE FROM article_versions
                 WHERE article_id IN ({$placeholders})
                   AND status NOT IN ('published', 'edited')",
                $articleIds
            );

            // Reset articles to scraped
            foreach ($articleIds as $articleId) {
                $article = Article::find($articleId);
                if ($article && !in_array($article->status, ['published', 'cancelled'])) {
                    $article->changeStatus('scraped', ['action' => 'bulk_reprocess']);
                    $count++;
                }
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
        }

        return $count;
    }

    /**
     * Bulk cancel articles.
     */
    private function bulkCancel(array $articleIds): int
    {
        $count = 0;
        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));

        try {
            Database::beginTransaction();

            // Cancel unpublished versions
            Database::execute(
                "UPDATE article_versions
                 SET status = 'cancelled'
                 WHERE article_id IN ({$placeholders})
                   AND status NOT IN ('published', 'edited', 'deleted')",
                $articleIds
            );

            // Cancel articles
            foreach ($articleIds as $articleId) {
                $article = Article::find($articleId);
                if ($article && !in_array($article->status, ['published', 'cancelled'])) {
                    $article->changeStatus('cancelled', ['action' => 'bulk_cancel']);
                    $count++;
                }
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
        }

        return $count;
    }

    /**
     * Bulk publish validated versions.
     */
    private function bulkPublish(array $articleIds): int
    {
        $count = 0;
        $placeholders = implode(',', array_fill(0, count($articleIds), '?'));

        // Get validated versions
        $versions = Database::fetchAll("
            SELECT av.id, av.article_id, av.channel_id
            FROM article_versions av
            WHERE av.article_id IN ({$placeholders})
              AND av.status IN ('validated', 'manual_review')
            ORDER BY av.article_id
        ", $articleIds);

        $publisher = new Publisher();

        foreach ($versions as $versionData) {
            $version = ArticleVersion::find((int)$versionData['id']);
            $article = Article::find((int)$versionData['article_id']);
            $channel = Channel::find((int)$versionData['channel_id']);

            if (!$version || !$article || !$channel) {
                continue;
            }

            try {
                $messageId = $publisher->publish($version, $article, $channel);
                if ($messageId) {
                    $version->markPublished($messageId);
                    $article->changeStatus('published', [
                        'action' => 'bulk_publish',
                        'channel_id' => $channel->id,
                    ]);
                    $count++;
                }
            } catch (\Throwable $e) {
                // Continue with other articles
            }
        }

        return $count;
    }
}
