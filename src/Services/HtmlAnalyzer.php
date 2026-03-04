<?php declare(strict_types=1);

namespace NewsBot\Services;

use DOMDocument;
use DOMXPath;
use DOMNode;
use DOMElement;

/**
 * Analyzes HTML pages to auto-detect article list structure
 * and suggest CSS selectors for parser configuration.
 */
class HtmlAnalyzer
{
    /**
     * Analyze HTML and return suggested parser selectors.
     *
     * @param string $html HTML content of the page
     * @param string $baseUrl Base URL of the site
     * @return array Suggested selectors and config
     */
    public function analyze(string $html, string $baseUrl): array
    {
        // Detect JavaScript anti-bot protection
        if ($this->detectJsProtection($html)) {
            return [
                'success' => false,
                'message' => 'Сайт использует JavaScript-защиту от ботов. Требуется headless-браузер или ручная настройка.',
                'list_url' => $baseUrl,
                'js_protected' => true,
            ];
        }

        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);

        // Find the best article container
        $container = $this->findArticleContainer($xpath, $doc);

        if (!$container) {
            return [
                'success' => false,
                'message' => 'Не удалось обнаружить список статей на странице',
                'list_url' => $baseUrl,
            ];
        }

        $result = [
            'success' => true,
            'list_url' => $baseUrl,
            'article_selector' => $container['article_selector'],
            'link_selector' => $container['link_selector'],
            'title_selector' => null,
            'date_selector' => null,
            'image_selector' => null,
            'description_selector' => null,
            'pagination_type' => 'none',
            'pagination_selector' => null,
            'articles_found' => $container['count'],
            'sample_titles' => [],
        ];

        // Analyze the first article block for inner selectors
        $sampleNode = $container['sample_node'];
        if ($sampleNode) {
            $inner = $this->analyzeArticleBlock($xpath, $sampleNode);
            $result = array_merge($result, $inner);
        }

        // Collect sample titles from all found articles
        $result['sample_titles'] = $this->collectSampleTitles(
            $xpath,
            $container['nodes'],
            $result['link_selector'],
            $result['title_selector']
        );

        // Detect pagination
        $pagination = $this->detectPagination($xpath, $doc, $baseUrl);
        if ($pagination) {
            $result = array_merge($result, $pagination);
        }

