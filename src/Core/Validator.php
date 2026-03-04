<?php declare(strict_types=1);

namespace NewsBot\Core;

/**
 * Input validation utilities.
 */
class Validator
{
    /**
     * Validate IANA timezone.
     */
    public static function timezone(string $tz): bool
    {
        if (empty($tz)) {
            return false;
        }

        try {
            new \DateTimeZone($tz);
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Validate URL (http/https only).
     */
    public static function url(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // Basic format check
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        // Only allow http/https
        $parsed = parse_url($url);
        return isset($parsed['scheme'], $parsed['host'])
            && in_array($parsed['scheme'], ['http', 'https'], true);
    }

    /**
     * Validate Telegram chat ID format.
     * Valid formats: @channel, -100xxx (supergroup/channel), positive number (user)
     */
    public static function chatId(string $id): bool
    {
        if (empty($id)) {
            return false;
        }

        // @username format (min 5 chars after @)
        if (preg_match('/^@[a-zA-Z][a-zA-Z0-9_]{4,}$/', $id)) {
            return true;
        }

        // Negative group/channel ID
        if (preg_match('/^-\d+$/', $id)) {
            return true;
        }

        // Positive user ID
        if (preg_match('/^\d+$/', $id)) {
            return true;
        }

        return false;
    }

    /**
     * Validate XPath expression syntax.
     */
    public static function xpath(string $expr): bool
    {
        if (empty($expr)) {
            return false;
        }

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML('<html><body></body></html>');
        $xpath = new \DOMXPath($dom);

        $result = @$xpath->query($expr);
        libxml_clear_errors();

        return $result !== false;
    }

    /**
     * Validate CSS selector (basic check).
     */
    public static function cssSelector(string $sel): bool
    {
        if (empty($sel)) {
            return false;
        }

        // Basic pattern: alphanumeric, ., #, [], =, quotes, :, spaces, combinators
        return preg_match('/^[a-zA-Z0-9_.\-#\[\]=\'":\s\>\+\~\*\(\),]+$/', $sel) === 1;
    }

    /**
     * Validate email address.
     */
    public static function email(string $email): bool
    {
        return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate integer in range.
     */
    public static function intRange(mixed $value, int $min, int $max): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $int = (int)$value;
        return $int >= $min && $int <= $max;
    }

    /**
     * Validate string length.
     */
    public static function stringLength(string $str, int $min, int $max): bool
    {
        $len = mb_strlen($str);
        return $len >= $min && $len <= $max;
    }

    /**
     * Validate JSON string.
     */
    public static function json(string $str): bool
    {
        if (empty($str)) {
            return false;
        }

        json_decode($str);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Sanitize string for safe output.
     */
    public static function sanitize(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
