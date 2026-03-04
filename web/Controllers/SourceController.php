<?php declare(strict_types=1);

namespace NewsBot\Web\Controllers;

use NewsBot\Core\Database;
use NewsBot\Core\Validator;
use NewsBot\Models\Feed;
use NewsBot\Models\ScrapeRule;
use NewsBot\Models\Source;

/**
 * Controller for managing news sources.
 */
class SourceController extends BaseController
{
    /**
     * List all sources.
     */
    public function index(?int $id = null): void
    {
        $sources = Database::fetchAll('
            SELECT s.*,
                   (SELECT COUNT(*) FROM feeds f WHERE f.source_id = s.id) as feed_count,
                   (SELECT COUNT(*) FROM feeds f WHERE f.source_id = s.id AND f.status = \'active\') as active_feed_count,
                   (SELECT COUNT(*) FROM feeds f WHERE f.source_id = s.id AND f.status = \'auto_disabled\') as disabled_feed_count,
                   (SELECT COUNT(*) FROM channel_sources cs WHERE cs.source_id = s.id) as channel_count
            FROM sources s
            ORDER BY s.status ASC, s.name ASC
        ');

        $this->render('sources/index', [
            'pageTitle' => 'Источники',
            'sources' => $sources,
        ]);
    }

    /**
     * Edit/create source form.
     */
    public function edit(?int $id = null): void
    {
        $source = null;
        $feeds = [];
        $scrapeRules = [];

        if ($id) {
            $source = Source::find($id);
            if (!$source) {
                $this->setFlash('danger', 'Источник не найден');
                $this->redirect('?page=sources');
                return;
            }
            $feeds = Database::fetchAll(
                'SELECT * FROM feeds WHERE source_id = ? ORDER BY id',
                [$id]
            );
            $scrapeRules = Database::fetchAll(
                'SELECT * FROM scrape_rules WHERE source_id = ? ORDER BY priority DESC',
                [$id]
            );
        }

        $this->render('sources/edit', [
            'pageTitle' => $source ? 'Редактирование источника' : 'Новый источник',
            'source' => $source,
            'feeds' => $feeds,
            'scrapeRules' => $scrapeRules,
        ]);
    }

    /**
     * Save source (create or update).
     */
    public function save(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $isNew = $id === 0;

        // Collect form data
        $name = trim($_POST['name'] ?? '');
        $siteUrl = trim($_POST['site_url'] ?? '');
        $type = $_POST['type'] ?? 'news';
        $scrapeStrategy = $_POST['scrape_strategy'] ?? 'web';
        $authorityRank = (int)($_POST['authority_rank'] ?? 50);
        $requestDelayMs = (int)($_POST['request_delay_ms'] ?? 2000);
        $proxyUrl = trim($_POST['proxy_url'] ?? '');
        $status = $_POST['status'] ?? 'active';

        // Validation
        $errors = [];
        if (empty($name)) {
            $errors[] = 'Название обязательно';
        }
        if (empty($siteUrl)) {
            $errors[] = 'URL сайта обязателен';
        } elseif (!Validator::url($siteUrl)) {
            $errors[] = 'Неверный формат URL сайта';
        }
        if (!in_array($scrapeStrategy, ['web', 'rss_only', 'custom_parser'], true)) {
            $errors[] = 'Неверная стратегия скрапинга';
        }
        if (!empty($proxyUrl) && !Validator::url($proxyUrl)) {
            $errors[] = 'Неверный формат URL прокси';
        }

        // Validate feeds
        $feedsData = $_POST['feeds'] ?? [];
        foreach ($feedsData as $idx => $feed) {
            $feedUrl = trim($feed['url'] ?? '');
            if (!empty($feedUrl) && !Validator::url($feedUrl)) {
                $errors[] = "Фид #{$idx}: неверный URL";
            }
        }

        if (!empty($errors)) {
            $this->setFlash('danger', implode('<br>', $errors));
            $this->redirect($id > 0 ? "?page=sources&action=edit&id={$id}" : '?page=sources&action=edit');
            return;
        }

        // Prepare source data
        $data = [
            'name' => $name,
            'site_url' => $siteUrl,
            'type' => $type,
            'scrape_strategy' => $scrapeStrategy,
            'authority_rank' => max(1, min(100, $authorityRank)),
            'request_delay_ms' => max(500, min(30000, $requestDelayMs)),
            'proxy_url' => $proxyUrl ?: null,
            'status' => $status,
        ];

        try {
            Database::beginTransaction();

            if ($id > 0) {
                Source::update($id, $data);
            } else {
                $source = Source::create($data);
                $id = (int)$source->id;
            }

            // Update feeds
            $this->updateFeeds($id, $feedsData);

            // Update scrape rules
            $this->updateScrapeRules($id, $_POST['rules'] ?? []);

            Database::commit();
            $this->setFlash('success', $isNew ? 'Источник создан' : 'Источник обновлён');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->setFlash('danger', 'Ошибка сохранения: ' . $e->getMessage());
            $this->redirect($id > 0 ? "?page=sources&action=edit&id={$id}" : '?page=sources&action=edit');
            return;
        }

        $this->redirect('?page=sources');
    }

    /**
     * Toggle source status.
     */
    public function toggle(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? $id ?? 0);
        $source = Source::find($id);

        if (!$source) {
            $this->setFlash('danger', 'Источник не найден');
            $this->redirect('?page=sources');
            return;
        }

        $newStatus = $source->status === 'active' ? 'paused' : 'active';
        Source::update($id, ['status' => $newStatus]);

        $this->setFlash('success', $newStatus === 'active' ? 'Источник активирован' : 'Источник приостановлен');
        $this->redirect('?page=sources');
    }

    /**
     * Delete source.
     */
    public function delete(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? $id ?? 0);
        $source = Source::find($id);

        if (!$source) {
            $this->setFlash('danger', 'Источник не найден');
            $this->redirect('?page=sources');
            return;
        }

        try {
            Database::beginTransaction();

            // Delete related data
            Database::delete('feeds', 'source_id = ?', [$id]);
            Database::delete('scrape_rules', 'source_id = ?', [$id]);
            Database::delete('channel_sources', 'source_id = ?', [$id]);
            Database::delete('source_parsers', 'source_id = ?', [$id]);

            // Delete source
            Source::delete($id);

            Database::commit();
            $this->setFlash('success', 'Источник удалён');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->setFlash('danger', 'Ошибка удаления: ' . $e->getMessage());
        }

        $this->redirect('?page=sources');
    }

    /**
     * Reactivate auto-disabled feed.
     */
    public function reactivate_feed(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $feedId = (int)($_POST['feed_id'] ?? 0);
        $feed = Feed::find($feedId);

        if (!$feed) {
            $this->setFlash('danger', 'Фид не найден');
            $this->redirect('?page=sources');
            return;
        }

        Feed::update($feedId, [
            'status' => 'active',
            'consecutive_errors' => 0,
            'last_error' => null,
        ]);

        $this->setFlash('success', 'Фид реактивирован');
        $this->redirect("?page=sources&action=edit&id={$feed->source_id}");
    }

    /**
     * Reactivate all auto-disabled feeds for a source.
     */
    public function reactivate_all_feeds(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $sourceId = (int)($_POST['source_id'] ?? $id ?? 0);
        $source = Source::find($sourceId);

        if (!$source) {
            $this->setFlash('danger', 'Источник не найден');
            $this->redirect('?page=sources');
            return;
        }

        $count = Database::execute(
            "UPDATE feeds SET status = 'active', consecutive_errors = 0, last_error = NULL
             WHERE source_id = ? AND status = 'auto_disabled'",
            [$sourceId]
        );

        $this->setFlash('success', "Реактивировано фидов: {$count}");
        $this->redirect("?page=sources&action=edit&id={$sourceId}");
    }

    /**
     * Update feeds for source.
     */
    private function updateFeeds(int $sourceId, array $feedsData): void
    {
        // Get existing feed IDs
        $existingIds = array_column(
            Database::fetchAll('SELECT id FROM feeds WHERE source_id = ?', [$sourceId]),
            'id'
        );
        $processedIds = [];

        foreach ($feedsData as $feed) {
            $feedId = (int)($feed['id'] ?? 0);
            $feedUrl = trim($feed['url'] ?? '');
            $feedName = trim($feed['name'] ?? '');
            $feedStatus = $feed['status'] ?? 'active';
            $feedIntervalRaw = trim($feed['fetch_interval_min'] ?? '');
            $feedIntervalMin = $feedIntervalRaw !== '' && (int)$feedIntervalRaw > 0
                ? (int)$feedIntervalRaw
                : null;

            // Skip empty rows
            if (empty($feedUrl)) {
                continue;
            }

            $feedData = [
                'source_id' => $sourceId,
                'url' => $feedUrl,
                'name' => $feedName ?: null,
                'status' => $feedStatus,
                'fetch_interval_min' => $feedIntervalMin,
            ];

            if ($feedId > 0 && in_array($feedId, $existingIds)) {
                Feed::update($feedId, $feedData);
                $processedIds[] = $feedId;
            } else {
                $newFeed = Feed::create($feedData);
                $processedIds[] = (int)$newFeed->id;
            }
        }

        // Delete removed feeds
        $toDelete = array_diff($existingIds, $processedIds);
        foreach ($toDelete as $feedId) {
            Feed::delete($feedId);
        }
    }

    /**
     * Update scrape rules for source.
     */
    private function updateScrapeRules(int $sourceId, array $rulesData): void
    {
        // Get existing rule IDs
        $existingIds = array_column(
            Database::fetchAll('SELECT id FROM scrape_rules WHERE source_id = ?', [$sourceId]),
            'id'
        );
        $processedIds = [];

        foreach ($rulesData as $rule) {
            $ruleId = (int)($rule['id'] ?? 0);
            $contentSelector = trim($rule['content_selector'] ?? '');
            $removeSelectors = trim($rule['remove_selectors'] ?? '');
            $priority = (int)($rule['priority'] ?? 0);

            // Skip empty rows
            if (empty($contentSelector)) {
                continue;
            }

            // Parse remove_selectors as JSON array or newline-separated
            $removeSelectorsJson = null;
            if (!empty($removeSelectors)) {
                // Check if it's already JSON
                $decoded = json_decode($removeSelectors, true);
                if (is_array($decoded)) {
                    $removeSelectorsJson = $removeSelectors;
                } else {
                    // Convert newline-separated to JSON array
                    $lines = array_filter(array_map('trim', explode("\n", $removeSelectors)));
                    $removeSelectorsJson = json_encode($lines);
                }
            }

            $ruleData = [
                'source_id' => $sourceId,
                'content_selector' => $contentSelector,
                'remove_selectors' => $removeSelectorsJson,
                'priority' => $priority,
            ];

            if ($ruleId > 0 && in_array($ruleId, $existingIds)) {
                ScrapeRule::update($ruleId, $ruleData);
                $processedIds[] = $ruleId;
            } else {
                $newRule = ScrapeRule::create($ruleData);
                $processedIds[] = (int)$newRule->id;
            }
        }

        // Delete removed rules
        $toDelete = array_diff($existingIds, $processedIds);
        foreach ($toDelete as $ruleId) {
            ScrapeRule::delete($ruleId);
        }
    }
}
