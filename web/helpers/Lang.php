<?php declare(strict_types=1);

namespace NewsBot\Web\Helpers;

/**
 * Simple localization helper for admin panel.
 *
 * Usage:
 *   Lang::setLocale('ru');
 *   echo __('dashboard.title');  // "Панель управления"
 *   echo __('common.items_count', ['count' => 5]);  // "5 items"
 */
class Lang
{
    private static string $locale = 'en';
    private static string $fallback = 'en';
    private static array $loaded = [];
    private static ?string $langPath = null;

    /**
     * Initialize language system.
     */
    public static function init(?string $langPath = null): void
    {
        self::$langPath = $langPath ?? dirname(__DIR__) . '/lang';

        // Load locale from session or detect from browser
        if (isset($_SESSION['locale'])) {
            self::$locale = $_SESSION['locale'];
        } elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if (self::isValidLocale($browserLang)) {
                self::$locale = $browserLang;
            }
        }
    }

    /**
     * Set current locale.
     */
    public static function setLocale(string $locale): void
    {
        if (self::isValidLocale($locale)) {
            self::$locale = $locale;
            $_SESSION['locale'] = $locale;
        }
    }

    /**
     * Get current locale.
     */
    public static function getLocale(): string
    {
        return self::$locale;
    }

    /**
     * Get available locales.
     *
     * @return array ['en' => 'English', 'ru' => 'Русский']
     */
    public static function getAvailableLocales(): array
    {
        $locales = [];
        $langPath = self::$langPath ?? dirname(__DIR__) . '/lang';

        if (is_dir($langPath)) {
            foreach (scandir($langPath) as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir($langPath . '/' . $dir)) {
                    // Load locale name from common.php if exists
                    $commonFile = $langPath . '/' . $dir . '/common.php';
                    if (file_exists($commonFile)) {
                        $data = require $commonFile;
                        $locales[$dir] = $data['_locale_name'] ?? strtoupper($dir);
                    } else {
                        $locales[$dir] = strtoupper($dir);
                    }
                }
            }
        }

        return $locales;
    }

    /**
     * Check if locale directory exists.
     */
    public static function isValidLocale(string $locale): bool
    {
        $langPath = self::$langPath ?? dirname(__DIR__) . '/lang';
        return is_dir($langPath . '/' . $locale);
    }

    /**
     * Get translation by key.
     *
     * @param string $key Dot-notation key like "dashboard.title" or "common.save"
     * @param array $params Replacement parameters ['name' => 'John'] for "Hello, :name"
     * @return string Translated string or key if not found
     */
    public static function get(string $key, array $params = []): string
    {
        // Parse key: "dashboard.title" -> file: dashboard, key: title
        $parts = explode('.', $key, 2);

        if (count($parts) !== 2) {
            return $key;
        }

        [$file, $translationKey] = $parts;

        // Try current locale
        $value = self::getFromFile($file, $translationKey, self::$locale);

        // Fallback to default locale
        if ($value === null && self::$locale !== self::$fallback) {
            $value = self::getFromFile($file, $translationKey, self::$fallback);
        }

        // Return key if not found
        if ($value === null) {
            return $key;
        }

        // Replace parameters :name with values
        foreach ($params as $paramKey => $paramValue) {
            $value = str_replace(':' . $paramKey, (string)$paramValue, $value);
        }

        return $value;
    }

    /**
     * Load and get value from language file.
     */
    private static function getFromFile(string $file, string $key, string $locale): ?string
    {
        $cacheKey = $locale . '.' . $file;

        // Load file if not cached
        if (!isset(self::$loaded[$cacheKey])) {
            $langPath = self::$langPath ?? dirname(__DIR__) . '/lang';
            $filePath = $langPath . '/' . $locale . '/' . $file . '.php';

            if (file_exists($filePath)) {
                self::$loaded[$cacheKey] = require $filePath;
            } else {
                self::$loaded[$cacheKey] = [];
            }
        }

        // Support nested keys with dots: "status.active"
        $value = self::$loaded[$cacheKey];
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }

    /**
     * Clear loaded translations cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$loaded = [];
    }
}

/**
 * Global helper function for translations.
 *
 * @param string $key Translation key like "dashboard.title"
 * @param array $params Optional replacement parameters
 * @return string Translated string
 */
function __($key, array $params = []): string
{
    return Lang::get($key, $params);
}
