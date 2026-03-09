<?php declare(strict_types=1);

namespace NewsBot\Web\Controllers;

use NewsBot\Core\Database;
use NewsBot\Core\Validator;
use NewsBot\Models\Bot;
use NewsBot\Models\Channel;
use NewsBot\Models\ChannelSource;
use NewsBot\Models\Source;

/**
 * Controller for managing publication channels.
 */
class ChannelController extends BaseController
{
    /**
     * Common IANA timezones for select.
     */
    private const TIMEZONES = [
        'UTC' => 'UTC',
        'Asia/Bangkok' => 'Asia/Bangkok (ICT, UTC+7)',
        'Asia/Ho_Chi_Minh' => 'Asia/Ho Chi Minh (ICT, UTC+7)',
        'Asia/Jakarta' => 'Asia/Jakarta (WIB, UTC+7)',
        'Asia/Singapore' => 'Asia/Singapore (SGT, UTC+8)',
        'Asia/Hong_Kong' => 'Asia/Hong Kong (HKT, UTC+8)',
        'Asia/Tokyo' => 'Asia/Tokyo (JST, UTC+9)',
        'Asia/Seoul' => 'Asia/Seoul (KST, UTC+9)',
        'Europe/Moscow' => 'Europe/Moscow (MSK, UTC+3)',
        'Europe/London' => 'Europe/London (GMT/BST)',
        'Europe/Paris' => 'Europe/Paris (CET/CEST)',
        'Europe/Berlin' => 'Europe/Berlin (CET/CEST)',
        'America/New_York' => 'America/New_York (EST/EDT)',
        'America/Los_Angeles' => 'America/Los Angeles (PST/PDT)',
    ];

