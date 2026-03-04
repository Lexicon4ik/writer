<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Models\SourceParser;
use NewsBot\Core\{Logger, Database};
use NewsBot\Helpers\{UrlResolver, ThaiDateConverter};
use DOMDocument;
use DOMXPath;
use DOMNode;

/**
 * Web parser for sources without RSS.
 * Parses HTML pages using CSS/XPath selectors configured in source_parsers table.
 */
class WebParser
{
    private int $requestCount = 0;
    private float $lastRequestTime = 0;
    private int $pagesProcessed = 0;

    /**
     * Parse list of articles from source with pagination.
     *
     * @param SourceParser $parser Parser configuration
     * @return array Array of articles: [['url'=>, 'title'=>, 'date'=>, 'description'=>, 'image'=>], ...]
     * @throws \RuntimeException If required fields are missing
     */
    public function parseList(SourceParser $parser): array
    {
        // Validate required fields
        if (empty($parser->list_url)) {
            throw new \RuntimeException('SourceParser list_url is empty');
        }
        if (empty($parser->article_selector) || empty($parser->link_selector)) {
            throw new \RuntimeException('SourceParser article_selector or link_selector is empty');
        }

        $articles = [];
        $seenUrls = []; // Deduplication within batch

        $baseUrl = $parser->link_base_url ?: $this->getBaseUrlFromListUrl($parser->list_url);
        $currentUrl = $parser->list_url;
        $page = (int)($parser->pagination_start ?? 1);
        $maxPages = (int)($parser->max_pages ?? 3);
        $delayMs = (int)($parser->request_delay_ms ?? 2000);

        $this->pagesProcessed = 0;

        for ($i = 0; $i < $maxPages; $i++) {
            // Rate limiting between requests
            $this->rateLimit($delayMs);

            $html = $this->fetchPage($currentUrl);
            if ($html === null) {
                Logger::warning('WebParser: Failed to fetch page', [
                    'url' => $currentUrl,
                    'page' => $i + 1,
                ]);
                break;
            }

            $this->pagesProcessed++;
            $pageArticles = $this->parsePage($html, $parser, $baseUrl);

            Logger::debug('WebParser: Page parsed', [
                'url' => $currentUrl,
                'page' => $i + 1,
                'articles_found' => count($pageArticles),
            ]);

            foreach ($pageArticles as $article) {
                $url = $article['url'];

                // Deduplication within batch
                if (isset($seenUrls[$url])) {
                    continue;
                }
                $seenUrls[$url] = true;

                // Filter by exclude patterns
                if ($this->shouldExclude($url, $parser->getExcludePatterns())) {
                    continue;
                }

                // Filter by title length
                $minTitleLength = (int)($parser->min_title_length ?? 10);
                if (!empty($article['title']) && mb_strlen($article['title']) < $minTitleLength) {
                    continue;
                }

                $articles[] = $article;
            }

            // Get next page URL
            $nextUrl = $this->getNextPageUrl($currentUrl, $parser, $page, $html);
            if ($nextUrl === null) {
                break;
            }

            $currentUrl = $nextUrl;
            $page++;
        }

        Logger::info('WebParser: Parsing completed', [
            'list_url' => $parser->list_url,
            'pages_processed' => $this->pagesProcessed,
            'articles_found' => count($articles),
        ]);

        return $articles;
    }

    /**
     * Get number of pages processed in last parseList call.
     */
    public function getPagesProcessed(): int
    {
        return $this->pagesProcessed;
    }

    /**
     * Parse a single HTML page.
     *
     * @param string $html HTML content
     * @param SourceParser $parser Parser configuration
     * @param string $baseUrl Base URL for resolving relative links
     * @return array Array of articles found on page
     */
    public function parsePage(string $html, SourceParser $parser, string $baseUrl): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        $articles = [];

        // Convert CSS selector to XPath if needed
        $articleXpath = $this->toXpath($parser->article_selector);
        $articleNodes = $xpath->query($articleXpath);

        if ($articleNodes === false || $articleNodes->length === 0) {
            Logger::debug('WebParser: No article nodes found', [
                'selector' => $parser->article_selector,
                'xpath' => $articleXpath,
            ]);
            return [];
        }

