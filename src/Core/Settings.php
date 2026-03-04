<?php declare(strict_types=1);

namespace NewsBot\Core;

/**
 * Key-value settings from database table.
 * Caches values in memory for the duration of the request.
 */
class Settings
{
    private static array $cache = [];
    private static bool $loaded = false;

    /**
     * Get a setting value.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        self::ensureLoaded();

        return self::$cache[$key] ?? $default;
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, string $value): void
    {
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$key, $value]
        );

        self::$cache[$key] = $value;
    }

    /**
     * Get setting as integer.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value !== null ? (int)$value : $default;
    }

    /**
     * Get setting as float.
     */
    public static function getFloat(string $key, float $default = 0.0): float
    {
        $value = self::get($key);
        return $value !== null ? (float)$value : $default;
    }

    /**
     * Get setting as boolean.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Clear in-memory cache.
     * Call between pipeline steps in master.php to pick up settings changed via admin UI.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$loaded = false;
    }

    /**
     * Load all settings into cache.
     */
    private static function ensureLoaded(): void
    {
        if (self::$loaded) {
            return;
        }

        try {
            $rows = Database::fetchAll("SELECT `key`, `value` FROM settings");
            foreach ($rows as $row) {
                self::$cache[$row['key']] = $row['value'];
            }
            self::$loaded = true;
        } catch (\Throwable $e) {
            Logger::error('Failed to load settings: ' . $e->getMessage());
            self::$loaded = true; // Prevent infinite retry
        }
    }

    /**
     * Check if a key exists.
     */
    public static function has(string $key): bool
    {
        self::ensureLoaded();
        return array_key_exists($key, self::$cache);
    }

    /**
     * Delete a setting.
     */
    public static function delete(string $key): void
    {
        Database::delete('settings', '`key` = ?', [$key]);
        unset(self::$cache[$key]);
    }

    /**
     * Get all settings (for admin display).
     */
    public static function all(): array
    {
        self::ensureLoaded();
        return self::$cache;
    }
}
