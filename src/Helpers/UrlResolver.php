<?php declare(strict_types=1);

namespace NewsBot\Helpers;

/**
 * Resolves relative URLs against a base URL.
 */
class UrlResolver
{
    /**
     * Resolve a relative URL against a base URL.
     *
     * Examples:
     *   resolve('/news/123', 'https://example.com/feed/rss') → 'https://example.com/news/123'
     *   resolve('article.html', 'https://example.com/category/') → 'https://example.com/category/article.html'
     *   resolve('../page', 'https://example.com/a/b/') → 'https://example.com/a/page'
     *   resolve('//other.com/path', 'https://example.com') → 'https://other.com/path'
     *   resolve('https://other.com/page', 'https://example.com') → 'https://other.com/page'
     *
     * @param string $relativeUrl URL to resolve (may be absolute)
     * @param string $baseUrl Base URL for resolution
     * @return string Resolved absolute URL
     */
    public static function resolve(string $relativeUrl, string $baseUrl): string
    {
        $relativeUrl = trim($relativeUrl);
        $baseUrl = trim($baseUrl);

        if ($relativeUrl === '') {
            return $baseUrl;
        }

        // Already absolute URL
        if (preg_match('#^https?://#i', $relativeUrl)) {
            return $relativeUrl;
        }

        $baseParsed = parse_url($baseUrl);
        if ($baseParsed === false || !isset($baseParsed['scheme'], $baseParsed['host'])) {
            // Invalid base URL - return relative as-is
            return $relativeUrl;
        }

        $scheme = $baseParsed['scheme'];
        $host = $baseParsed['host'];
        $port = isset($baseParsed['port']) ? ':' . $baseParsed['port'] : '';

        // Protocol-relative URL (//host/path)
        if (str_starts_with($relativeUrl, '//')) {
            return $scheme . ':' . $relativeUrl;
        }

        // Absolute path (/path)
        if (str_starts_with($relativeUrl, '/')) {
            return $scheme . '://' . $host . $port . $relativeUrl;
        }

        // Relative path (path, ../path)
        $basePath = $baseParsed['path'] ?? '/';

        // If base path doesn't end with / and has a file component, get directory
        if (!str_ends_with($basePath, '/')) {
            $basePath = dirname($basePath);
            if ($basePath !== '/') {
                $basePath .= '/';
            }
        }

        // Combine base path with relative path
        $combinedPath = $basePath . $relativeUrl;

        // Resolve . and .. segments
        $resolvedPath = self::resolvePath($combinedPath);

        return $scheme . '://' . $host . $port . $resolvedPath;
    }

    /**
     * Resolve path segments (. and ..).
     *
     * @param string $path Path with potential . and .. segments
     * @return string Resolved path
     */
    private static function resolvePath(string $path): string
    {
        // Split path and query/fragment
        $parts = explode('?', $path, 2);
        $pathOnly = $parts[0];
        $queryString = isset($parts[1]) ? '?' . $parts[1] : '';

        // Handle fragment in query
        $qParts = explode('#', $queryString, 2);
        $queryString = $qParts[0];
        // Fragment is dropped

        // Resolve path segments
        $segments = explode('/', $pathOnly);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '..') {
                // Go up one directory (but don't go above root)
                if (count($resolved) > 1 || (count($resolved) === 1 && $resolved[0] !== '')) {
                    array_pop($resolved);
                }
            } elseif ($segment !== '.') {
                $resolved[] = $segment;
            }
        }

        $result = implode('/', $resolved);

        // Ensure path starts with /
        if (!str_starts_with($result, '/')) {
            $result = '/' . $result;
        }

        return $result . $queryString;
    }
}
