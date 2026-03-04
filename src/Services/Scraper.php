<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\CircuitBreaker;
use NewsBot\Core\Database;
use NewsBot\Core\Logger;
use NewsBot\Helpers\EncodingFixer;
use NewsBot\Helpers\LanguageDetector;
use NewsBot\Models\Article;
use NewsBot\Models\ScrapeRule;
use NewsBot\Models\Source;

/**
 * Web scraper with fallback to RSS content.
 */
class Scraper
{
    // HTTP timeout settings
    private const CONNECT_TIMEOUT = 10;
    private const TOTAL_TIMEOUT = 30;
    private const MAX_REDIRECTS = 5;
    private const MAX_HTTP_RETRIES = 2;

    // Hard limits
    private const MAX_CHARS = 500000;
    private const MAX_BYTES = 15 * 1024 * 1024; // 15MB

    // Heuristic content selectors (tried in order)
    private const HEURISTIC_SELECTORS = [
        '//article',
        '//*[@role="main"]',
        '//*[contains(@class, "post-content")]',
        '//*[contains(@class, "article-body")]',
        '//*[contains(@class, "article-content")]',
        '//*[contains(@class, "entry-content")]',
        '//*[contains(@class, "story-body")]',
        '//*[contains(@class, "content-body")]',
        '//main',
        '//*[contains(@class, "main-content")]',
    ];

    private ?string $lastEffectiveUrl = null;

    /**
     * Scrape full text from article URL.
     *
     * @param Article $article Article to scrape
     * @param Source $source Source configuration
     * @return array {
     *   title: ?string,
     *   text: ?string,
     *   image_url: ?string,
     *   language: string,
     *   success: bool,
     *   method: 'web'|'rss_fallback'|'rss_only',
     *   effective_url: ?string
     * }
     */
    public function scrape(Article $article, Source $source): array
    {
        $this->lastEffectiveUrl = null;

        // Check scrape strategy
        $strategy = $source->scrape_strategy ?? 'web';

        if ($strategy === 'rss_only') {
            return $this->useRssFallback($article, 'rss_only');
        }

        if ($strategy === 'api') {
            throw new \RuntimeException('API scrape strategy not implemented');
        }

        // web or custom_parser → proceed with web scraping
        return $this->scrapeWeb($article, $source);
    }

    /**
     * Perform web scraping.
     */
    private function scrapeWeb(Article $article, Source $source): array
    {
        $url = $article->url;

        // Rate limiting
        $this->checkRateLimit($source);

        // Fetch HTML
        $html = $this->fetchHtml($url, $source);
        if ($html === null) {
            return $this->useRssFallback($article, 'rss_fallback');
        }

        // Get scrape rules
        $rules = ScrapeRule::getForSource((int)$source->id);

        // Extract content
        $text = null;
        $title = null;
        $imageUrl = null;

        if (!empty($rules)) {
            foreach ($rules as $rule) {
                $extracted = $this->extractByRules($html, $rule);
                if ($extracted !== null) {
                    $text = $extracted['text'];
                    $title = $extracted['title'] ?? null;
                    break;
                }
            }
        }

        // Heuristic fallback if no rules or rules failed
        if ($text === null) {
            $text = $this->extractByHeuristic($html);
        }

        // Extract image
        $imageUrl = $this->extractImage($html);

        // Clean and validate text
        if ($text !== null) {
            $text = TextCleaner::clean($text);

            // Apply hard limits
            $text = $this->applyHardLimits($text, $article->id);

            if (!TextCleaner::isValid($text)) {
                $text = null;
            }
        }

        // If extraction failed, use RSS fallback
        if ($text === null || mb_strlen($text) < 200) {
            return $this->useRssFallback($article, 'rss_fallback');
        }

        // Detect language
        $language = LanguageDetector::detect($text);

        // Record success
        CircuitBreaker::recordSuccess('scraper');

        return [
            'title' => $title,
            'text' => $text,
            'image_url' => $imageUrl ?? $article->rss_image_url,
            'language' => $language,
            'success' => true,
            'method' => 'web',
            'effective_url' => $this->lastEffectiveUrl,
        ];
    }