    /**
     * List all channels.
     */
    public function index(?int $id = null): void
    {
        $channels = Database::fetchAll('
            SELECT c.*,
                   b.name as bot_name,
                   (SELECT COUNT(*) FROM channel_sources cs WHERE cs.channel_id = c.id) as source_count,
                   (SELECT COUNT(*) FROM article_versions av
                    WHERE av.channel_id = c.id
                      AND av.status = \'published\'
                      AND DATE(av.published_at) = CURDATE()) as published_today
            FROM channels c
            LEFT JOIN bots b ON b.id = c.bot_id
            ORDER BY c.status ASC, c.name ASC
        ');

        $this->render('channels/index', [
            'pageTitle' => 'Каналы',
            'channels' => $channels,
        ]);
    }

    /**
     * Edit/create channel form.
     */
    public function edit(?int $id = null): void
    {
        $channel = null;
        $linkedSourceIds = [];

        if ($id) {
            $channel = Channel::find($id);
            if (!$channel) {
                $this->setFlash('danger', 'Канал не найден');
                $this->redirect('?page=channels');
                return;
            }
            $linkedSourceIds = ChannelSource::getSourceIds($id);
        }

        // Get bots for select
        $bots = Database::fetchAll('SELECT id, name, type, status FROM bots ORDER BY name');

        // Get sources for multi-select
        $sources = Database::fetchAll('SELECT id, name, site_url, status FROM sources ORDER BY name');

        $this->render('channels/edit', [
            'pageTitle' => $channel ? 'Редактирование канала' : 'Новый канал',
            'channel' => $channel,
            'bots' => $bots,
            'sources' => $sources,
            'linkedSourceIds' => $linkedSourceIds,
            'timezones' => self::TIMEZONES,
        ]);
    }

    /**
     * Save channel (create or update).
     */
    public function save(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $isNew = $id === 0;

        // Get old channel for prompt comparison
        $oldChannel = $id > 0 ? Channel::find($id) : null;
        $oldPrompt = $oldChannel ? ($oldChannel->ai_prompt ?? '') : '';

        // Collect form data
        $name = trim($_POST['name'] ?? '');
        $botId = (int)($_POST['bot_id'] ?? 0);
        $chatId = trim($_POST['chat_id'] ?? '');
        $topic = trim($_POST['topic'] ?? '');
        $language = trim($_POST['language'] ?? 'ru');
        $timezone = $_POST['timezone'] ?? 'UTC';
        $aiPrompt = trim($_POST['ai_prompt'] ?? '');
        $validationPrompt = trim($_POST['validation_prompt'] ?? '');
        $aiModel = trim($_POST['ai_model'] ?? '');
        $aiTemperature = $_POST['ai_temperature'] !== '' ? (float)$_POST['ai_temperature'] : null;
        $postTemplate = trim($_POST['post_template'] ?? '');
        $publishIntervalMin = (int)($_POST['publish_interval_min'] ?? 5);
        $activeHoursStart = $_POST['active_hours_start'] ?? '08:00:00';
        $activeHoursEnd = $_POST['active_hours_end'] ?? '22:00:00';
        $maxPerRun = (int)($_POST['max_per_run'] ?? 3);
        $maxPerDay = (int)($_POST['max_per_day'] ?? 20);
        $minImportanceScore = (int)($_POST['min_importance_score'] ?? 1);
        $useImages = isset($_POST['use_images']) ? 1 : 0;
        $imageMode = in_array($_POST['image_mode'] ?? '', ['source', 'enhanced', 'generated', 'ai_only', 'library', 'pexels_ai', 'disabled'], true)
            ? $_POST['image_mode']
            : 'source';
        $manualReviewEnabled = isset($_POST['manual_review_enabled']) ? 1 : 0;
        $minValidationScore = (int)($_POST['min_validation_score'] ?? 6);
        $validationMode = $_POST['validation_mode'] ?? 'always';
        $validationSamplePct = (int)($_POST['validation_sample_pct'] ?? 100);
        $validationImportanceMin = (int)($_POST['validation_importance_min'] ?? 1);
        $status = $_POST['status'] ?? 'active';

        // Validation
        $errors = [];
        if (empty($name)) {
            $errors[] = 'Название обязательно';
        }
        if ($botId <= 0) {
            $errors[] = 'Выберите бота';
        }
        if (empty($chatId)) {
            $errors[] = 'Chat ID обязателен';
        } elseif (!Validator::chatId($chatId)) {
            $errors[] = 'Неверный формат Chat ID (допустимо: @channel, -100xxx, число)';
        }
        if (!Validator::timezone($timezone)) {
            $errors[] = 'Неверный часовой пояс: ' . htmlspecialchars($timezone);
        }
        if (!in_array($status, ['active', 'paused'], true)) {
            $errors[] = 'Неверный статус';
        }

        if (!empty($errors)) {
            $this->setFlash('danger', implode('<br>', $errors));
            $this->redirect($id > 0 ? "?page=channels&action=edit&id={$id}" : '?page=channels&action=edit');
            return;
        }

        // Prepare data
        $data = [
            'name' => $name,
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'topic' => $topic ?: null,
            'language' => $language,
            'timezone' => $timezone,
            'ai_prompt' => $aiPrompt ?: null,
            'validation_prompt' => $validationPrompt ?: null,
            'ai_model' => $aiModel ?: null,
            'ai_temperature' => $aiTemperature,
            'post_template' => $postTemplate ?: null,
            'publish_interval_min' => $publishIntervalMin,
            'active_hours_start' => $activeHoursStart,
            'active_hours_end' => $activeHoursEnd,
            'max_per_run' => $maxPerRun,
            'max_per_day' => $maxPerDay,
            'min_importance_score' => $minImportanceScore,
            'use_images' => $useImages,
            'image_mode' => $imageMode,
            'manual_review_enabled' => $manualReviewEnabled,
            'min_validation_score' => $minValidationScore,
            'validation_mode' => $validationMode,
            'validation_sample_pct' => $validationSamplePct,
            'validation_importance_min' => $validationImportanceMin,
            'status' => $status,
        ];

        // Check if prompt changed
        $promptChanged = !$isNew && $aiPrompt !== $oldPrompt;
        if ($promptChanged) {
            $data['prompt_updated_at'] = date('Y-m-d H:i:s');
        }

        try {
            Database::beginTransaction();

            if ($id > 0) {
                Channel::update($id, $data);
            } else {
                $channel = Channel::create($data);
                $id = (int)$channel->id;
            }

            // Update channel_sources
            $this->updateChannelSources($id, $_POST['sources'] ?? []);

            // Auto-reprocess if prompt changed
            $reprocessedCount = 0;
            if ($promptChanged) {
                $reprocessedCount = $this->reprocessForPromptChange($id);
            }

            Database::commit();

            if ($promptChanged && $reprocessedCount > 0) {
                $this->setFlash('success', "Канал сохранён. Промпт изменён — {$reprocessedCount} статей отправлены на переобработку.");
            } else {
                $this->setFlash('success', $isNew ? 'Канал создан' : 'Канал обновлён');
            }
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->setFlash('danger', 'Ошибка сохранения: ' . $e->getMessage());
            $this->redirect($id > 0 ? "?page=channels&action=edit&id={$id}" : '?page=channels&action=edit');
            return;
        }

        $this->redirect('?page=channels');
    }

    /**
     * Toggle channel status.
     */
    public function toggle(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? $id ?? 0);
        $channel = Channel::find($id);

        if (!$channel) {
            $this->setFlash('danger', 'Канал не найден');
            $this->redirect('?page=channels');
            return;
        }

        $newStatus = $channel->status === 'active' ? 'paused' : 'active';
        Channel::update($id, ['status' => $newStatus]);

        $this->setFlash('success', $newStatus === 'active' ? 'Канал активирован' : 'Канал приостановлен');
        $this->redirect('?page=channels');
    }

