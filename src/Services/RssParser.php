<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\Database;
use NewsBot\Core\Logger;
use NewsBot\Helpers\ThaiDateConverter;
use NewsBot\Helpers\UrlResolver;

/**
 * RSS/Atom/JSON Feed parser with auto-correction and Thai date support.
 */
class RssParser
{
    private const CONNECT_TIMEOUT = 10;
    private const TOTAL_TIMEOUT = 15;
    private const MAX_REDIRECTS = 3;

    /**
     * Parse an RSS/Atom/JSON feed and return articles.
     *
     * @param string $feedUrl Feed URL
     * @param string $dateFilter Filter type: 'none', 'today', 'hours'
     * @param int|null $dateFilterHours Hours to filter (if dateFilter is 'hours')
     * @return array Array of articles: [{title, link, guid, pub_date, description, content, image_url}, ...]
     */
    public function parse(string $feedUrl, string $dateFilter = 'none', ?int $dateFilterHours = null): array
    {
        $response = $this->fetch($feedUrl);
        if ($response === null) {
            throw new \RuntimeException("Failed to fetch feed: {$feedUrl}");
        }

        $contentType = $response['content_type'] ?? '';
        $body = $response['body'];
        $baseUrl = $response['effective_url'] ?? $feedUrl;

        // Detect format by content type or content
        if ($this->isJsonFeed($contentType, $body)) {
            $items = $this->parseJsonFeed($body, $baseUrl);
        } else {
            $items = $this->parseXmlFeed($body, $baseUrl);
        }

        // Deduplicate within batch by canonical URL
        $items = $this->deduplicateBatch($items);

        // Filter by date
        $items = $this->filterByDate($items, $dateFilter, $dateFilterHours);

        return $items;
    }

    /**
     * Fetch feed content via cURL.
     *
     * @return array|null {body: string, content_type: string, effective_url: string} or null on failure
     */
    private function fetch(string $url): ?array
    {
        $userAgent = $this->getRandomUserAgent();

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: application/rss+xml, application/atom+xml, application/json, application/feed+json, application/xml, text/xml, */*',
                'Accept-Language: en-US,en;q=0.9,th;q=0.8',
            ],
            CURLOPT_ENCODING => '', // Accept any encoding
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $httpCode !== 200) {
            Logger::warning('RSS fetch failed', [
                'url' => $url,
                'http_code' => $httpCode,
                'error' => $error,
            ]);
            return null;
        }

        return [
            'body' => $body,
            'content_type' => $contentType,
            'effective_url' => $effectiveUrl,
        ];
    }

    /**
     * Check if content is JSON Feed format.
     */
    private function isJsonFeed(string $contentType, string $body): bool
    {
        // Check content type
        if (str_contains($contentType, 'application/json') ||
            str_contains($contentType, 'application/feed+json')) {
            return true;
        }

        // Check if body starts with JSON
        $trimmed = ltrim($body);
        return str_starts_with($trimmed, '{') && str_contains($body, '"items"');
    }

    /**
     * Parse JSON Feed (https://jsonfeed.org/version/1.1).
     */
    private function parseJsonFeed(string $body, string $baseUrl): array
    {
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['items'])) {
            return [];
        }

        $items = [];
        foreach ($data['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $link = $item['url'] ?? $item['external_url'] ?? '';
            if (empty($link)) {
                continue;
            }

            // Resolve relative URLs
            $link = UrlResolver::resolve($link, $baseUrl);

            $title = $this->cleanText($item['title'] ?? '');
            if (empty($title)) {
                continue;
            }

            // Date
            $pubDate = null;
            $dateStr = $item['date_published'] ?? $item['date_modified'] ?? null;
            if ($dateStr) {
                $pubDate = ThaiDateConverter::convert($dateStr);
            }

            // Content
            $content = $item['content_html'] ?? $item['content_text'] ?? null;
            $description = $item['summary'] ?? null;

            // Image
            $imageUrl = $item['image'] ?? $item['banner_image'] ?? null;

            $items[] = [
                'title' => $title,
                'link' => $link,
                'guid' => $item['id'] ?? null,
                'pub_date' => $pubDate,
                'description' => $description ? $this->cleanText($description) : null,
                'content' => $content,
                'image_url' => $imageUrl,
            ];
        }

        return $items;
    }

    /**
     * Parse XML feed (RSS 2.0 or Atom).
     */
    private function parseXmlFeed(string $body, string $baseUrl): array
    {
        // Auto-fix common XML issues
        $body = $this->fixXml($body);

        // Try SimpleXML first
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);

