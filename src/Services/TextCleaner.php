<?php declare(strict_types=1);

namespace NewsBot\Services;

/**
 * Text cleaning and extraction for AI processing.
 */
class TextCleaner
{
    // Tags to preserve for structure (used in clean())
    private const PRESERVE_TAGS = '<p><br><h1><h2><h3><h4><h5><h6>';

    // Tags to remove completely (including content)
    private const REMOVE_TAGS = ['script', 'style', 'nav', 'header', 'footer', 'aside', 'noscript', 'iframe', 'form'];

    // Garbage patterns to remove
    private const GARBAGE_PATTERNS = [
        // Social sharing
        '/Share\s+(on\s+)?(Facebook|Twitter|LinkedIn|WhatsApp|Pinterest|Reddit|Email)/iu',
        '/Share\s+this\s+(article|post|story)/iu',
        '/Tweet\s+this/iu',

        // Read more/related
        '/Read\s+(more|also|next|further)/iu',
        '/See\s+also/iu',
        '/Related\s+(articles?|posts?|stories|news|content)/iu',
        '/Recommended\s+for\s+you/iu',
        '/You\s+may\s+also\s+like/iu',
        '/More\s+from\s+this\s+category/iu',

        // Ads
        '/^Advertisement$/ium',
        '/^ADVERTISEMENT$/um',
        '/^Sponsored$/ium',
        '/^Promoted$/ium',
        '/^Ad$/um',
        '/\[Ad\]/u',

        // Comments
        '/Comments?\s*\(\d+\)/iu',
        '/Leave\s+a\s+comment/iu',
        '/Post\s+a\s+comment/iu',
        '/\d+\s+comments?$/ium',

        // Social follow
        '/Follow\s+us\s+on/iu',
        '/Subscribe\s+to/iu',
        '/Sign\s+up\s+for/iu',
        '/Join\s+our\s+newsletter/iu',
        '/Get\s+our\s+newsletter/iu',

        // Photo/image credits (at start of line)
        '/^Photo:\s*.{0,50}$/ium',
        '/^Image:\s*.{0,50}$/ium',
        '/^Credit:\s*.{0,50}$/ium',
        '/^Source:\s*.{0,30}$/ium',
        '/^Via:\s*.{0,30}$/ium',
        '/^\(Photo[^)]*\)$/ium',

        // Date/time (at start of line, short)
        '/^Published:\s*\d{1,2}[\/.:-]\d{1,2}[\/.:-]\d{2,4}/ium',
        '/^Updated:\s*\d{1,2}[\/.:-]\d{1,2}[\/.:-]\d{2,4}/ium',
        '/^Posted:\s*\d{1,2}[\/.:-]\d{1,2}[\/.:-]\d{2,4}/ium',

        // Email addresses
        '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u',

        // Copyright
        '/(?:©|Copyright|\(c\))\s*\d{4}/iu',
        '/All\s+rights\s+reserved/iu',

        // Navigation remnants
        '/^(Home|News|Sports|Business|Entertainment|Technology|About|Contact)\s*[|>»]/ium',
        '/^Back\s+to\s+top$/ium',
        '/^Skip\s+to\s+content$/ium',

        // Click here / links
        '/Click\s+here\s+to/iu',
        '/Tap\s+here\s+to/iu',

        // Thai-specific patterns
        '/แชร์\s*(บน|ไป|ลง)/u',
        '/อ่านต่อ/u',
        '/อ่านเพิ่มเติม/u',
        '/ข่าวที่เกี่ยวข้อง/u',
        '/โฆษณา/u',
    ];

    /**
     * Clean HTML content for AI processing.
     *
     * @param string $html Raw HTML content
     * @return string Clean text
     */
    public static function clean(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Remove script and style tag content FIRST (before any other processing)
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $text);
        $text = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', '', $text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Strip tags except structural ones
        $text = strip_tags($text, self::PRESERVE_TAGS);

        // Convert structural tags to newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<p[^>]*>/i', '', $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
        $text = preg_replace('/<h[1-6][^>]*>/i', '', $text);

