<?php declare(strict_types=1);

namespace NewsBot\Services;

/**
 * URL canonization for deduplication.
 * Normalizes URLs to prevent duplicates with different query params.
 */
class UrlCanonizer
{
    /**
     * Query parameters to remove (tracking, session, etc.)
     */
    private const REMOVE_PARAMS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'utm_id',
        'utm_cid',
        'fbclid',
        'gclid',
        'ref',
        'ref_src',
        'ref_url',
        'referrer',
        '_ga',
        '_gl',
        'mc_cid',
        'mc_eid',
        'oly_anon_id',
        'oly_enc_id',
        '__twitter_impression',
        'twclid',
        'igshid',
        'ncid',
        'sr_share',
    ];

    /**
     * Canonize URL:
     * 1. Parse URL via parse_url
     * 2. Lowercase scheme and host
     * 3. Remove tracking parameters
     * 4. Remove fragment (#...)
     * 5. Remove trailing slash (except root)
     * 6. Rebuild URL
     *
     * @param string $url URL to canonize
     * @return string Canonical URL
     */
    public static function canonize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['host'])) {
            // Invalid URL or no host - return as-is trimmed
            return $url;
        }

        // Lowercase scheme and host
        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host = strtolower($parsed['host']);

        // Remove www. prefix for consistency
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        // Port (only include if non-standard)
        $port = '';
        if (isset($parsed['port'])) {
            $defaultPort = ($scheme === 'https') ? 443 : 80;
            if ($parsed['port'] !== $defaultPort) {
                $port = ':' . $parsed['port'];
            }
        }

        // Path: decode and re-encode for normalization
        $path = $parsed['path'] ?? '/';
        $path = self::normalizePath($path);

        // Remove trailing slash (except for root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Query: remove tracking params, sort remaining
        $query = '';
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $params);

            // Remove tracking parameters (case-insensitive)
            $removeParamsLower = array_map('strtolower', self::REMOVE_PARAMS);
            foreach ($params as $key => $value) {
                if (in_array(strtolower($key), $removeParamsLower, true)) {
                    unset($params[$key]);
                }
            }

            // Sort remaining params for consistency
            if (!empty($params)) {
                ksort($params);
                $query = '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            }
        }

        // Fragment is intentionally excluded

        return $scheme . '://' . $host . $port . $path . $query;
    }

    /**
     * Get SHA-256 hash of canonical URL.
     *
     * @param string $url URL to hash
     * @return string 64-character hex hash
     */
    public static function hash(string $url): string
    {
        $canonical = self::canonize($url);
        return hash('sha256', $canonical);
    }

    /**
     * Normalize URL path:
     * - Decode percent-encoded characters that don't need encoding
     * - Re-encode special characters
     * - Resolve . and .. segments
     *
     * @param string $path URL path
     * @return string Normalized path
     */
    private static function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        // Decode the path first
        $decoded = rawurldecode($path);

        // Resolve . and .. segments
        $segments = explode('/', $decoded);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '..') {
                array_pop($resolved);
            } elseif ($segment !== '.' && $segment !== '') {
                $resolved[] = $segment;
            }
        }

        // Re-encode each segment
        $encoded = array_map('rawurlencode', $resolved);

        return '/' . implode('/', $encoded);
    }
}