        foreach ($articleNodes as $node) {
            $article = $this->parseArticleNode($node, $xpath, $parser, $baseUrl);
            if ($article !== null) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    /**
     * Extract data from a single article DOM node.
     *
     * @param DOMNode $node Article container node
     * @param DOMXPath $xpath XPath instance
     * @param SourceParser $parser Parser configuration
     * @param string $baseUrl Base URL for resolving relative links
     * @return array|null Article data or null if extraction failed
     */
    public function parseArticleNode(DOMNode $node, DOMXPath $xpath, SourceParser $parser, string $baseUrl): ?array
    {
        // URL (required)
        $linkXpath = $this->toXpath($parser->link_selector, true);
        $linkNode = $xpath->query($linkXpath, $node)->item(0);

        if (!$linkNode) {
            return null;
        }

        $url = $linkNode->getAttribute('href');
        if (empty($url)) {
            return null;
        }

        // Resolve relative URL
        $url = UrlResolver::resolve($url, $baseUrl);

        // Title (optional, with fallback to link text)
        $title = null;
        if (!empty($parser->title_selector)) {
            $titleXpath = $this->toXpath($parser->title_selector, true);
            $titleNode = $xpath->query($titleXpath, $node)->item(0);
            $title = $titleNode ? $this->cleanText($titleNode->textContent) : null;
        }
        // Fallback: link text
        if (empty($title)) {
            $title = $this->cleanText($linkNode->textContent);
        }

        // Date (optional)
        $date = null;
        if (!empty($parser->date_selector)) {
            $dateXpath = $this->toXpath($parser->date_selector, true);
            $dateNode = $xpath->query($dateXpath, $node)->item(0);
            if ($dateNode) {
                $dateStr = $this->cleanText($dateNode->textContent);
                $date = $this->parseDate($dateStr, $parser->date_format);
            }
        }

        // Description (optional)
        $description = null;
        if (!empty($parser->description_selector)) {
            $descXpath = $this->toXpath($parser->description_selector, true);
            $descNode = $xpath->query($descXpath, $node)->item(0);
            $description = $descNode ? $this->cleanText($descNode->textContent) : null;
        }

        // Image (optional)
        $image = null;
        if (!empty($parser->image_selector)) {
            $imgXpath = $this->toXpath($parser->image_selector, true);
            $imgNode = $xpath->query($imgXpath, $node)->item(0);
            if ($imgNode) {
                $image = $imgNode->getAttribute('src') ?: $imgNode->getAttribute('data-src');
                if (!empty($image)) {
                    $image = UrlResolver::resolve($image, $baseUrl);
                }
            }
        }

        return [
            'url' => $url,
            'title' => $title,
            'date' => $date,
            'description' => $description,
            'image' => $image,
        ];
    }

    /**
     * Get URL of next page based on pagination settings.
     *
     * @param string $currentUrl Current page URL
     * @param SourceParser $parser Parser configuration
     * @param int $page Current page number
     * @param string $html Current page HTML (for next_link pagination)
     * @return string|null Next page URL or null if no more pages
     */
    public function getNextPageUrl(string $currentUrl, SourceParser $parser, int $page, string $html): ?string
    {
        $paginationType = $parser->pagination_type ?? 'none';

        switch ($paginationType) {
            case 'none':
                return null;

            case 'page_param':
                $param = $parser->pagination_param ?: 'page';
                $nextPage = $page + 1;
                return $this->addQueryParam($currentUrl, $param, (string)$nextPage);

            case 'offset':
                $param = $parser->pagination_param ?: 'offset';
                $increment = (int)($parser->offset_increment ?? 20);

                // Get current offset from URL or start from 0
                $parsed = parse_url($currentUrl);
                parse_str($parsed['query'] ?? '', $query);
                $currentOffset = (int)($query[$param] ?? 0);
                $newOffset = $currentOffset + $increment;

                return $this->addQueryParam($currentUrl, $param, (string)$newOffset);

            case 'next_link':
                if (empty($parser->pagination_selector)) {
                    return null;
                }

                $dom = new DOMDocument();
                @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new DOMXPath($dom);

                $nextXpath = $this->toXpath($parser->pagination_selector);
                $nextNode = $xpath->query($nextXpath)->item(0);

                if (!$nextNode) {
                    return null;
                }

                $href = $nextNode->getAttribute('href');
                return $href ? UrlResolver::resolve($href, $currentUrl) : null;

            default:
                return null;
        }
    }

    /**
     * Fetch a page via cURL.
     *
     * @param string $url URL to fetch
     * @return string|null HTML content or null on failure
     */
    public function fetchPage(string $url): ?string
    {
        $userAgent = $this->getRandomUserAgent();

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9,th;q=0.8,ru;q=0.7',
                'Cache-Control: no-cache',
            ],
            CURLOPT_ENCODING => '', // Accept all encodings
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $this->requestCount++;

        if ($response === false || $httpCode >= 400) {
            Logger::warning('WebParser: Fetch failed', [
                'url' => $url,
                'http_code' => $httpCode,
                'error' => $error,
            ]);
            return null;
        }

        // Fix encoding if needed
        return $this->fixEncoding((string)$response);
    }

