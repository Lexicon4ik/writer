<?php declare(strict_types=1);

namespace NewsBot\Web\Controllers;

use NewsBot\Core\Crypto;
use NewsBot\Core\Database;
use NewsBot\Core\Settings;
use NewsBot\Models\Bot;
use NewsBot\Services\AiClient;
use NewsBot\Services\OpenRouterClient;
use NewsBot\Services\AnthropicClient;
use NewsBot\Services\GeminiClient;
use NewsBot\Services\TelegramClient;

/**
 * Controller for managing global settings.
 */
class SettingsController extends BaseController
{
    /**
     * Sensitive keys that should be encrypted.
     */
    private const SENSITIVE_KEYS = [
        'openrouter_api_key',
        'anthropic_api_key',
        'gemini_api_key',
    ];

    /**
     * Settings groups configuration.
     */
    private const SETTINGS_GROUPS = [
        'ai_provider' => [
            'label' => 'AI Провайдер',
            'icon' => 'bi-robot',
            'settings' => [
                'ai_provider' => ['type' => 'select', 'label' => 'Провайдер', 'options' => ['openrouter' => 'OpenRouter', 'anthropic' => 'Anthropic Direct', 'gemini' => 'Google Gemini']],
                'openrouter_api_key' => ['type' => 'password', 'label' => 'OpenRouter API Key', 'placeholder' => 'sk-or-...'],
                'anthropic_api_key' => ['type' => 'password', 'label' => 'Anthropic API Key', 'placeholder' => 'sk-ant-...'],
                'gemini_api_key' => ['type' => 'password', 'label' => 'Gemini API Key', 'placeholder' => 'AIza...'],
            ],
        ],
        'ai_models' => [
            'label' => 'AI Модели',
            'icon' => 'bi-cpu',
            'settings' => [
                'model_process' => ['type' => 'text', 'label' => 'Модель обработки', 'default' => 'anthropic/claude-sonnet-4-20250514'],
                'model_process_fallback' => ['type' => 'text', 'label' => 'Fallback обработки', 'default' => 'anthropic/claude-3-5-sonnet-20241022'],
                'model_validate' => ['type' => 'text', 'label' => 'Модель валидации', 'default' => 'anthropic/claude-haiku-4-5-20251001'],
                'model_validate_fallback' => ['type' => 'text', 'label' => 'Fallback валидации', 'default' => 'google/gemini-2.0-flash-001'],
                'model_deduplicate' => ['type' => 'text', 'label' => 'Модель дедупликации', 'default' => 'anthropic/claude-haiku-4-5-20251001'],
                'model_deduplicate_fallback' => ['type' => 'text', 'label' => 'Fallback дедупликации', 'default' => 'openai/gpt-4o-mini'],
                'model_fallback_enabled' => ['type' => 'checkbox', 'label' => 'Включить fallback модели'],
            ],
        ],
        'ai_temperature' => [
            'label' => 'AI Temperature',
            'icon' => 'bi-thermometer-half',
            'settings' => [
                'temperature_process' => ['type' => 'number', 'label' => 'Обработка', 'default' => '0.4', 'step' => '0.1', 'min' => '0', 'max' => '2'],
                'temperature_validate' => ['type' => 'number', 'label' => 'Валидация', 'default' => '0.3', 'step' => '0.1', 'min' => '0', 'max' => '2'],
                'temperature_deduplicate' => ['type' => 'number', 'label' => 'Дедупликация', 'default' => '0.1', 'step' => '0.1', 'min' => '0', 'max' => '2'],
            ],
        ],
        'budget' => [
            'label' => 'Бюджет',
            'icon' => 'bi-wallet2',
            'settings' => [
                'ai_daily_budget' => ['type' => 'number', 'label' => 'Дневной лимит ($)', 'default' => '10', 'step' => '0.5', 'min' => '0'],
            ],
        ],
        'alerts' => [
            'label' => 'Алерты',
            'icon' => 'bi-bell',
            'settings' => [
                'alert_bot_id' => ['type' => 'bot_select', 'label' => 'Бот для алертов', 'filter' => 'service'],
                'alert_chat_id' => ['type' => 'text', 'label' => 'Chat ID для алертов', 'placeholder' => '@admin или 123456789'],
            ],
        ],
        'pipeline' => [
            'label' => 'Пайплайн',
            'icon' => 'bi-diagram-3',
            'settings' => [
                'max_article_age_hours' => ['type' => 'number', 'label' => 'Макс. возраст статьи (часов)', 'default' => '24', 'min' => '1'],
                'dedup_max_batches' => ['type' => 'number', 'label' => 'Макс. батчей дедупликации', 'default' => '10', 'min' => '1'],
            ],
        ],
    ];