        if ($xml === false) {
            // Fallback to DOMDocument with recovery
            $xml = $this->loadWithDom($body);
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $errorMsg = !empty($errors) ? $errors[0]->message : 'Unknown XML error';
                throw new \RuntimeException("Failed to parse XML: {$errorMsg}");
            }
        }

        libxml_clear_errors();

        // Detect feed type and parse accordingly
        $rootName = $xml->getName();

        if ($rootName === 'feed') {
            // Atom feed
            return $this->parseAtom($xml, $baseUrl);
        }

        // RSS 2.0 (rss > channel > item) or RSS 1.0 (rdf:RDF > item)
        return $this->parseRss($xml, $baseUrl);
    }

    /**
     * Fix common XML issues.
     */
    private function fixXml(string $body): string
    {
        // Remove BOM
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);

        // Remove invalid XML characters (keep tab, newline, carriage return)
        $body = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $body);

        // Fix duplicate namespace declarations (keep first occurrence)
        $namespaces = [];
        $body = preg_replace_callback(
            '/\bxmlns:(\w+)=["\'][^"\']+["\']/',
            function ($match) use (&$namespaces) {
                $prefix = $match[1];
                if (isset($namespaces[$prefix])) {
                    return ''; // Remove duplicate
                }
                $namespaces[$prefix] = true;
                return $match[0];
            },
            $body
        );

        // Fix unescaped ampersands in content (not in entities)
        $body = preg_replace('/&(?!(?:amp|lt|gt|quot|apos|#\d+|#x[0-9a-fA-F]+);)/', '&amp;', $body);

        return $body;
    }

    /**
     * Load XML using DOMDocument with recovery mode.
     */
    private function loadWithDom(string $body): \SimpleXMLElement|false
    {
        $dom = new \DOMDocument();
        $dom->recover = true;
        $dom->strictErrorChecking = false;

        if (!$dom->loadXML($body, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET)) {
            return false;
        }

        return simplexml_import_dom($dom);
    }

    /**
     * Parse RSS 2.0 format.
     */
    private function parseRss(\SimpleXMLElement $xml, string $baseUrl): array
    {
        $items = [];

        // RSS 2.0: rss > channel > item
        $rssItems = $xml->channel->item ?? [];
        if (empty($rssItems)) {
            // RSS 1.0 / RDF: item at root level
            $rssItems = $xml->item ?? [];
        }

        // Register namespaces
        $namespaces = $xml->getNamespaces(true);

        foreach ($rssItems as $item) {
            $parsed = $this->parseRssItem($item, $baseUrl, $namespaces);
            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        return $items;
    }

    /**
     * Parse a single RSS item.
     */
    private function parseRssItem(\SimpleXMLElement $item, string $baseUrl, array $namespaces): ?array
    {
        // Link (required)
        $link = (string)($item->link ?? '');
        if (empty($link)) {
            return null;
        }

        // Resolve relative URL
        if (!preg_match('#^https?://#i', $link)) {
            $link = UrlResolver::resolve($link, $baseUrl);
        }

        // Title (required)
        $title = $this->cleanText((string)($item->title ?? ''));
        if (empty($title)) {
            return null;
        }

        // GUID
        $guid = (string)($item->guid ?? '') ?: null;

        // Date
        $pubDate = null;
        $dateStr = (string)($item->pubDate ?? '');
        if (empty($dateStr) && isset($namespaces['dc'])) {
            $dc = $item->children($namespaces['dc']);
            $dateStr = (string)($dc->date ?? '');
        }
        if (!empty($dateStr)) {
            $pubDate = ThaiDateConverter::convert($dateStr);
        }

        // Description
        $description = (string)($item->description ?? '') ?: null;

        // Content (content:encoded)
        $content = null;
        if (isset($namespaces['content'])) {
            $contentNs = $item->children($namespaces['content']);
            $content = (string)($contentNs->encoded ?? '') ?: null;
        }

        // Image URL (multiple sources)
        $imageUrl = $this->extractImageFromRssItem($item, $namespaces);

        return [
            'title' => $title,
            'link' => $link,
            'guid' => $guid,
            'pub_date' => $pubDate,
            'description' => $description,
            'content' => $content,
            'image_url' => $imageUrl,
        ];
    }

    /**
     * Extract image URL from RSS item.
     */
    private function extractImageFromRssItem(\SimpleXMLElement $item, array $namespaces): ?string
    {
        // 1. media:content
        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);

            // media:content with image type
            if (isset($media->content)) {
                foreach ($media->content as $mediaContent) {
                    $attrs = $mediaContent->attributes();
                    $url = (string)($attrs['url'] ?? '');
                    $type = (string)($attrs['type'] ?? '');
                    $medium = (string)($attrs['medium'] ?? '');

                    if (!empty($url) && ($medium === 'image' || str_starts_with($type, 'image/'))) {
                        return $url;
                    }
                }
                // First media:content if no explicit image
                $firstMedia = $media->content[0] ?? null;
                if ($firstMedia) {
                    $attrs = $firstMedia->attributes();
                    $url = (string)($attrs['url'] ?? '');
                    if (!empty($url)) {
                        return $url;
                    }
                }
            }

            // media:thumbnail
            if (isset($media->thumbnail)) {
                $attrs = $media->thumbnail->attributes();
                $url = (string)($attrs['url'] ?? '');
                if (!empty($url)) {
                    return $url;
                }
            }
        }

        // 2. enclosure with image type
        if (isset($item->enclosure)) {
            $attrs = $item->enclosure->attributes();
            $url = (string)($attrs['url'] ?? '');
            $type = (string)($attrs['type'] ?? '');

            if (!empty($url) && str_starts_with($type, 'image/')) {
                return $url;
            }
        }

        // 3. image element (some feeds)
        if (isset($item->image)) {
            $imageUrl = (string)($item->image ?? '');
            if (!empty($imageUrl)) {
                return $imageUrl;
            }
        }

        return null;
    }

    /**
     * Parse Atom format.
     */
    private function parseAtom(\SimpleXMLElement $xml, string $baseUrl): array
    {
        $items = [];
        $namespaces = $xml->getNamespaces(true);

        foreach ($xml->entry as $entry) {
            $parsed = $this->parseAtomEntry($entry, $baseUrl, $namespaces);
            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        return $items;
    }

    /**
     * Parse a single Atom entry.
     */
    private function parseAtomEntry(\SimpleXMLElement $entry, string $baseUrl, array $namespaces): ?array
    {
        // Link (required) - look for 'alternate' rel or first link
        $link = null;
        foreach ($entry->link as $linkEl) {
            $attrs = $linkEl->attributes();
            $rel = (string)($attrs['rel'] ?? 'alternate');
            $href = (string)($attrs['href'] ?? '');

            if ($rel === 'alternate' && !empty($href)) {
                $link = $href;
                break;
            }
            if ($link === null && !empty($href)) {
                $link = $href;
            }
        }

        if (empty($link)) {
            return null;
        }

        // Resolve relative URL
        if (!preg_match('#^https?://#i', $link)) {
            $link = UrlResolver::resolve($link, $baseUrl);
        }

        // Title (required)
        $title = $this->cleanText((string)($entry->title ?? ''));
        if (empty($title)) {
            return null;
        }

        // GUID (id)
        $guid = (string)($entry->id ?? '') ?: null;

        // Date (published or updated)
        $pubDate = null;
        $dateStr = (string)($entry->published ?? $entry->updated ?? '');
        if (!empty($dateStr)) {
            $pubDate = ThaiDateConverter::convert($dateStr);
        }

        // Summary/Description
        $description = (string)($entry->summary ?? '') ?: null;

        // Content
        $content = (string)($entry->content ?? '') ?: null;

        // Image (media:thumbnail or link with image type)
        $imageUrl = null;
        if (isset($namespaces['media'])) {
            $media = $entry->children($namespaces['media']);
            if (isset($media->thumbnail)) {
                $attrs = $media->thumbnail->attributes();
                $imageUrl = (string)($attrs['url'] ?? '');
            }
            if (empty($imageUrl) && isset($media->content)) {
                $attrs = $media->content->attributes();
                $imageUrl = (string)($attrs['url'] ?? '');
            }
        }

        // Check link with type="image/*"
        if (empty($imageUrl)) {
            foreach ($entry->link as $linkEl) {
                $attrs = $linkEl->attributes();
                $type = (string)($attrs['type'] ?? '');
                if (str_starts_with($type, 'image/')) {
                    $imageUrl = (string)($attrs['href'] ?? '');
                    break;
                }
            }
        }

        return [
            'title' => $title,
            'link' => $link,
            'guid' => $guid,
            'pub_date' => $pubDate,
            'description' => $description,
            'content' => $content,
            'image_url' => $imageUrl ?: null,
        ];
    }

    /**
     * Deduplicate items within batch by canonical URL.
     */
    private function deduplicateBatch(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $canonical = UrlCanonizer::canonize($item['link']);
            if (!isset($seen[$canonical])) {
                $seen[$canonical] = true;
                $unique[] = $item;
            }
        }

        return $unique;
    }

    /**
     * Filter items by date according to feed settings.
     */
    private function filterByDate(array $items, string $dateFilter, ?int $dateFilterHours): array
    {
        if ($dateFilter === 'none') {
            return $items;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($dateFilter === 'today') {
            $cutoff = $now->setTime(0, 0, 0);
        } elseif ($dateFilter === 'hours' && $dateFilterHours !== null) {
            $cutoff = $now->modify("-{$dateFilterHours} hours");
        } else {
            return $items;
        }

        return array_filter($items, function ($item) use ($cutoff) {
            if (empty($item['pub_date'])) {
                return true; // Include items without date
            }

            try {
                $pubDate = new \DateTimeImmutable($item['pub_date'], new \DateTimeZone('UTC'));
                return $pubDate >= $cutoff;
            } catch (\Throwable) {
                return true; // Include on parse error
            }
        });
    }

    /**
     * Clean text: trim, normalize whitespace.
     */
    private function cleanText(string $text): string
    {
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Strip HTML tags
        $text = strip_tags($text);

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Get a random User-Agent from database.
     */
    private function getRandomUserAgent(): string
    {
        $default = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        try {
            $row = Database::fetchOne(
                "SELECT user_agent FROM user_agents WHERE is_active = 1 ORDER BY RAND() LIMIT 1"
            );
            return $row['user_agent'] ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