    /**
     * Rate limiting between requests.
     *
     * @param int $delayMs Minimum delay in milliseconds
     */
    public function rateLimit(int $delayMs): void
    {
        if ($this->lastRequestTime > 0) {
            $elapsed = (microtime(true) - $this->lastRequestTime) * 1000;
            if ($elapsed < $delayMs) {
                usleep((int)(($delayMs - $elapsed) * 1000));
            }
        }
        $this->lastRequestTime = microtime(true);
    }

    /**
     * Convert CSS selector to XPath.
     * Supports: .class, #id, element, element.class, element#id,
     * spaces (descendant), > (child), [attr], [attr=value].
     *
     * For complex CSS selectors, use XPath directly (starting with // or ./).
     *
     * @param string $selector CSS selector or XPath
     * @param bool $relative If true, prefix with .// for relative queries
     * @return string XPath expression
     */
    public function toXpath(string $selector, bool $relative = false): string
    {
        $selector = trim($selector);

        // Already XPath - return as is
        if (str_starts_with($selector, '//') || str_starts_with($selector, './')) {
            return $relative && str_starts_with($selector, '//')
                ? '.' . $selector
                : $selector;
        }

        $prefix = $relative ? './/' : '//';

        // Handle simple selectors first
        if (!str_contains($selector, ' ') && !str_contains($selector, '>')) {
            return $prefix . $this->convertSingleSelector($selector);
        }

        // Split by spaces and > for processing parts
        $parts = preg_split('/\s*(>|\s)\s*/', $selector, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return $prefix . '*';
        }

        $xpathParts = [];
        $isFirst = true;

        foreach ($parts as $part) {
            if ($part === '>') {
                $xpathParts[] = '/';
                continue;
            }
            if ($part === ' ' || trim($part) === '') {
                $xpathParts[] = '//';
                continue;
            }

            $converted = $this->convertSingleSelector($part);
            if ($isFirst) {
                $xpathParts[] = $converted;
                $isFirst = false;
            } else {
                $xpathParts[] = $converted;
            }
        }

        return $prefix . implode('', $xpathParts);
    }