        // Remove remaining tags
        $text = strip_tags($text);

        // Apply garbage pattern removal
        foreach (self::GARBAGE_PATTERNS as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        // Split into lines and process each
        $lines = explode("\n", $text);
        $cleanLines = [];

        foreach ($lines as $line) {
            // Trim whitespace
            $line = trim($line);

            // Replace tabs with spaces
            $line = str_replace("\t", ' ', $line);

            // Normalize multiple spaces
            $line = preg_replace('/\s{2,}/', ' ', $line);

            // Skip empty lines
            if ($line === '') {
                continue;
            }

            // Skip short lines without sentence-ending punctuation
            // (likely UI fragments, captions, etc.)
            if (mb_strlen($line) < 30 && !preg_match('/[.!?。？！]/', $line)) {
                continue;
            }

            $cleanLines[] = $line;
        }

        // Join lines
        $text = implode("\n", $cleanLines);

        // Normalize multiple newlines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Final trim
        return trim($text);
    }

    /**
     * Extract text from HTML using DOMDocument.
     * Removes script, style, nav, header, footer, aside tags.
     *
     * @param string $html Raw HTML
     * @return string Extracted text
     */
    public static function extractText(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Suppress libxml errors (HTML is often malformed)
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();

        // Load HTML with UTF-8 encoding hint
        $html = '<?xml encoding="UTF-8">' . $html;
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        // Remove unwanted tags
        foreach (self::REMOVE_TAGS as $tagName) {
            $elements = $dom->getElementsByTagName($tagName);
            // Iterate backwards to avoid index issues when removing
            for ($i = $elements->length - 1; $i >= 0; $i--) {
                $element = $elements->item($i);
                if ($element && $element->parentNode) {
                    $element->parentNode->removeChild($element);
                }
            }
        }

        // Extract text content
        $text = $dom->textContent ?? '';

        // Remove the encoding declaration if it leaked through
        $text = preg_replace('/^<\?xml encoding="UTF-8"\?>/', '', $text);

        return self::clean($text);
    }

    /**
     * Validate text meets minimum quality requirements.
     *
     * @param string $text Text to validate
     * @param int $minLength Minimum character length
     * @param int $minSentences Minimum sentence count
     * @return bool True if valid
     */
    public static function isValid(string $text, int $minLength = 200, int $minSentences = 2): bool
    {
        // Check length
        if (mb_strlen($text) < $minLength) {
            return false;
        }

        // Count sentences (end with . ! ? or their unicode equivalents)
        $sentenceCount = preg_match_all('/[.!?。？！]/u', $text);

        return $sentenceCount >= $minSentences;
    }

    /**
     * Extract first N characters as summary, respecting word boundaries.
     *
     * @param string $text Text to summarize
     * @param int $maxLength Maximum length
     * @return string Summary
     */
    public static function summarize(string $text, int $maxLength = 500): string
    {
        $text = self::clean($text);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        // Cut at max length
        $summary = mb_substr($text, 0, $maxLength);

        // Find last word boundary
        $lastSpace = mb_strrpos($summary, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLength * 0.8) {
            $summary = mb_substr($summary, 0, $lastSpace);
        }

        // Add ellipsis if truncated
        return rtrim($summary, ' .,;:') . '...';
    }

    /**
     * Count words in text.
     * Handles languages without spaces (Thai, Chinese, etc.) by counting characters for them.
     *
     * @param string $text Text to count
     * @param string $language ISO 639-1 language code
     * @return int Word/token count
     */
    public static function countWords(string $text, string $language = 'en'): int
    {
        $text = trim($text);
        if (empty($text)) {
            return 0;
        }

        // For languages without word spaces, count characters as rough approximation
        // (average word ~2-3 characters)
        if (in_array($language, ['th', 'zh', 'ja', 'ko'], true)) {
            $charCount = mb_strlen(preg_replace('/\s+/u', '', $text));
            return (int)ceil($charCount / 2.5);
        }

        // For spaced languages, count words
        $words = preg_split('/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return count($words);
    }
}
