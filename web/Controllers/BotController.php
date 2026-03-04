<?php declare(strict_types=1);

namespace NewsBot\Web\Controllers;

use NewsBot\Core\Crypto;
use NewsBot\Core\Database;
use NewsBot\Models\Bot;

/**
 * Controller for managing Telegram bots.
 */
class BotController extends BaseController
{
    /**
     * List all bots.
     */
    public function index(?int $id = null): void
    {
        $bots = Database::fetchAll('
            SELECT b.*,
                   (SELECT COUNT(*) FROM channels c WHERE c.bot_id = b.id) as channel_count
            FROM bots b
            ORDER BY b.status ASC, b.name ASC
        ');

        $this->render('bots/index', [
            'pageTitle' => 'Боты',
            'bots' => $bots,
        ]);
    }

    /**
     * Edit/create bot form.
     */
    public function edit(?int $id = null): void
    {
        $bot = null;
        $decryptedToken = '';

        if ($id) {
            $bot = Bot::find($id);
            if (!$bot) {
                $this->setFlash('danger', 'Бот не найден');
                $this->redirect('?page=bots');
                return;
            }
            // Decrypt token for display
            try {
                $decryptedToken = $bot->getToken();
            } catch (\Throwable $e) {
                $decryptedToken = ''; // Token corrupted
            }
        }

        $this->render('bots/edit', [
            'pageTitle' => $bot ? 'Редактирование бота' : 'Новый бот',
            'bot' => $bot,
            'decryptedToken' => $decryptedToken,
        ]);
    }

    /**
     * Save bot (create or update).
     */
    public function save(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $token = trim($_POST['token'] ?? '');
        $type = $_POST['type'] ?? 'publishing';
        $status = $_POST['status'] ?? 'active';

        // Validation
        $errors = [];
        if (empty($name)) {
            $errors[] = 'Название обязательно';
        }
        if ($id === 0 && empty($token)) {
            $errors[] = 'Токен обязателен для нового бота';
        }
        if (!in_array($type, ['publishing', 'service'], true)) {
            $errors[] = 'Неверный тип бота';
        }
        if (!in_array($status, ['active', 'paused'], true)) {
            $errors[] = 'Неверный статус';
        }

        if (!empty($errors)) {
            $this->setFlash('danger', implode('<br>', $errors));
            $this->redirect($id ? "?page=bots&action=edit&id={$id}" : '?page=bots&action=edit');
            return;
        }

        $data = [
            'name' => $name,
            'type' => $type,
            'status' => $status,
        ];

        // Handle token update
        if (!empty($token)) {
            // Encrypt new token
            $data['encrypted_token'] = Crypto::encrypt($token);
            // Mask token for legacy field
            $data['token'] = substr($token, 0, 10) . '...';
        }

        try {
            if ($id > 0) {
                Bot::update($id, $data);
                $this->setFlash('success', 'Бот обновлён');
            } else {
                Bot::create($data);
                $this->setFlash('success', 'Бот создан');
            }
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'Ошибка сохранения: ' . $e->getMessage());
            $this->redirect($id ? "?page=bots&action=edit&id={$id}" : '?page=bots&action=edit');
            return;
        }

        $this->redirect('?page=bots');
    }

    /**
     * Toggle bot status.
     */
    public function toggle(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? $id ?? 0);
        $bot = Bot::find($id);

        if (!$bot) {
            $this->setFlash('danger', 'Бот не найден');
            $this->redirect('?page=bots');
            return;
        }

        $newStatus = $bot->status === 'active' ? 'paused' : 'active';
        Bot::update($id, ['status' => $newStatus]);

        $this->setFlash('success', $newStatus === 'active' ? 'Бот активирован' : 'Бот приостановлен');
        $this->redirect('?page=bots');
    }

    /**
     * Delete bot.
     */
    public function delete(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $id = (int)($_POST['id'] ?? $id ?? 0);
        $bot = Bot::find($id);

        if (!$bot) {
            $this->setFlash('danger', 'Бот не найден');
            $this->redirect('?page=bots');
            return;
        }

        // Check if bot is used by channels
        $channelCount = Database::fetchOne(
            'SELECT COUNT(*) as cnt FROM channels WHERE bot_id = ?',
            [$id]
        )['cnt'] ?? 0;

        if ($channelCount > 0) {
            $this->setFlash('danger', "Нельзя удалить бота: используется {$channelCount} каналами");
            $this->redirect('?page=bots');
            return;
        }

        Bot::delete($id);
        $this->setFlash('success', 'Бот удалён');
        $this->redirect('?page=bots');
    }
}