        return $result;
    }

    /**
     * Find the container with repeating article elements.
     *
     * Strategy: find parent elements with 3+ identical child tags that contain links.
     * Score each candidate by article-likeness and pick the best one.
     */
    private function findArticleContainer(DOMXPath $xpath, DOMDocument $doc): ?array
    {
        // Exclude navigation, header, footer from search to avoid false positives
        $this->excludeNonContentAreas($xpath, $doc);

        $candidates = [];

        // Strategy 1: Look for semantic article/item containers
        // Order matters: more specific BEM patterns first, then generic patterns
        $semanticSelectors = [
            // HTML5 semantic
            '//article' => 'article',

            // BEM patterns (block-element or block__element) - high priority
            '//div[contains(@class,"news-item")]' => 'div.news-item',
            '//div[contains(@class,"news__item")]' => 'div.news__item',
            '//div[contains(@class,"article-item")]' => 'div.article-item',
            '//div[contains(@class,"article__item")]' => 'div.article__item',
            '//div[contains(@class,"post-item")]' => 'div.post-item',
            '//div[contains(@class,"post__item")]' => 'div.post__item',
            '//div[contains(@class,"feed-item")]' => 'div.feed-item',
            '//div[contains(@class,"feed__item")]' => 'div.feed__item',
            '//div[contains(@class,"list-item")]' => 'div.list-item',
            '//div[contains(@class,"list__item")]' => 'div.list__item',
            '//div[contains(@class,"grid-item")]' => 'div.grid-item',
            '//div[contains(@class,"blog-item")]' => 'div.blog-item',
            '//div[contains(@class,"archive-item")]' => 'div.archive-item',

            // Generic patterns
            '//div[contains(@class,"article")]' => 'div.article*',
            '//div[contains(@class,"news")]' => 'div.news*',
            '//div[contains(@class,"post")]' => 'div.post*',
            '//div[contains(@class,"story")]' => 'div.story*',
            '//div[contains(@class,"entry")]' => 'div.entry*',
            '//div[contains(@class,"card")]' => 'div.card*',
            '//div[contains(@class,"teaser")]' => 'div.teaser*',
            '//div[contains(@class,"excerpt")]' => 'div.excerpt*',

            // List items
            '//li[contains(@class,"article")]' => 'li.article*',
            '//li[contains(@class,"news")]' => 'li.news*',
            '//li[contains(@class,"post")]' => 'li.post*',

            // Generic item (last, lowest priority)
            '//div[contains(@class,"item")]' => 'div.item*',
        ];

        foreach ($semanticSelectors as $xpathExpr => $label) {
            $nodes = $xpath->query($xpathExpr);
            if ($nodes && $nodes->length >= 3) {
                // Verify they contain links
                $linksCount = 0;
                $sampleNode = null;
                $articleNodes = [];
                foreach ($nodes as $node) {
                    $links = $xpath->query('.//a[@href]', $node);
                    if ($links && $links->length > 0) {
                        $linksCount++;
                        if (!$sampleNode) {
                            $sampleNode = $node;
                        }
                        $articleNodes[] = $node;
                    }
                }
                if ($linksCount >= 3) {
                    $selector = $this->buildSelectorForNode($nodes->item(0));

                    // Calculate score with multiple factors
                    $score = $linksCount * 2 + ($this->isSemantic($label) ? 10 : 5);

                    // Bonus for BEM-style selectors (more specific)
                    if (str_contains($label, '-item') || str_contains($label, '__item')) {
                        $score += 8;
                    }

                    // Bonus for being inside content area
                    if ($sampleNode && $this->isInsideContentArea($sampleNode)) {
                        $score += 15;
                    }

                    // Bonus for articles containing headings (strong indicator of real articles)
                    $headingsCount = 0;
                    foreach ($articleNodes as $articleNode) {
                        $headings = $xpath->query('.//h2|.//h3|.//h4', $articleNode);
                        if ($headings && $headings->length > 0) {
                            $headingsCount++;
                        }
                    }
                    if ($headingsCount >= 3) {
                        $score += $headingsCount * 3;
                    }

                    $candidates[] = [
                        'article_selector' => $selector,
                        'link_selector' => 'a',
                        'count' => $linksCount,
                        'sample_node' => $sampleNode,
                        'nodes' => $articleNodes,
                        'score' => $score,
                    ];
                }
            }
        }

        // Strategy 2: Find parents with 3+ same-tag children containing links
        $potentialParents = $xpath->query('//div | //ul | //ol | //section | //main');
        if ($potentialParents) {
            foreach ($potentialParents as $parent) {
                $childGroups = $this->groupChildrenByTag($parent);
                foreach ($childGroups as $tag => $children) {
                    if (count($children) < 3) {
                        continue;
                    }

                    // Check if children contain links
                    $withLinks = [];
                    $sampleNode = null;
                    foreach ($children as $child) {
                        $links = $xpath->query('.//a[@href]', $child);
                        if ($links && $links->length > 0) {
                            $withLinks[] = $child;
                            if (!$sampleNode) {
                                $sampleNode = $child;
                            }
                        }
                    }

                    if (count($withLinks) >= 3 && $sampleNode) {
                        $selector = $this->buildSelectorForChildren($parent, $tag, $sampleNode);
                        if ($selector) {
                            // Calculate score with multiple factors
                            $score = count($withLinks) + $this->getDepthScore($parent);

                            // Bonus for being inside content area
                            if ($this->isInsideContentArea($sampleNode)) {
                                $score += 15;
                            }

                            // Bonus for children containing headings
                            $headingsCount = 0;
                            foreach ($withLinks as $linkNode) {
                                $headings = $xpath->query('.//h2|.//h3|.//h4', $linkNode);
                                if ($headings && $headings->length > 0) {
                                    $headingsCount++;
                                }
                            }
                            if ($headingsCount >= 3) {
                                $score += $headingsCount * 3;
                            }

                            $candidates[] = [
                                'article_selector' => $selector,
                                'link_selector' => 'a',
                                'count' => count($withLinks),
                                'sample_node' => $sampleNode,
                                'nodes' => $withLinks,
                                'score' => $score,
                            ];
                        }
                    }
                }
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort by score descending and return best candidate
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
        return $candidates[0];
    }

    /**
     * Analyze inner structure of sample article block.
     */
    private function analyzeArticleBlock(DOMXPath $xpath, DOMNode $node): array
    {
        $result = [];

        // Title selector
        $titleSelectors = [
            './/h1', './/h2', './/h3', './/h4',
            './/*[contains(@class,"title")]',
            './/*[contains(@class,"headline")]',
            './/*[contains(@class,"heading")]',
        ];
        foreach ($titleSelectors as $sel) {
            $found = $xpath->query($sel, $node);
            if ($found && $found->length > 0) {
                $el = $found->item(0);
                $text = trim($el->textContent);
                if (mb_strlen($text) >= 10 && mb_strlen($text) <= 300) {
                    $result['title_selector'] = $this->buildInnerSelector($el, $node);
                    break;
                }
            }
        }

        // Link selector — find the most prominent link
        $linkSelectors = [
            './/h1/a', './/h2/a', './/h3/a', './/h4/a',
            './/*[contains(@class,"title")]/a',
            './/*[contains(@class,"title")]//a',
            './/a[contains(@class,"title")]',
            './/a[contains(@class,"link")]',
            './/a[@href]',
        ];
        foreach ($linkSelectors as $sel) {
            $found = $xpath->query($sel, $node);
            if ($found && $found->length > 0) {
                $el = $found->item(0);
                $href = $el->getAttribute('href');
                if (!empty($href) && $href !== '#') {
                    $result['link_selector'] = $this->buildInnerSelector($el, $node);
                    break;
                }
            }
        }

        // Date selector
        $dateSelectors = [
            './/time',
            './/*[contains(@class,"date")]',
            './/*[contains(@class,"time")]',
            './/*[contains(@class,"published")]',
            './/*[contains(@class,"created")]',
            './/*[contains(@class,"meta")]',
        ];
        foreach ($dateSelectors as $sel) {
            $found = $xpath->query($sel, $node);
            if ($found && $found->length > 0) {
                $el = $found->item(0);
                $text = trim($el->textContent);
                // Check if text looks like a date (has digits)
                if (preg_match('/\d/', $text) && mb_strlen($text) <= 100) {
                    $result['date_selector'] = $this->buildInnerSelector($el, $node);
                    break;
                }
            }
        }

        // Image selector
        $imgSelectors = [
            './/img[contains(@class,"thumb")]',
            './/img[contains(@class,"preview")]',
            './/img[contains(@class,"cover")]',
            './/img[contains(@class,"photo")]',
            './/img[contains(@class,"image")]',
            './/picture//img',
            './/figure//img',
            './/img[@src]',
            './/img[@data-src]',
        ];
        foreach ($imgSelectors as $sel) {
            $found = $xpath->query($sel, $node);
            if ($found && $found->length > 0) {
                $el = $found->item(0);
                $src = $el->getAttribute('src') ?: $el->getAttribute('data-src');
                if (!empty($src) && !str_contains($src, 'data:image') && !str_contains($src, 'pixel')) {
                    $result['image_selector'] = $this->buildInnerSelector($el, $node);
                    break;
                }
            }
        }

        // Description selector
        $descSelectors = [
            './/*[contains(@class,"excerpt")]',
            './/*[contains(@class,"summary")]',
            './/*[contains(@class,"description")]',
            './/*[contains(@class,"lead")]',
            './/*[contains(@class,"teaser")]',
            './/*[contains(@class,"preview")]',
            './/p',
        ];
        foreach ($descSelectors as $sel) {
            $found = $xpath->query($sel, $node);
            if ($found && $found->length > 0) {
                $el = $found->item(0);
                $text = trim($el->textContent);
                if (mb_strlen($text) >= 20 && mb_strlen($text) <= 500) {
                    $result['description_selector'] = $this->buildInnerSelector($el, $node);
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Detect pagination on the page.
     */
    private function detectPagination(DOMXPath $xpath, DOMDocument $doc, string $baseUrl): ?array
    {
        // Look for "next" links
        $nextSelectors = [
            '//a[contains(@class,"next")]',
            '//a[contains(@rel,"next")]',
            '//li[contains(@class,"next")]/a',
            '//*[contains(@class,"pagination")]//a[contains(text(),"»")]',
            '//*[contains(@class,"pagination")]//a[contains(text(),"›")]',
            '//*[contains(@class,"pagination")]//a[last()]',
            '//a[contains(text(),"Next")]',
            '//a[contains(text(),"next")]',
            '//a[contains(text(),"ถัดไป")]',
        ];

        foreach ($nextSelectors as $sel) {
            $found = $xpath->query($sel);
            if ($found && $found->length > 0) {
                $link = $found->item(0);
                $href = $link->getAttribute('href');
                if (!empty($href) && $href !== '#') {
                    // Try to detect if it's page_param or offset
                    if (preg_match('/[?&](page|p)=(\d+)/', $href, $m)) {
                        return [
                            'pagination_type' => 'page_param',
                            'pagination_param' => $m[1],
                        ];
                    }
                    if (preg_match('/[?&](offset|start)=(\d+)/', $href, $m)) {
                        return [
                            'pagination_type' => 'offset',
                            'pagination_param' => $m[1],
                            'offset_increment' => (int)$m[2],
                        ];
                    }
                    // Generic next_link
                    $selector = $this->buildSelectorForNode($link);
                    return [
                        'pagination_type' => 'next_link',
                        'pagination_selector' => $selector,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Collect sample titles from found article nodes.
     */
    private function collectSampleTitles(DOMXPath $xpath, array $nodes, string $linkSelector, ?string $titleSelector): array
    {
        $titles = [];
        $max = min(5, count($nodes));

        for ($i = 0; $i < $max; $i++) {
            $node = $nodes[$i];
            $title = '';

            // Try title selector first
            if ($titleSelector) {
                $xpathSel = $this->cssToRelativeXpath($titleSelector);
                $found = $xpath->query($xpathSel, $node);
                if ($found && $found->length > 0) {
                    $title = trim($found->item(0)->textContent);
                }
            }

            // Fallback to link text
            if (empty($title)) {
                $xpathSel = $this->cssToRelativeXpath($linkSelector);
                $found = $xpath->query($xpathSel, $node);
                if ($found && $found->length > 0) {
                    $title = trim($found->item(0)->textContent);
                }
            }

            if (!empty($title)) {
                $titles[] = mb_substr($title, 0, 100);
            }
        }

        return $titles;
    }

    /**
     * Build a CSS selector for a node based on its tag, class, and id.
     */
    private function buildSelectorForNode(DOMElement $node): string
    {
        $tag = strtolower($node->tagName);
        $id = $node->getAttribute('id');
        $class = $node->getAttribute('class');

        if (!empty($id)) {
            return "{$tag}#{$id}";
        }

        if (!empty($class)) {
            // Use the most specific/meaningful class
            $classes = preg_split('/\s+/', trim($class));
            $best = $this->pickBestClass($classes);
            if ($best) {
                return "{$tag}.{$best}";
            }
        }

        return $tag;
    }

    /**
     * Build selector for child elements of a parent.
     */
    private function buildSelectorForChildren(DOMElement $parent, string $childTag, DOMElement $sampleChild): ?string
    {
        // Try using the child's own class
        $childClass = $sampleChild->getAttribute('class');
        if (!empty($childClass)) {
            $classes = preg_split('/\s+/', trim($childClass));
            $best = $this->pickBestClass($classes);
            if ($best) {
                return strtolower($childTag) . '.' . $best;
            }
        }

        // Use parent selector + child tag
        $parentSel = $this->buildSelectorForNode($parent);
        if ($parentSel !== strtolower($parent->tagName)) {
            return $parentSel . ' > ' . strtolower($childTag);
        }

        return null;
    }

    /**
     * Build inner selector relative to article container.
     */
    private function buildInnerSelector(DOMElement $el, DOMNode $container): string
    {
        $tag = strtolower($el->tagName);
        $class = $el->getAttribute('class');

        if (!empty($class)) {
            $classes = preg_split('/\s+/', trim($class));
            $best = $this->pickBestClass($classes);
            if ($best) {
                return "{$tag}.{$best}";
            }
        }

        // For specific tags, just use the tag
        if (in_array($tag, ['time', 'img', 'h1', 'h2', 'h3', 'h4', 'p', 'a'])) {
            // Check if parent has meaningful class
            $parent = $el->parentNode;
            if ($parent instanceof DOMElement && $parent !== $container) {
                $pClass = $parent->getAttribute('class');
                if (!empty($pClass)) {
                    $pClasses = preg_split('/\s+/', trim($pClass));
                    $pBest = $this->pickBestClass($pClasses);
                    if ($pBest) {
                        $pTag = strtolower($parent->tagName);
                        return "{$pTag}.{$pBest} {$tag}";
                    }
                }
                return strtolower($parent->tagName) . ' ' . $tag;
            }
            return $tag;
        }

        return $tag;
    }

    /**
     * Pick the most meaningful CSS class from a list.
     */
    private function pickBestClass(array $classes): ?string
    {
        // Filter out utility/layout classes
        $skipPatterns = [
            '/^(col|row|container|flex|grid|d-|p-|m-|w-|h-|text-|bg-|border|rounded|shadow)/',
            '/^(clearfix|hidden|visible|active|disabled|show|fade|collapse)$/',
            '/^\d+$/',
        ];

        $meaningful = [];
        foreach ($classes as $cls) {
            $cls = trim($cls);
            if (empty($cls)) continue;
            $skip = false;
            foreach ($skipPatterns as $pattern) {
                if (preg_match($pattern, $cls)) {
                    $skip = true;
                    break;
                }
            }
            if (!$skip) {
                $meaningful[] = $cls;
            }
        }

        if (empty($meaningful)) {
            return $classes[0] ?? null;
        }

        // Priority 1: Full BEM classes with element separator (block__element)
        // These are the most specific and reliable
        foreach ($meaningful as $cls) {
            if (preg_match('/^[a-z]+-?[a-z]*__[a-z]+/', $cls)) {
                return $cls;
            }
        }

        // Priority 2: BEM-style block-modifier classes (news-item, post-card, etc.)
        foreach ($meaningful as $cls) {
            if (preg_match('/^[a-z]+-item$|^[a-z]+-card$|^[a-z]+-entry$/', $cls)) {
                return $cls;
            }
        }

        // Priority 3: Prefer classes with semantic meaning
        $keywords = ['article', 'news', 'post', 'item', 'card', 'story', 'entry',
                     'title', 'headline', 'date', 'time', 'thumb', 'image', 'photo',
                     'excerpt', 'summary', 'description', 'lead', 'content', 'link',
                     'preview', 'teaser', 'meta', 'author', 'caption', 'dt'];

        foreach ($meaningful as $cls) {
            foreach ($keywords as $kw) {
                if (stripos($cls, $kw) !== false) {
                    return $cls;
                }
            }
        }

        return $meaningful[0];
    }

    /**
     * Group direct children by tag name.
     *
     * @return array<string, DOMElement[]>
     */
    private function groupChildrenByTag(DOMElement $parent): array
    {
        $groups = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                $groups[$tag][] = $child;
            }
        }
        return $groups;
    }

    /**
     * Check if label is semantic HTML.
     */
    private function isSemantic(string $label): bool
    {
        return str_starts_with($label, 'article');
    }

    /**
     * Get a depth score — deeper containers are typically more specific.
     */
    private function getDepthScore(DOMNode $node): int
    {
        $depth = 0;
        $current = $node;
        while ($current->parentNode) {
            $depth++;
            $current = $current->parentNode;
        }
        return min($depth, 10);
    }

    /**
     * Detect JavaScript anti-bot protection (Cloudflare, Imperva, etc.)
     */
    private function detectJsProtection(string $html): bool
    {
        // Very small page with script - likely a challenge page
        if (strlen($html) < 15000) {
            // Known bot protection markers
            $markers = [
                'window["bobcmn"]',           // Imperva/Incapsula
                '_cf_chl_opt',                // Cloudflare challenge
                'cf-browser-verification',    // Cloudflare
                'challenge-platform',         // Cloudflare
                'Just a moment...',           // Cloudflare waiting page
                'Checking your browser',      // Generic challenge
                'jschl-answer',               // Cloudflare JS challenge
                'DDoS protection by',         // Various providers
            ];

            foreach ($markers as $marker) {
                if (stripos($html, $marker) !== false) {
                    return true;
                }
            }

            // Check for minimal HTML with mostly JavaScript
            $scriptCount = substr_count(strtolower($html), '<script');
            $divCount = substr_count(strtolower($html), '<div');

            // If more scripts than divs and very few divs - likely a challenge
            if ($scriptCount > 2 && $divCount < 5) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exclude navigation, header, footer areas from DOM to prevent false positives.
     * These areas often contain many links but are not article containers.
     */
    private function excludeNonContentAreas(DOMXPath $xpath, DOMDocument $doc): void
    {
        $excludeXpaths = [
            '//header',
            '//nav',
            '//footer',
            '//aside',
            '//*[contains(@class,"header") and not(contains(@class,"header__"))]',
            '//*[contains(@class,"footer") and not(contains(@class,"footer__"))]',
            '//*[contains(@class,"nav") and not(contains(@class,"nav__"))]',
            '//*[contains(@class,"menu") and not(contains(@class,"menu__"))]',
            '//*[contains(@class,"sidebar")]',
            '//*[contains(@class,"breadcrumb")]',
        ];

        foreach ($excludeXpaths as $expr) {
            $nodes = @$xpath->query($expr);
            if ($nodes) {
                // Collect nodes first, then remove (to avoid modifying during iteration)
                $toRemove = [];
                foreach ($nodes as $node) {
                    if ($node->parentNode) {
                        $toRemove[] = $node;
                    }
                }
                foreach ($toRemove as $node) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    /**
     * Check if a node is inside a content area (not navigation/footer).
     */
    private function isInsideContentArea(DOMNode $node): bool
    {
        $current = $node;
        while ($current && $current->parentNode) {
            if ($current instanceof DOMElement) {
                $tag = strtolower($current->tagName);
                $class = strtolower($current->getAttribute('class') ?? '');

                // Inside content area indicators
                if ($tag === 'main' || $tag === 'article' || $tag === 'section') {
                    return true;
                }
                if (preg_match('/(content|page|main|body|wrapper|center)/', $class)) {
                    return true;
                }

                // Inside navigation indicators (bad)
                if (preg_match('/(header|footer|nav|menu|sidebar)/', $class)) {
                    return false;
                }
            }
            $current = $current->parentNode;
        }
        return true; // Default to allowing
    }

    /**
     * Convert simple CSS selector to relative XPath.
     */
    private function cssToRelativeXpath(string $selector): string
    {
        $selector = trim($selector);

        // Already XPath
        if (str_starts_with($selector, './/') || str_starts_with($selector, '//')) {
            return str_starts_with($selector, './/') ? $selector : '.' . $selector;
        }

        // Simple conversions
        $parts = preg_split('/\s+/', $selector);
        $xpathParts = [];

        foreach ($parts as $i => $part) {
            if ($part === '>') {
                continue;
            }

            // tag.class
            if (preg_match('/^([a-z][a-z0-9]*)?\.([a-zA-Z0-9_-]+)$/', $part, $m)) {
                $tag = $m[1] ?: '*';
                $class = $m[2];
                $xpathParts[] = "{$tag}[contains(@class,\"{$class}\")]";
            }
            // tag#id
            elseif (preg_match('/^([a-z][a-z0-9]*)?#([a-zA-Z0-9_-]+)$/', $part, $m)) {
                $tag = $m[1] ?: '*';
                $id = $m[2];
                $xpathParts[] = "{$tag}[@id=\"{$id}\"]";
            }
            // plain tag
            elseif (preg_match('/^[a-z][a-z0-9]*$/', $part)) {
                $xpathParts[] = $part;
            }
            else {
                $xpathParts[] = $part;
            }
        }

        return './/' . implode('//', $xpathParts);
    }
}