    /**
     * Convert a single CSS selector (without spaces or >).
     *
     * @param string $selector Single CSS selector
     * @return string XPath expression fragment
     */
    private function convertSingleSelector(string $selector): string
    {
        $element = '*';
        $conditions = [];

        // Extract element name
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9]*)/', $selector, $m)) {
            $element = $m[1];
            $selector = substr($selector, strlen($m[1]));
        }

        // Extract #id
        if (preg_match('/#([a-zA-Z0-9_-]+)/', $selector, $m)) {
            $conditions[] = '@id="' . $m[1] . '"';
            $selector = str_replace($m[0], '', $selector);
        }

        // Extract .class (may have multiple)
        while (preg_match('/\.([a-zA-Z0-9_-]+)/', $selector, $m)) {
            $conditions[] = 'contains(concat(" ", normalize-space(@class), " "), " ' . $m[1] . ' ")';
            $selector = preg_replace('/\.([a-zA-Z0-9_-]+)/', '', $selector, 1);
        }

        // Extract [attr] and [attr=value]
        while (preg_match('/\[([a-zA-Z0-9_-]+)(?:=["\']?([^"\'\]]*)["\']?)?\]/', $selector, $m)) {
            if (isset($m[2]) && $m[2] !== '') {
                $conditions[] = '@' . $m[1] . '="' . $m[2] . '"';
            } else {
                $conditions[] = '@' . $m[1];
            }
            $selector = str_replace($m[0], '', $selector);
        }

        if (empty($conditions)) {
            return $element;
        }

        return $element . '[' . implode(' and ', $conditions) . ']';
    }

    /**
     * Add or replace query parameter in URL.
     *
     * @param string $url Original URL
     * @param string $param Parameter name
     * @param string $value Parameter value
     * @return string URL with updated parameter
     */
    private function addQueryParam(string $url, string $param, string $value): string
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return $url;
        }

        parse_str($parsed['query'] ?? '', $query);
        $query[$param] = $value;

        $parsed['query'] = http_build_query($query);

        return $this->buildUrl($parsed);
    }

    /**
     * Build URL from parsed components.
     *
     * @param array $parsed Parsed URL components from parse_url()
     * @return string Reconstructed URL
     */
    private function buildUrl(array $parsed): string
    {
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        return $scheme . $host . $port . $path . $query . $fragment;
    }

    /**
     * Get base URL from list URL for resolving relative links.
     *
     * @param string $listUrl List page URL
     * @return string Base URL
     */
    private function getBaseUrlFromListUrl(string $listUrl): string
    {
        $parsed = parse_url($listUrl);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            return $listUrl;
        }

        $scheme = $parsed['scheme'];
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        return $scheme . '://' . $host . $port;
    }

    /**
     * Parse date string using format or auto-detection.
     *
     * @param string $dateStr Date string
     * @param string|null $format PHP date format
     * @return string|null Parsed date in Y-m-d H:i:s format or null
     */
    private function parseDate(string $dateStr, ?string $format): ?string
    {
        // Try Thai date converter first
        $converted = ThaiDateConverter::convert($dateStr);
        if ($converted) {
            return $converted;
        }

        // If format specified, use it
        if (!empty($format)) {
            $dt = \DateTime::createFromFormat($format, $dateStr);
            if ($dt !== false) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        // Fallback: strtotime
        $ts = strtotime($dateStr);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }

    /**
     * Check if URL should be excluded based on patterns.
     *
     * @param string $url URL to check
     * @param array $patterns Array of regex patterns
     * @return bool True if URL should be excluded
     */
    private function shouldExclude(string $url, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, $url)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get random User-Agent from database.
     *
     * @return string User-Agent string
     */
    private function getRandomUserAgent(): string
    {
        try {
            $row = Database::fetchOne(
                'SELECT user_agent FROM user_agents ORDER BY RAND() LIMIT 1'
            );
            return $row['user_agent'] ?? $this->getDefaultUserAgent();
        } catch (\Throwable) {
            return $this->getDefaultUserAgent();
        }
    }

    /**
     * Get default User-Agent string.
     *
     * @return string Default User-Agent
     */
    private function getDefaultUserAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    }

    /**
     * Fix HTML encoding (convert TIS-620, Windows-874 to UTF-8).
     *
     * @param string $html HTML content
     * @return string UTF-8 encoded HTML
     */
    private function fixEncoding(string $html): string
    {
        // Detect charset from meta tag
        if (preg_match('/charset=["\']?([^"\'>\s]+)/i', $html, $m)) {
            $charset = strtoupper(trim($m[1]));

            // Thai encodings
            if (in_array($charset, ['TIS-620', 'WINDOWS-874', 'ISO-8859-11', 'TIS620'])) {
                $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
                return $converted !== false ? $converted : $html;
            }
        }

        // Check if already valid UTF-8
        if (mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }

        // Try to convert from ISO-8859-1 as fallback
        $converted = @mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
        return $converted !== false ? $converted : $html;
    }

    /**
     * Clean text content (trim whitespace, normalize spaces).
     *
     * @param string $text Raw text
     * @return string Cleaned text
     */
    private function cleanText(string $text): string
    {
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
