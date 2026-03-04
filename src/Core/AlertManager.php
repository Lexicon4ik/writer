<?php declare(strict_types=1);

namespace NewsBot\Core;

use NewsBot\Models\Bot;
use NewsBot\Services\TelegramClient;

/**
 * Alert manager for admin notifications via Telegram.
 * Sends alerts through a service bot and performs automated health checks.
 */
class AlertManager
{
    /**
     * Minimum interval between same alert type (seconds).
     */
    private const ALERT_COOLDOWN = 3600; // 1 hour

    /**
     * Send an alert to admin via Telegram.
     *
     * @param string $message Alert message
     * @param string $level Alert level (info, warning, error, critical)
     * @return bool True if sent successfully, false otherwise
     */
    public static function send(string $message, string $level = 'warning'): bool
    {
        // Get alert configuration
        $alertBotId = Settings::get('alert_bot_id');
        $alertChatId = Settings::get('alert_chat_id');

        if (empty($alertBotId) || empty($alertChatId)) {
            Logger::warning('[ALERT] Alert not configured - logging only: ' . $message, [
                'level' => $level,
                'alert_bot_id' => $alertBotId ?? 'not set',
                'alert_chat_id' => $alertChatId ?? 'not set',
            ]);
            return false;
        }

        // Get bot
        $bot = Bot::find((int)$alertBotId);
        if (!$bot) {
            Logger::warning('[ALERT] Alert bot not found: ' . $message, [
                'level' => $level,
                'bot_id' => $alertBotId,
            ]);
            return false;
        }

        $token = $bot->getToken();
        if (empty($token)) {
            Logger::warning('[ALERT] Alert bot has no token: ' . $message, [
                'level' => $level,
                'bot_id' => $alertBotId,
            ]);
            return false;
        }

        // Add level emoji prefix
        $prefix = match ($level) {
            'info' => 'ℹ️',
            'warning' => '⚠️',
            'error' => '🔴',
            'critical' => '🚨',
            default => '📢',
        };

        $formattedMessage = "{$prefix} <b>NewsBot Alert</b>\n\n{$message}";

        try {
            $client = new TelegramClient();
            $client->sendMessage($token, $alertChatId, $formattedMessage, 'HTML');

            Logger::info('[ALERT] Sent successfully', [
                'level' => $level,
                'chat_id' => $alertChatId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Logger::error('[ALERT] Failed to send alert', [
                'level' => $level,
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
            return false;
        }
    }

    /**
     * Send error alert with context.
     */
    public static function error(string $message, array $context = []): bool
    {
        $fullMessage = $message;
        if (!empty($context)) {
            $fullMessage .= "\n\n<code>" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</code>";
        }

        return self::send($fullMessage, 'error');
    }

    /**
     * Send critical alert (e.g., budget exceeded, all APIs down).
     */
    public static function critical(string $message, array $context = []): bool
    {
        $fullMessage = $message;
        if (!empty($context)) {
            $fullMessage .= "\n\n<code>" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</code>";
        }

        return self::send($fullMessage, 'critical');
    }

    /**
     * Check if alerting is configured.
     */
    public static function isConfigured(): bool
    {
        $chatId = Settings::get('alert_chat_id');
        $botId = Settings::get('alert_bot_id');

        return !empty($chatId) && !empty($botId);
    }

    /**
     * Run all automated alert checks.
     * Called from cleanup.php.
     */
    public static function checkAlerts(): void
    {
        if (!self::isConfigured()) {
            Logger::debug('AlertManager: Not configured, skipping checks');
            return;
        }

        Logger::debug('AlertManager: Running automated checks');

        try {
            self::checkFeedsDisabled();
            self::checkAiNotResponding();
            self::checkBudgetExhausted();
            self::checkChannelSilence();
            self::checkManualReviewQueue();
            self::checkParsersDisabled();
            self::checkParsersZeroArticles();
            self::checkAiErrorsSpike();
        } catch (\Throwable $e) {
            Logger::error('AlertManager: Check failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check for mass feed failures.
     * Alert if >30% of feeds are auto_disabled.
     */
    private static function checkFeedsDisabled(): void
    {
        $stats = Database::fetchOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'auto_disabled' THEN 1 ELSE 0 END) as disabled
             FROM feeds
             WHERE status IN ('active', 'auto_disabled')"
        );

        $total = (int)($stats['total'] ?? 0);
        $disabled = (int)($stats['disabled'] ?? 0);

        if ($total === 0 || $disabled === 0) {
            return;
        }

        $ratio = $disabled / $total;

        if ($ratio > 0.3) {
            if (self::canSendAlert('feeds_disabled')) {
                self::send(
                    "⚠️ <b>Массовый сбой фидов</b>\n\n" .
                    "{$disabled} из {$total} фидов автоматически отключены.\n" .
                    "Проверьте логи и состояние источников.",
                    'warning'
                );
                self::recordAlertSent('feeds_disabled');
            }
        }
    }

    /**
     * Check if AI API is not responding.
     * Alert if 0 records in ai_usage_log for past hour + errors present.
     */
    private static function checkAiNotResponding(): void
    {
        // Check for AI usage in last hour
        $usage = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM ai_usage_log
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        $usageCount = (int)($usage['cnt'] ?? 0);

        // If there IS usage, AI is responding
        if ($usageCount > 0) {
            return;
        }

        // Check if there are AI errors in the last hour
        $errors = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM ai_errors
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        $errorCount = (int)($errors['cnt'] ?? 0);

        // Also check if there are scraped articles waiting (AI should be processing them)
        $waiting = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM articles WHERE status = 'scraped'"
        );

        $waitingCount = (int)($waiting['cnt'] ?? 0);

        // Alert only if there are errors or articles waiting and no successful usage
        if ($errorCount > 0 || $waitingCount > 5) {
            // Get last successful usage
            $lastUsage = Database::fetchOne(
                "SELECT created_at FROM ai_usage_log ORDER BY created_at DESC LIMIT 1"
            );
            $lastTime = $lastUsage['created_at'] ?? 'никогда';

            if (self::canSendAlert('ai_not_responding')) {
                self::send(
                    "🔴 <b>AI API не отвечает</b>\n\n" .
                    "Последняя успешная обработка: {$lastTime}\n" .
                    "Ошибок за час: {$errorCount}\n" .
                    "Статей в очереди: {$waitingCount}",
                    'error'
                );
                self::recordAlertSent('ai_not_responding');
            }
        }
    }

    /**
     * Check if daily AI budget is exhausted.
     */
    private static function checkBudgetExhausted(): void
    {
        $row = Database::fetchOne(
            "SELECT COALESCE(SUM(estimated_cost), 0) as total
             FROM ai_usage_log
             WHERE DATE(created_at) = CURDATE()"
        );

        $spent = (float)($row['total'] ?? 0);
        $budget = Settings::getFloat('ai_daily_budget', 10.00);

        if ($spent >= $budget) {
            if (self::canSendAlert('budget_exhausted')) {
                $spentFormatted = number_format($spent, 2);
                $budgetFormatted = number_format($budget, 2);

                self::send(
                    "💰 <b>Дневной бюджет AI исчерпан</b>\n\n" .
                    "Потрачено: \${$spentFormatted} / \${$budgetFormatted}\n" .
                    "Обработка новых статей приостановлена.",
                    'critical'
                );
                self::recordAlertSent('budget_exhausted');
            }
        }
    }

    /**
     * Check for channel silence (no publications for N hours).
     */
    private static function checkChannelSilence(): void
    {
        $silenceHours = Settings::getInt('channel_silence_hours', 12);

        $silentChannels = Database::fetchAll(
            "SELECT c.id, c.name, c.chat_id,
                    MAX(av.published_at) as last_published
             FROM channels c
             LEFT JOIN article_versions av ON av.channel_id = c.id AND av.status = 'published'
             WHERE c.status = 'active'
             GROUP BY c.id
             HAVING last_published IS NULL
                OR last_published < DATE_SUB(NOW(), INTERVAL ? HOUR)",
            [$silenceHours]
        );

        foreach ($silentChannels as $channel) {
            $alertKey = 'channel_silent_' . $channel['id'];

            if (self::canSendAlert($alertKey)) {
                $lastPublished = $channel['last_published'] ?? 'никогда';

                self::send(
                    "📢 <b>Тишина в канале</b>\n\n" .
                    "Канал: {$channel['name']}\n" .
                    "Последняя публикация: {$lastPublished}\n" .
                    "Прошло более {$silenceHours} часов.",
                    'warning'
                );
                self::recordAlertSent($alertKey);
            }
        }
    }

    /**
     * Check for accumulated manual_review articles.
     */
    private static function checkManualReviewQueue(): void
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM articles WHERE status = 'manual_review'"
        );

        $count = (int)($row['cnt'] ?? 0);

        if ($count > 10) {
            if (self::canSendAlert('manual_review_queue')) {
                self::send(
                    "📝 <b>Очередь ручной проверки</b>\n\n" .
                    "{$count} статей ожидают ручной проверки.\n" .
                    "Зайдите в админку для обработки.",
                    'warning'
                );
                self::recordAlertSent('manual_review_queue');
            }
        }
    }

    /**
     * Check for auto-disabled custom parsers.
     */
    private static function checkParsersDisabled(): void
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM source_parsers
             WHERE is_active = 0
             AND consecutive_errors >= COALESCE(max_errors, 5)"
        );

        $count = (int)($row['cnt'] ?? 0);

        if ($count > 0) {
            if (self::canSendAlert('parsers_disabled')) {
                self::send(
                    "🔧 <b>Парсеры отключены</b>\n\n" .
                    "{$count} custom parser(ов) автоматически отключены из-за ошибок.\n" .
                    "Проверьте логи и настройки парсеров.",
                    'warning'
                );
                self::recordAlertSent('parsers_disabled');
            }
        }
    }

