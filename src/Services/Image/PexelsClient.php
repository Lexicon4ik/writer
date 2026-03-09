<?php declare(strict_types=1);

namespace NewsBot\Services\Image;

use NewsBot\Core\{Crypto, Logger, Settings};

/**
 * Pexels API client for searching stock photos.
 * Free tier: 200 requests/hour. License: commercial use allowed.
 */
class PexelsClient
{
    private const API_BASE    = 'https://api.pexels.com/v1/';
    private const TIMEOUT     = 15;
    private const ORIENTATION = 'landscape';
    private const MIN_WIDTH   = 600;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = Crypto::decryptSafe(Settings::get('pexels_api_key', ''));
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Search for photos by query.
     * Returns array of photo data or empty array on failure.
     *
     * @param string $query      Search query (English)
     * @param int    $perPage    Results per page (max 80)
     * @return array[] Array of photo records with keys: id, url, width, height, photographer, photographer_url, src_large2x
     */
    public function search(string $query, int $perPage = 5): array
    {
        if (!$this->isConfigured()) {
            Logger::debug('PexelsClient: API key not configured');
            return [];
        }

        $params = http_build_query([
            'query'       => $query,
            'per_page'    => min($perPage, 80),
            'orientation' => self::ORIENTATION,
        ]);

        $ch = curl_init(self::API_BASE . 'search?' . $params);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $this->apiKey,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200 || !$response) {
            Logger::warning('PexelsClient: search failed', [
                'query'     => $query,
                'http_code' => $httpCode,
                'curl_err'  => $curlErr,
            ]);
            return [];
        }

        $data   = json_decode($response, true);
        $photos = $data['photos'] ?? [];

        $results = [];
        foreach ($photos as $photo) {
            $width = (int)($photo['width'] ?? 0);
            if ($width < self::MIN_WIDTH) {
                continue;
            }

            $results[] = [
                'id'                => (string)$photo['id'],
                'url'               => $photo['src']['large2x'] ?? $photo['src']['large'] ?? $photo['src']['original'] ?? '',
                'width'             => $width,
                'height'            => (int)($photo['height'] ?? 0),
                'photographer'      => $photo['photographer'] ?? '',
                'photographer_url'  => $photo['photographer_url'] ?? '',
                'source_url'        => $photo['url'] ?? '',
                'alt_text'          => $photo['alt'] ?? '',
            ];
        }

        Logger::debug('PexelsClient: search results', [
            'query'   => $query,
            'found'   => count($results),
        ]);

        return $results;
    }

    /**
     * Search with multiple queries, return first good result.
     *
     * @param string[] $queries
     */
    public function searchMulti(array $queries): ?array
    {
        foreach ($queries as $query) {
            if (empty(trim($query))) {
                continue;
            }

            $results = $this->search($query, 3);
            if (!empty($results)) {
                return $results[0];
            }
        }

        return null;
    }
}
