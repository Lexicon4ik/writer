<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\Logger;

/**
 * Image validator for Telegram.
 * Validates image URL before sending to Telegram Bot API.
 */
class ImageValidator
{
    /**
     * Maximum image size in bytes (5MB - Telegram limit).
     */
    public const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    /**
     * Maximum image dimension (Telegram limit).
     */
    public const MAX_DIMENSION = 10000;

    /**
     * Allowed content types for images.
     */
    private const ALLOWED_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Check if image URL is valid for Telegram.
     *
     * @param string $url Image URL to validate
     * @return array{valid: bool, reason: ?string, size: int}
     */
    public static function check(string $url): array
    {
        if (empty($url)) {
            return ['valid' => false, 'reason' => 'Empty URL', 'size' => 0];
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'reason' => 'Invalid URL format', 'size' => 0];
        }

        // Check protocol
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array(strtolower($scheme), ['http', 'https'])) {
            return ['valid' => false, 'reason' => 'URL must be HTTP(S)', 'size' => 0];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['valid' => false, 'reason' => 'cURL init failed', 'size' => 0];
        }

        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'NewsBot/1.0 ImageValidator',
        ]);

        curl_exec($ch);

        $error = curl_error($ch);
        if ($error) {
            curl_close($ch);
            Logger::debug('Image validation failed: cURL error', ['url' => $url, 'error' => $error]);
            return ['valid' => false, 'reason' => "cURL error: {$error}", 'size' => 0];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentLength = (int)curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        curl_close($ch);

        // Check HTTP status
        if ($httpCode !== 200) {
            return ['valid' => false, 'reason' => "HTTP {$httpCode}", 'size' => 0];
        }

        // Check content type
        if ($contentType) {
            // Extract main type (ignore charset etc)
            $mainType = explode(';', $contentType)[0];
            $mainType = trim(strtolower($mainType));

            if (!str_starts_with($mainType, 'image/')) {
                return ['valid' => false, 'reason' => "Not an image: {$contentType}", 'size' => 0];
            }

            if (!empty(self::ALLOWED_TYPES) && !in_array($mainType, self::ALLOWED_TYPES)) {
                return ['valid' => false, 'reason' => "Unsupported image type: {$mainType}", 'size' => $contentLength];
            }
        }

        // Check file size (if Content-Length header is present)
        if ($contentLength > 0 && $contentLength > self::MAX_SIZE_BYTES) {
            $sizeMb = round($contentLength / 1024 / 1024, 1);
            return [
                'valid' => false,
                'reason' => "Image too large: {$sizeMb}MB (max 5MB)",
                'size' => $contentLength,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'size' => $contentLength,
        ];
    }

    /**
     * Check if URL is accessible (HTTP 200).
     *
     * @param string $url URL to check
     * @return bool True if accessible
     */
    public static function isAccessible(string $url): bool
    {
        $result = self::check($url);
        return $result['valid'];
    }

    /**
     * Extract image URL from HTML content.
     * Looks for og:image, twitter:image, or first img tag.
     *
     * @param string $html HTML content
     * @param string $baseUrl Base URL for resolving relative URLs
     * @return string|null Image URL or null
     */
    public static function extractFromHtml(string $html, string $baseUrl = ''): ?string
    {
        // Try og:image first
        if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
            return self::resolveUrl($m[1], $baseUrl);
        }

        // Try twitter:image
        if (preg_match('/<meta[^>]*name=["\']twitter:image["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
            return self::resolveUrl($m[1], $baseUrl);
        }

        // Fallback to first large image in content
        if (preg_match_all('/<img[^>]*src=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                // Skip small images, icons, data URIs
                if (preg_match('/\.(gif|ico|svg)$/i', $src)) {
                    continue;
                }
                if (str_starts_with($src, 'data:')) {
                    continue;
                }
                if (preg_match('/(icon|logo|button|banner|ad)/i', $src)) {
                    continue;
                }

                return self::resolveUrl($src, $baseUrl);
            }
        }

        return null;
    }

    /**
     * Resolve relative URL against base URL.
     */
    private static function resolveUrl(string $url, string $baseUrl): string
    {
        if (empty($url)) {
            return '';
        }

        // Already absolute
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        // Protocol-relative
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (empty($baseUrl)) {
            return $url;
        }

        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';

        if (str_starts_with($url, '/')) {
            // Absolute path
            return "{$scheme}://{$host}{$url}";
        }

        // Relative path
        $basePath = $parsed['path'] ?? '/';
        $baseDir = dirname($basePath);
        return "{$scheme}://{$host}{$baseDir}/{$url}";
    }
}
