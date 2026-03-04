<?php declare(strict_types=1);

namespace NewsBot\Models;

use NewsBot\Core\Crypto;

/**
 * Telegram bot model.
 */
class Bot extends BaseModel
{
    protected static string $table = 'bots';

    /**
     * Get decrypted bot token.
     * Reads from encrypted_token and decrypts via Crypto.
     * Falls back to plain token field for legacy data.
     */
    public function getToken(): string
    {
        // Try encrypted token first
        if (!empty($this->encrypted_token)) {
            return Crypto::decrypt($this->encrypted_token);
        }

        // Fallback to plain token (legacy)
        return $this->token ?? '';
    }

    /**
     * Get all active bots.
     */
    public static function getActive(): array
    {
        return self::all("status = 'active'");
    }

    /**
     * Get bot by type.
     */
    public static function getByType(string $type): array
    {
        return self::all("type = ? AND status = 'active'", [$type]);
    }

    /**
     * Get publishing bots.
     */
    public static function getPublishingBots(): array
    {
        return self::getByType('publishing');
    }

    /**
     * Get service bot (for alerts).
     */
    public static function getServiceBot(): ?self
    {
        return self::findBy('type', 'service');
    }

    /**
     * Check if bot is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get channels using this bot.
     */
    public function getChannels(): array
    {
        return Channel::all('bot_id = ?', [$this->id]);
    }
}