    /**
     * Fetch HTML content via cURL.
     *
     * @return string|null HTML content or null on failure
     */
    private function fetchHtml(string $url, Source $source): ?string
    {
        $userAgent = $this->getRandomUserAgent();

        for ($attempt = 1; $attempt <= self::MAX_HTTP_RETRIES + 1; $attempt++) {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT,
                CURLOPT_USERAGENT => $userAgent,
                CURLOPT_ENCODING => 'gzip, deflate',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9,th;q=0.8',
                    'Referer: ' . ($source->site_url ?? 'https://www.google.com'),
                    'Cache-Control: no-cache',
                ],
            ]);

            // Proxy if configured
            $proxyUrl = $source->proxy_url ?? null;
            if ($proxyUrl) {
                curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);
            }

            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);

            curl_close($ch);

            $this->lastEffectiveUrl = $effectiveUrl;

            // Success
            if ($httpCode === 200 && $html !== false && $html !== '') {
                // Handle encoding
                $charset = EncodingFixer::extractCharsetFromContentType($contentType ?? '');
                $html = EncodingFixer::toUtf8($html, $charset);

                // Check size limits
                if (strlen($html) > self::MAX_BYTES) {
                    Logger::warning('HTML too large, truncating', [
                        'url' => $url,
                        'size_bytes' => strlen($html),
                    ]);
                    $html = mb_strcut($html, 0, self::MAX_BYTES);
                }

                return $html;
            }

            // Handle different HTTP codes
            if (in_array($httpCode, [403, 451, 503], true)) {
                // Cloudflare/blocking - no point in retrying
                Logger::info('Blocked by server', [
                    'url' => $url,
                    'http_code' => $httpCode,
                ]);
                CircuitBreaker::recordFailure('scraper');
                return null;
            }

            if ($httpCode >= 500) {
                // Server error - retry
                if ($attempt <= self::MAX_HTTP_RETRIES) {
                    Logger::debug('HTTP 5xx, retrying', [
                        'url' => $url,
                        'http_code' => $httpCode,
                        'attempt' => $attempt,
                    ]);
                    sleep(3);
                    continue;
                }

                Logger::warning('HTTP 5xx after retries', [
                    'url' => $url,
                    'http_code' => $httpCode,
                ]);
                CircuitBreaker::recordFailure('scraper');
                return null;
            }

            // Other error
            if ($html === false || $httpCode !== 200) {
                Logger::warning('Fetch failed', [
                    'url' => $url,
                    'http_code' => $httpCode,
                    'error' => $error,
                ]);
                CircuitBreaker::recordFailure('scraper');
                return null;
            }
        }

        return null;
    }

    /**
     * Extract content using scrape rules.
     *
     * @return array|null {text: string, title: ?string}
     */
    private function extractByRules(string $html, ScrapeRule $rule): ?array
    {
        $dom = $this->loadDom($html);
        if ($dom === null) {
            return null;
        }

        $xpath = new \DOMXPath($dom);

        // Remove elements specified in remove_selectors first
        $removeSelectors = $rule->getRemoveSelectors();
        foreach ($removeSelectors as $removeSelector) {
            $removeNodes = @$xpath->query($removeSelector);
            if ($removeNodes) {
                foreach ($removeNodes as $node) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }
        }

        // Extract main content
        $contentSelector = $rule->content_selector;
        $contentNodes = @$xpath->query($contentSelector);

        if (!$contentNodes || $contentNodes->length === 0) {
            return null;
        }

        $text = '';
        foreach ($contentNodes as $node) {
            $text .= $this->getNodeText($node) . "\n\n";
        }

        if (empty(trim($text))) {
            return null;
        }

        // Extract title if selector provided
        $title = null;
        if ($rule->title_selector) {
            $titleNodes = @$xpath->query($rule->title_selector);
            if ($titleNodes && $titleNodes->length > 0) {
                $title = trim($titleNodes->item(0)->textContent ?? '');
            }
        }

        return [
            'text' => $text,
            'title' => $title ?: null,
        ];
    }

    /**
     * Extract content using heuristics (when no rules available).
     */
    private function extractByHeuristic(string $html): ?string
    {
        $dom = $this->loadDom($html);
        if ($dom === null) {
            return null;
        }

        $xpath = new \DOMXPath($dom);

        // Remove common junk elements first
        $junkSelectors = [
            '//script',
            '//style',
            '//nav',
            '//header',
            '//footer',
            '//aside',
            '//form',
            '//*[contains(@class, "comment")]',
            '//*[contains(@class, "sidebar")]',
            '//*[contains(@class, "advertisement")]',
            '//*[contains(@class, "social")]',
            '//*[contains(@class, "share")]',
            '//*[contains(@class, "related")]',
            '//*[contains(@class, "recommended")]',
            '//*[contains(@id, "comment")]',
            '//*[contains(@id, "sidebar")]',
        ];

        foreach ($junkSelectors as $selector) {
            $junkNodes = @$xpath->query($selector);
            if ($junkNodes) {
                foreach ($junkNodes as $node) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }
        }

        // Try heuristic selectors in order
        foreach (self::HEURISTIC_SELECTORS as $selector) {
            $nodes = @$xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                $text = '';
                foreach ($nodes as $node) {
                    $text .= $this->getNodeText($node) . "\n\n";
                }

                $text = trim($text);
                if (mb_strlen($text) >= 200) {
                    return $text;
                }
            }
        }

        // Last resort: use body
        $bodyNodes = @$xpath->query('//body');
        if ($bodyNodes && $bodyNodes->length > 0) {
            return $this->getNodeText($bodyNodes->item(0));
        }

        return null;
    }

    /**
     * Extract og:image or first large image from HTML.
     */
    private function extractImage(string $html): ?string
    {
        $dom = $this->loadDom($html);
        if ($dom === null) {
            return null;
        }

        $xpath = new \DOMXPath($dom);

        // Try og:image first
        $ogImage = @$xpath->query('//meta[@property="og:image"]/@content');
        if ($ogImage && $ogImage->length > 0) {
            $url = trim($ogImage->item(0)->nodeValue ?? '');
            if (!empty($url)) {
                return $url;
            }
        }

        // Try twitter:image
        $twitterImage = @$xpath->query('//meta[@name="twitter:image"]/@content');
        if ($twitterImage && $twitterImage->length > 0) {
            $url = trim($twitterImage->item(0)->nodeValue ?? '');
            if (!empty($url)) {
                return $url;
            }
        }

        // Look for large images in article
        $images = @$xpath->query('//article//img[@src] | //main//img[@src] | //*[contains(@class, "content")]//img[@src]');
        if ($images) {
            foreach ($images as $img) {
                $src = $img->getAttribute('src');
                $width = $img->getAttribute('width');
                $height = $img->getAttribute('height');

                // Skip small images
                if ($width && (int)$width < 200) {
                    continue;
                }
                if ($height && (int)$height < 150) {
                    continue;
                }

                // Skip common icons/placeholders
                if (preg_match('/(icon|logo|avatar|placeholder|loading|spinner|pixel)/i', $src)) {
                    continue;
                }

                return $src;
            }
        }

        return null;
    }

    /**
     * Use RSS content as fallback.
     */
    private function useRssFallback(Article $article, string $method): array
    {
        // Try rss_content first (content:encoded), then rss_description
        $text = $article->rss_content ?? $article->rss_description ?? null;

        if ($text !== null) {
            $text = TextCleaner::clean($text);
            $text = $this->applyHardLimits($text, $article->id);
        }

        $success = $text !== null && TextCleaner::isValid($text, 25, 0);
        $language = $text ? LanguageDetector::detect($text) : 'en';

        return [
            'title' => null, // Use RSS title
            'text' => $success ? $text : null,
            'image_url' => $article->rss_image_url,
            'language' => $language,
            'success' => $success,
            'method' => $method,
            'effective_url' => null,
        ];
    }

    /**
     * Check and enforce rate limit for source domain.
     */
    private function checkRateLimit(Source $source): void
    {
        $url = $source->site_url ?? '';
        $domain = parse_url($url, PHP_URL_HOST) ?? 'unknown';
        $delayMs = $source->getRequestDelayMs();

        // Get last request time
        $row = Database::fetchOne(
            "SELECT last_request_at FROM domain_rate_limits WHERE domain = ?",
            [$domain]
        );

        if ($row) {
            $lastRequest = strtotime($row['last_request_at']);
            $elapsed = (microtime(true) - $lastRequest) * 1000;

            if ($elapsed < $delayMs) {
                $waitMs = $delayMs - $elapsed;
                usleep((int)($waitMs * 1000));
            }
        }

        // Update last request time
        Database::execute(
            "INSERT INTO domain_rate_limits (domain, last_request_at) VALUES (?, NOW(3))
             ON DUPLICATE KEY UPDATE last_request_at = NOW(3)",
            [$domain]
        );
    }

    /**
     * Get random user agent from database.
     */
    private function getRandomUserAgent(): string
    {
        $row = Database::fetchOne(
            "SELECT user_agent FROM user_agents WHERE is_active = 1 ORDER BY RAND() LIMIT 1"
        );

        if ($row && !empty($row['user_agent'])) {
            return $row['user_agent'];
        }

        // Fallback
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    }

    /**
     * Load HTML into DOMDocument.
     */
    private function loadDom(string $html): ?\DOMDocument
    {
        if (empty($html)) {
            return null;
        }

        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;

        // Add UTF-8 encoding hint
        $html = '<?xml encoding="UTF-8">' . $html;

        $success = @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $success ? $dom : null;
    }

    /**
     * Get text content from DOM node, preserving paragraph structure.
     */
    private function getNodeText(\DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->nodeValue;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->nodeName);

                // Skip junk tags
                if (in_array($tagName, ['script', 'style', 'nav', 'noscript', 'iframe'], true)) {
                    continue;
                }

                $childText = $this->getNodeText($child);

                // Add newlines for block elements
                if (in_array($tagName, ['p', 'div', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'tr'], true)) {
                    $text .= "\n" . $childText . "\n";
                } else {
                    $text .= $childText;
                }
            }
        }

        return $text;
    }

    /**
     * Apply hard limits to text (500K chars, 15MB bytes).
     */
    private function applyHardLimits(string $text, $articleId): string
    {
        // Check byte limit first (15MB)
        if (strlen($text) > self::MAX_BYTES) {
            Logger::warning('Text exceeds byte limit, truncating', [
                'article_id' => $articleId,
                'original_bytes' => strlen($text),
            ]);
            $text = mb_strcut($text, 0, self::MAX_BYTES);
        }

        // Check character limit (500K chars)
        if (mb_strlen($text) > self::MAX_CHARS) {
            Logger::warning('Text exceeds char limit, truncating', [
                'article_id' => $articleId,
                'original_chars' => mb_strlen($text),
            ]);
            $text = mb_substr($text, 0, self::MAX_CHARS);
        }

        return $text;
    }

    /**
     * Get the last effective URL after redirects.
     */
    public function getLastEffectiveUrl(): ?string
    {
        return $this->lastEffectiveUrl;
    }
}