    /**
     * Delete channel.
     */
    public function delete(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? $id ?? 0);
        $channel = Channel::find($id);

        if (!$channel) {
            $this->setFlash('danger', 'Канал не найден');
            $this->redirect('?page=channels');
            return;
        }

        try {
            Database::beginTransaction();

            // Delete channel_sources
            Database::delete('channel_sources', 'channel_id = ?', [$id]);

            // Delete channel
            Channel::delete($id);

            Database::commit();
            $this->setFlash('success', 'Канал удалён');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->setFlash('danger', 'Ошибка удаления: ' . $e->getMessage());
        }

        $this->redirect('?page=channels');
    }

    /**
     * Reprocess recent articles form/action.
     */
    public function reprocess_recent(?int $id = null): void
    {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? $id ?? 0);
        $channel = Channel::find($id);

        if (!$channel) {
            $this->setFlash('danger', 'Канал не найден');
            $this->redirect('?page=channels');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $days = (int)($_POST['days'] ?? 3);
            $days = max(1, min(30, $days));

            try {
                $reprocessed = $this->reprocessRecentArticles($id, $days);
                $this->setFlash('success', "Переобработка: {$reprocessed} статей за последние {$days} дней");
            } catch (\Throwable $e) {
                $this->setFlash('danger', 'Ошибка: ' . $e->getMessage());
            }

            $this->redirect("?page=channels&action=edit&id={$id}");
            return;
        }

        // Show form (via edit page)
        $this->redirect("?page=channels&action=edit&id={$id}");
    }

    /**
     * Update channel-source links.
     */
    private function updateChannelSources(int $channelId, array $sourceIds): void
    {
        // Get current links
        $currentIds = ChannelSource::getSourceIds($channelId);
        $newIds = array_map('intval', $sourceIds);

        // Remove unlinked
        $toRemove = array_diff($currentIds, $newIds);
        foreach ($toRemove as $sourceId) {
            ChannelSource::unlink($channelId, $sourceId);
        }

        // Add new links
        $toAdd = array_diff($newIds, $currentIds);
        foreach ($toAdd as $sourceId) {
            ChannelSource::link($channelId, $sourceId);
        }
    }

    /**
     * Reprocess articles when prompt changes.
     * Returns count of affected articles.
     */
    private function reprocessForPromptChange(int $channelId): int
    {
        // Reset non-published article_versions to pending
        Database::execute(
            "UPDATE article_versions
             SET status = 'pending'
             WHERE channel_id = ?
               AND status IN ('validated', 'failed', 'manual_review')",
            [$channelId]
        );

        // Return related articles to 'scraped' status
        // BUT only if their versions for this channel are NOT published
        $stmt = Database::execute(
            "UPDATE articles a
             INNER JOIN article_versions av ON a.id = av.article_id
             SET a.status = 'scraped'
             WHERE av.channel_id = ?
               AND a.status IN ('processed', 'process_failed', 'manual_review')
               AND av.status NOT IN ('published', 'publishing')",
            [$channelId]
        );

        return $stmt->rowCount();
    }

    /**
     * Reprocess articles for last N days.
     */
    private function reprocessRecentArticles(int $channelId, int $days): int
    {
        $sinceDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Reset article_versions
        Database::execute(
            "UPDATE article_versions
             SET status = 'pending'
             WHERE channel_id = ?
               AND status NOT IN ('published', 'edited', 'deleted')
               AND created_at >= ?",
            [$channelId, $sinceDate]
        );

        // Return articles to scraped
        $stmt = Database::execute(
            "UPDATE articles a
             INNER JOIN article_versions av ON a.id = av.article_id
             SET a.status = 'scraped'
             WHERE av.channel_id = ?
               AND a.status IN ('processed', 'process_failed', 'manual_review')
               AND a.created_at >= ?",
            [$channelId, $sinceDate]
        );

        return $stmt->rowCount();
    }
}