    /**
     * Display settings page.
     */
    public function index(?int $id = null): void
    {
        // Get all current settings
        $allSettings = Settings::all();

        // Get service bots for alert bot select
        $serviceBots = Database::fetchAll(
            "SELECT id, name FROM bots WHERE type = 'service' AND status = 'active' ORDER BY name"
        );

        // Get today's AI usage
        $todayUsage = Database::fetchOne(
            "SELECT COUNT(*) as calls,
                    COALESCE(SUM(input_tokens), 0) as input_tokens,
                    COALESCE(SUM(output_tokens), 0) as output_tokens,
                    COALESCE(SUM(estimated_cost), 0) as cost
             FROM ai_usage_log
             WHERE DATE(created_at) = CURDATE()"
        );

        $this->render('settings/index', [
            'pageTitle' => 'Настройки',
            'settingsGroups' => self::SETTINGS_GROUPS,
            'allSettings' => $allSettings,
            'serviceBots' => $serviceBots,
            'todayUsage' => $todayUsage,
        ]);
    }

    /**
     * Save settings.
     */
    public function save(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        $settings = $_POST['settings'] ?? [];

        foreach ($settings as $key => $value) {
            // Skip empty sensitive keys (don't overwrite)
            if (in_array($key, self::SENSITIVE_KEYS) && empty(trim($value))) {
                continue;
            }

            $trimmed = trim($value);

            // Encrypt sensitive keys
            if (in_array($key, self::SENSITIVE_KEYS) && !empty($trimmed)) {
                $trimmed = Crypto::encrypt($trimmed);
            }

            Settings::set($key, $trimmed);
        }

        // Handle checkboxes (not sent when unchecked)
        $checkboxKeys = ['model_fallback_enabled'];
        foreach ($checkboxKeys as $cbKey) {
            if (!isset($settings[$cbKey])) {
                Settings::set($cbKey, '0');
            }
        }

        $this->setFlash('success', 'Настройки сохранены');
        $this->redirect('?page=settings');
    }

    /**
     * Test AI connection.
     */
    public function test_ai(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        header('Content-Type: application/json');

        try {
            $provider = Settings::get('ai_provider', 'openrouter');
            $model = AiClient::getModel('process');

            // Create client based on provider
            if ($provider === 'anthropic') {
                $client = new AnthropicClient();
            } elseif ($provider === 'gemini') {
                $client = new GeminiClient();
            } else {
                $client = new OpenRouterClient();
            }

            $startTime = microtime(true);
            $result = $client->message(
                'You are a helpful assistant. Respond briefly.',
                'Hello! Please respond with "Connection successful" and nothing else.',
                $model,
                0.1,
                100
            );
            $duration = round((microtime(true) - $startTime) * 1000);

            echo json_encode([
                'success' => true,
                'provider' => $provider,
                'model' => $result['model'] ?? $model,
                'response' => $result['content'] ?? '',
                'input_tokens' => $result['usage']['input_tokens'] ?? 0,
                'output_tokens' => $result['usage']['output_tokens'] ?? 0,
                'duration_ms' => $duration,
            ]);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
        exit;
    }

    /**
     * Test Telegram connection.
     */
    public function test_telegram(?int $id = null): void
    {
        $this->requirePost();
        $this->validateCsrf();

        header('Content-Type: application/json');

        try {
            $botId = (int)Settings::get('alert_bot_id', '0');
            $chatId = Settings::get('alert_chat_id', '');

            if ($botId <= 0) {
                throw new \RuntimeException('Alert bot not configured');
            }
            if (empty($chatId)) {
                throw new \RuntimeException('Alert chat ID not configured');
            }

            $bot = Bot::find($botId);
            if (!$bot) {
                throw new \RuntimeException('Alert bot not found');
            }

            $token = $bot->getToken();
            if (empty($token)) {
                throw new \RuntimeException('Bot token is empty or corrupted');
            }

            $telegram = new TelegramClient();

            // First, verify bot
            $botInfo = $telegram->getMe($token);

            // Send test message
            $message = "Test message from NewsBot Admin\n" . date('Y-m-d H:i:s') . " UTC";
            $result = $telegram->sendMessage($token, $chatId, $message);

            echo json_encode([
                'success' => true,
                'bot_username' => $botInfo['username'] ?? 'unknown',
                'message_id' => $result['message_id'] ?? 0,
                'chat_id' => $chatId,
            ]);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
        exit;
    }

    /**
     * Mask sensitive value for display.
     */
    public static function maskSensitiveValue(string $value): string
    {
        if (empty($value)) {
            return '';
        }

        // Try to decrypt first
        try {
            $decrypted = Crypto::decryptSafe($value);
            if (!empty($decrypted)) {
                // Show only last 4 chars
                $len = strlen($decrypted);
                if ($len <= 8) {
                    return str_repeat('*', $len);
                }
                return substr($decrypted, 0, 6) . '****' . substr($decrypted, -4);
            }
        } catch (\Throwable) {
            // Not encrypted, mask as-is
        }

        // Plain value (legacy), mask it
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, 6) . '****' . substr($value, -4);
    }
}
