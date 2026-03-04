<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\Logger;
use NewsBot\Models\{Article, ArticleVersion, WebsiteEndpoint};

/**
 * REST publisher for website endpoints.
 * Builds HTTP payload from article data and sends it to a REST API.
 */
class RestPublisher
{
    private const CONNECT_TIMEOUT = 10;
    private const REQUEST_TIMEOUT = 30;

    /**
     * Build the HTTP payload for a REST endpoint.
     * Applies field mapping and static extras from endpoint config.
     *
     * @param ArticleVersion $version Processed article version (content source)
     * @param Article $article Original article (URLs, dates, source)
     * @param WebsiteEndpoint $endpoint Endpoint configuration
     * @return array Ready-to-send payload
     */
    public function buildPayload(
        ArticleVersion $version,
        Article $article,
        WebsiteEndpoint $endpoint
    ): array {
        $sourceData = $this->collectSourceData($version, $article);
        $payload = [];

        foreach ($endpoint->getFieldMapping() as $sourceField => $targetConfig) {
            if (!array_key_exists($sourceField, $sourceData)) {
                continue;
            }

            [$targetField, $transform] = $this->parseTargetConfig($targetConfig);

            if (empty($targetField)) {
                continue;
            }

            $value = $this->applyTransform($sourceData[$sourceField], $transform);
            $this->setNestedValue($payload, $targetField, $value);
        }

        // Merge static extras — extras do NOT override mapped fields
        foreach ($endpoint->getPayloadExtras() as $key => $value) {
            if (!isset($payload[$key])) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Publish article version to a REST endpoint.
     * Returns ['external_id' => ..., 'external_url' => ...] on success.
     *
     * @throws RestException on HTTP or network error
     */
    public function publish(
        ArticleVersion $version,
        Article $article,
        WebsiteEndpoint $endpoint
    ): array {
        $payload = $this->buildPayload($version, $article, $endpoint);
        $headers = $this->buildAuthHeaders($endpoint);

        $contentType = $endpoint->content_type ?? 'application/json';
        $headers[] = 'Content-Type: ' . $contentType;

        $method = strtoupper($endpoint->http_method ?? 'POST');

        $body = $contentType === 'application/json'
            ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : http_build_query($payload);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint->api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::REQUEST_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        // Network / cURL error
        if ($responseBody === false) {
            throw new RestException(
                'Network error: ' . $curlError,
                0,
                $endpoint->getRetryHttpCodes()
            );
        }

        $successCodes = $endpoint->getSuccessHttpCodes();

        if (!in_array($httpCode, $successCodes, true)) {
            Logger::warning('REST publish failed', [
                'endpoint_id' => $endpoint->id,
                'http_code'   => $httpCode,
                'response'    => mb_substr((string)$responseBody, 0, 500),
            ]);
            throw new RestException(
                "HTTP {$httpCode}: " . mb_substr((string)$responseBody, 0, 200),
                $httpCode,
                $endpoint->getRetryHttpCodes()
            );
        }

        // Parse response for external ID / URL
        $result = ['external_id' => null, 'external_url' => null];
        $decoded = json_decode((string)$responseBody, true);

        if (is_array($decoded)) {
            if (!empty($endpoint->external_id_path)) {
                $val = $this->getNestedValue($decoded, $endpoint->external_id_path);
                if ($val !== null) {
                    $result['external_id'] = (string)$val;
                }
            }
            if (!empty($endpoint->external_url_path)) {
                $val = $this->getNestedValue($decoded, $endpoint->external_url_path);
                if ($val !== null) {
                    $result['external_url'] = (string)$val;
                }
            }
        }

        Logger::info('REST publish succeeded', [
            'endpoint_id' => $endpoint->id,
            'article_id'  => $article->id,
            'http_code'   => $httpCode,
            'external_id' => $result['external_id'],
        ]);

        return $result;
    }

    /**
     * Build authentication headers based on endpoint config.
     */
    private function buildAuthHeaders(WebsiteEndpoint $endpoint): array
    {
        $authType = $endpoint->auth_type ?? 'none';

        if ($authType === 'none') {
            return [];
        }

        $credential = $endpoint->getCredential();

        return match ($authType) {
            'bearer'        => ['Authorization: Bearer ' . $credential],
            'basic'         => ['Authorization: Basic ' . base64_encode($credential)],
            'api_key'       => [($endpoint->auth_header_name ?? 'X-API-Key') . ': ' . $credential],
            'custom_header' => [($endpoint->auth_header_name ?? 'X-Token') . ': ' . $credential],
            default         => [],
        };
    }

    /**
     * Collect all available source data from article + version.
     * These are the internal field names available in field_mapping.
     */
    private function collectSourceData(ArticleVersion $version, Article $article): array
    {
        $body     = $version->body ?? '';
        $hashtags = $version->getHashtags();

        $date    = '';
        $dateIso = '';
        $ts = null;

        if (!empty($article->rss_pub_date)) {
            $ts = strtotime($article->rss_pub_date);
        } elseif (!empty($article->created_at)) {
            $ts = strtotime($article->created_at);
        }

        if ($ts) {
            $date    = date('Y-m-d', $ts);
            $dateIso = date('c', $ts);
        }

        return [
            'title'            => $version->title ?? '',
            'short_title'      => $version->short_title ?? '',
            'description'      => $version->description ?? '',
            'body'             => $body,
            'body_plain'       => strip_tags($body),
            'hashtags'         => $hashtags,
            'url'              => $article->url ?? '',
            'image_url'        => $article->scraped_image_url ?? $article->rss_image_url ?? '',
            'date'             => $date,
            'date_iso'         => $dateIso,
            'source_name'      => $article->getSource()?->name ?? '',
            'importance_score' => (int)($version->importance_score ?? 0),
        ];
    }

    /**
     * Parse target config: string or array with 'to' and optional 'transform'.
     * Returns [$targetField, $transform].
     */
    private function parseTargetConfig(mixed $config): array
    {
        if (is_string($config)) {
            return [$config, null];
        }
        if (is_array($config)) {
            return [$config['to'] ?? '', $config['transform'] ?? null];
        }
        return ['', null];
    }

    /**
     * Apply a named transformation to a value.
     *
     * strip_html  — removes HTML tags (for Telegram-formatted body)
     * array       — ensures value is an array (for hashtags → JSON array)
     * csv         — converts array to comma-separated string
     * iso8601     — date as ISO 8601 (use date_iso field instead)
     * plain_date  — date as YYYY-MM-DD (use date field instead)
     */
    private function applyTransform(mixed $value, ?string $transform): mixed
    {
        return match ($transform) {
            'strip_html' => strip_tags((string)$value),
            'array'      => is_array($value) ? $value : [$value],
            'csv'        => is_array($value) ? implode(', ', $value) : (string)$value,
            'iso8601',
            'plain_date' => $value, // already formatted in collectSourceData
            default      => $value,
        };
    }

    /**
     * Set a nested value in an array using dot notation.
     * "meta.source_url" sets $arr['meta']['source_url'] = $value.
     */
    private function setNestedValue(array &$array, string $path, mixed $value): void
    {
        $keys    = explode('.', $path);
        $current = &$array;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    /**
     * Get a nested value from an array using dot notation.
     * "data.post_id" reads $arr['data']['post_id'].
     */
    private function getNestedValue(array $array, string $path): mixed
    {
        $keys    = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }
}