    /**
     * Check for parsers returning zero articles repeatedly.
     */
    private static function checkParsersZeroArticles(): void
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM source_parsers
             WHERE consecutive_zero_articles >= COALESCE(max_zero_runs, 5)
             AND COALESCE(min_articles_threshold, 0) > 0
             AND is_active = 1"
        );

        $count = (int)($row['cnt'] ?? 0);

        if ($count > 0) {
            if (self::canSendAlert('parsers_zero_articles')) {
                self::send(
                    "⚠️ <b>Парсеры не находят статей</b>\n\n" .
                    "{$count} custom parser(ов) не находят статей.\n" .
                    "Проверьте селекторы — возможно, разметка сайта изменилась.",
                    'warning'
                );
                self::recordAlertSent('parsers_zero_articles');
            }
        }
    }

    /**
     * Check for AI errors spike.
     */
    private static function checkAiErrorsSpike(): void
    {
        // Get error count by type in last hour
        $errors = Database::fetchAll(
            "SELECT error_type, COUNT(*) as cnt
             FROM ai_errors
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             GROUP BY error_type
             ORDER BY cnt DESC"
        );

        $totalErrors = array_sum(array_column($errors, 'cnt'));

        if ($totalErrors >= 10) {
            if (self::canSendAlert('ai_errors_spike')) {
                $details = [];
                foreach ($errors as $error) {
                    $details[] = "{$error['error_type']}: {$error['cnt']}";
                }
                $detailsStr = implode("\n", $details);

                self::send(
                    "🔴 <b>Всплеск AI ошибок</b>\n\n" .
                    "{$totalErrors} ошибок за последний час:\n\n" .
                    "<code>{$detailsStr}</code>",
                    'error'
                );
                self::recordAlertSent('ai_errors_spike');
            }
        }
    }

    /**
     * Check if we can send an alert (spam protection).
     */
    private static function canSendAlert(string $alertType): bool
    {
        $key = 'last_alert_' . $alertType;
        $lastSent = Settings::get($key);

        if (empty($lastSent)) {
            return true;
        }

        $lastTime = strtotime($lastSent);
        if ($lastTime === false) {
            return true;
        }

        return (time() - $lastTime) >= self::ALERT_COOLDOWN;
    }

    /**
     * Record that an alert was sent.
     */
    private static function recordAlertSent(string $alertType): void
    {
        $key = 'last_alert_' . $alertType;
        Settings::set($key, date('Y-m-d H:i:s'));
    }

    /**
     * Send consecutive API errors alert.
     * Called from ProcessStep when consecutive errors threshold is reached.
     */
    public static function sendConsecutiveApiErrorsAlert(int $errorCount, string $lastError): bool
    {
        if (!self::canSendAlert('consecutive_api_errors')) {
            return false;
        }

        $result = self::send(
            "🔴 <b>AI API: последовательные ошибки</b>\n\n" .
            "{$errorCount} ошибок подряд.\n" .
            "Последняя ошибка: {$lastError}\n\n" .
            "Обработка временно приостановлена.",
            'error'
        );

        if ($result) {
            self::recordAlertSent('consecutive_api_errors');
        }

        return $result;
    }
}
