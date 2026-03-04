<?php declare(strict_types=1);

namespace NewsBot\Helpers;

/**
 * Telegram HTML formatting helper.
 * Escapes text for Telegram HTML parse_mode while preserving allowed tags.
 */
class TelegramFormatter
{
    /**
     * Allowed tags in Telegram HTML mode.
     */
    private const ALLOWED_TAGS = ['b', 'strong', 'i', 'em', 'u', 'ins', 's', 'strike', 'del', 'code', 'pre', 'a'];

    /**
     * Regex pattern to match allowed tags (both opening and closing).
     * Captures: <b>, </b>, <a href="...">, </a>, <code>, </code>, etc.
     */
    private const TAG_PATTERN = '/<\/?(?:b|strong|i|em|u|ins|s|strike|del|code|pre)(?:\s[^>]*)?>|<a\s[^>]*>|<\/a>/i';

    /**
     * Escape text for Telegram HTML parse_mode.
     *
     * Algorithm:
     * 1. Split text into segments: [text, tag, text, tag, ...]
     * 2. Escape only text segments (not tags): & → &amp; < → &lt; > → &gt;
     * 3. Reassemble.
     *
     * This approach correctly handles nested tags like <b>text <i>italic</i></b>.
     *
     * @param string $text Text possibly containing allowed HTML tags
     * @return string Escaped text safe for Telegram HTML
     */
    public static function escape(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Split by allowed tags, keeping delimiters (tags) in result
        $segments = preg_split(self::TAG_PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($segments === false) {
            // Fallback: escape everything if regex fails
            return self::escapeText($text);
        }

        // Find all tags to interleave with text segments
        preg_match_all(self::TAG_PATTERN, $text, $tagMatches);
        $tags = $tagMatches[0] ?? [];

        $result = '';
        $tagIndex = 0;

        foreach ($segments as $i => $segment) {
            // Even indices are text, odd indices from split don't exist here
            // Actually preg_split with DELIM_CAPTURE puts delimiters in order
            // We need different approach: match tags and split around them

            $result .= self::escapeText($segment);

            // Add the tag after this segment (if exists)
            if (isset($tags[$tagIndex])) {
                $result .= $tags[$tagIndex];
                $tagIndex++;
            }
        }

        return $result;
    }

    /**
     * Escape plain text (no tags).
     */
    private static function escapeText(string $text): string
    {
        // Order matters: & first, then < and >
        return str_replace(
            ['&', '<', '>'],
            ['&amp;', '&lt;', '&gt;'],
            $text
        );
    }

    /**
     * Remove all HTML tags except allowed Telegram tags.
     *
     * @param string $html HTML content
     * @return string Cleaned HTML with only allowed tags
     */
    public static function stripUnsupportedTags(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Build allowed tags string for strip_tags
        $allowedTagsString = '<' . implode('><', self::ALLOWED_TAGS) . '>';

        return strip_tags($html, $allowedTagsString);
    }

    /**
     * Safely truncate HTML without breaking tags.
     * Closes any open tags at the truncation point.
     *
     * @param string $html HTML content
     * @param int $maxLength Maximum length in characters (excluding tags)
     * @return string Truncated HTML with proper closing tags
     */
    public static function truncateSafe(string $html, int $maxLength): string
    {
        if (empty($html)) {
            return '';
        }

        // Calculate visible text length (without tags)
        $visibleLength = mb_strlen(strip_tags($html));

        if ($visibleLength <= $maxLength) {
            return $html;
        }

        $result = '';
        $openTags = [];
        $visibleCount = 0;
        $pos = 0;
        $length = strlen($html);

        while ($pos < $length && $visibleCount < $maxLength) {
            if ($html[$pos] === '<') {
                // Find end of tag
                $tagEnd = strpos($html, '>', $pos);
                if ($tagEnd === false) {
                    break;
                }

                $tag = substr($html, $pos, $tagEnd - $pos + 1);
                $result .= $tag;

                // Track open/close tags
                if (preg_match('/<(\w+)(?:\s|>)/i', $tag, $m)) {
                    // Opening tag
                    $tagName = strtolower($m[1]);
                    if (in_array($tagName, self::ALLOWED_TAGS) && !self::isSelfClosing($tag)) {
                        $openTags[] = $tagName;
                    }
                } elseif (preg_match('/<\/(\w+)>/i', $tag, $m)) {
                    // Closing tag
                    $tagName = strtolower($m[1]);
                    $key = array_search($tagName, array_reverse($openTags, true));
                    if ($key !== false) {
                        unset($openTags[$key]);
                        $openTags = array_values($openTags);
                    }
                }

                $pos = $tagEnd + 1;
            } else {
                // Regular character
                $char = mb_substr(substr($html, $pos), 0, 1);
                $charLen = strlen($char);

                // Handle HTML entities
                if ($char === '&') {
                    $entityEnd = strpos($html, ';', $pos);
                    if ($entityEnd !== false && $entityEnd - $pos < 10) {
                        $entity = substr($html, $pos, $entityEnd - $pos + 1);
                        $result .= $entity;
                        $pos = $entityEnd + 1;
                        $visibleCount++;
                        continue;
                    }
                }

                $result .= $char;
                $pos += $charLen;
                $visibleCount++;
            }
        }

        // Close any open tags in reverse order
        foreach (array_reverse($openTags) as $tag) {
            $result .= "</{$tag}>";
        }

        return $result;
    }

    /**
     * Check if tag is self-closing.
     */
    private static function isSelfClosing(string $tag): bool
    {
        return (bool)preg_match('/\/>$/', $tag) || preg_match('/<(br|hr|img|input|meta|link)[\s>]/i', $tag);
    }

    /**
     * Format text for Telegram: strip unsupported tags and escape.
     * Combined convenience method.
     *
     * @param string $html HTML content
     * @return string Text safe for Telegram HTML mode
     */
    public static function format(string $html): string
    {
        $stripped = self::stripUnsupportedTags($html);
        return self::escape($stripped);
    }

    /**
     * Normalize whitespace in text.
     * Collapses multiple spaces/newlines into single ones.
     */
    public static function normalizeWhitespace(string $text): string
    {
        // Replace multiple newlines with double
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        // Replace multiple spaces with single
        $text = preg_replace('/[ \t]+/', ' ', $text);
        // Trim lines
        $text = implode("\n", array_map('trim', explode("\n", $text)));

        return trim($text);
    }
}
