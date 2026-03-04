<?php declare(strict_types=1);

namespace NewsBot\Helpers;

/**
 * Language detection by Unicode blocks and stop words.
 */
class LanguageDetector
{
    // Minimum percentage of characters in a Unicode block to detect that language
    private const BLOCK_THRESHOLD = 0.15; // 15%

    // Minimum stop word matches for Latin-based language detection
    private const MIN_STOPWORD_MATCHES = 3;

    // Stop words for Latin-based languages
    private const STOPWORDS = [
        'en' => ['the', 'and', 'of', 'is', 'in', 'to', 'a', 'that', 'it', 'for', 'was', 'on', 'are', 'with', 'as', 'at', 'be', 'this', 'have', 'from'],
        'de' => ['der', 'die', 'und', 'ist', 'ein', 'in', 'das', 'den', 'von', 'zu', 'mit', 'des', 'auf', 'für', 'eine', 'nicht', 'sich', 'dem', 'es', 'werden'],
        'fr' => ['le', 'la', 'les', 'de', 'des', 'et', 'est', 'en', 'un', 'une', 'du', 'que', 'dans', 'pour', 'pas', 'qui', 'sur', 'il', 'ce', 'au'],
        'es' => ['el', 'la', 'de', 'en', 'es', 'los', 'las', 'un', 'una', 'del', 'que', 'por', 'con', 'para', 'se', 'al', 'su', 'más', 'no', 'como'],
        'it' => ['il', 'di', 'la', 'che', 'e', 'un', 'in', 'è', 'del', 'per', 'non', 'una', 'da', 'le', 'con', 'dei', 'al', 'sono', 'si', 'lo'],
        'pt' => ['de', 'a', 'o', 'que', 'e', 'do', 'da', 'em', 'um', 'para', 'é', 'com', 'não', 'uma', 'os', 'no', 'se', 'na', 'por', 'mais'],
        'nl' => ['de', 'het', 'van', 'en', 'een', 'in', 'is', 'dat', 'op', 'te', 'voor', 'met', 'niet', 'zijn', 'aan', 'er', 'die', 'wordt', 'ook', 'maar'],
    ];

    /**
     * Detect language of text.
     *
     * @param string $text Text to analyze
     * @return string ISO 639-1 language code
     */
    public static function detect(string $text): string
    {
        if (mb_strlen($text) < 10) {
            return 'en'; // Too short to detect
        }

        // Analyze Unicode blocks
        $blockCounts = self::analyzeUnicodeBlocks($text);
        $totalChars = array_sum($blockCounts);

        if ($totalChars === 0) {
            return 'en';
        }

        // Check for non-Latin scripts first (they're more distinctive)

        // Thai (U+0E00–U+0E7F)
        if (isset($blockCounts['thai']) && ($blockCounts['thai'] / $totalChars) > self::BLOCK_THRESHOLD) {
            return 'th';
        }

        // Cyrillic (U+0400–U+04FF)
        if (isset($blockCounts['cyrillic']) && ($blockCounts['cyrillic'] / $totalChars) > self::BLOCK_THRESHOLD) {
            return 'ru';
        }

        // CJK Unified Ideographs (U+4E00–U+9FFF)
        if (isset($blockCounts['cjk']) && ($blockCounts['cjk'] / $totalChars) > self::BLOCK_THRESHOLD) {
            return 'zh';
        }

        // Hangul (U+AC00–U+D7AF)
        if (isset($blockCounts['hangul']) && ($blockCounts['hangul'] / $totalChars) > self::BLOCK_THRESHOLD) {
            return 'ko';
        }

        // Hiragana/Katakana (U+3040–U+30FF)
        if (isset($blockCounts['japanese']) && ($blockCounts['japanese'] / $totalChars) > self::BLOCK_THRESHOLD) {
            return 'ja';
        }

        // Arabic (U+0600–U+06FF)
        if (isset($blockCounts['arabic']) && ($blockCounts['arabic'] / $totalChars) > self::BLOCK_THRESHOLD) {
            return 'ar';
        }

        // If mostly Latin, detect by stop words
        if (isset($blockCounts['latin']) && ($blockCounts['latin'] / $totalChars) > 0.5) {
            return self::detectByStopWords($text);
        }

        return 'en'; // Default
    }

    /**
     * Analyze text and count characters in each Unicode block.
     *
     * @return array<string, int> Block name => character count
     */
    private static function analyzeUnicodeBlocks(string $text): array
    {
        $counts = [
            'latin' => 0,
            'cyrillic' => 0,
            'thai' => 0,
            'cjk' => 0,
            'hangul' => 0,
            'japanese' => 0,
            'arabic' => 0,
            'other' => 0,
        ];

        // Convert to array of Unicode code points
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return $counts;
        }

        foreach ($chars as $char) {
            // Skip whitespace and common punctuation
            if (preg_match('/[\s\p{P}\p{N}]/u', $char)) {
                continue;
            }

            $codePoint = self::getCodePoint($char);
            if ($codePoint === null) {
                continue;
            }

            // Classify by Unicode block
            if ($codePoint >= 0x0E00 && $codePoint <= 0x0E7F) {
                $counts['thai']++;
            } elseif ($codePoint >= 0x0400 && $codePoint <= 0x04FF) {
                $counts['cyrillic']++;
            } elseif ($codePoint >= 0x4E00 && $codePoint <= 0x9FFF) {
                $counts['cjk']++;
            } elseif ($codePoint >= 0xAC00 && $codePoint <= 0xD7AF) {
                $counts['hangul']++;
            } elseif (($codePoint >= 0x3040 && $codePoint <= 0x309F) || // Hiragana
                      ($codePoint >= 0x30A0 && $codePoint <= 0x30FF)) { // Katakana
                $counts['japanese']++;
            } elseif ($codePoint >= 0x0600 && $codePoint <= 0x06FF) {
                $counts['arabic']++;
            } elseif (($codePoint >= 0x0041 && $codePoint <= 0x007A) || // Basic Latin letters
                      ($codePoint >= 0x00C0 && $codePoint <= 0x00FF) || // Latin-1 Supplement
                      ($codePoint >= 0x0100 && $codePoint <= 0x017F) || // Latin Extended-A
                      ($codePoint >= 0x0180 && $codePoint <= 0x024F)) { // Latin Extended-B
                $counts['latin']++;
            } else {
                $counts['other']++;
            }
        }

        return $counts;
    }

    /**
     * Get Unicode code point for a character.
     */
    private static function getCodePoint(string $char): ?int
    {
        $ord = mb_ord($char, 'UTF-8');
        return $ord !== false ? $ord : null;
    }

    /**
     * Detect Latin-based language by stop words.
     */
    private static function detectByStopWords(string $text): string
    {
        // Normalize text: lowercase and split by whitespace
        $text = mb_strtolower($text, 'UTF-8');
        $words = preg_split('/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return 'en';
        }

        // Create word frequency map for faster lookup
        $wordFreq = array_count_values($words);

        // Count stop word matches for each language
        $scores = [];
        foreach (self::STOPWORDS as $lang => $stopwords) {
            $score = 0;
            foreach ($stopwords as $sw) {
                if (isset($wordFreq[$sw])) {
                    $score += $wordFreq[$sw];
                }
            }
            $scores[$lang] = $score;
        }

        // Find language with highest score
        arsort($scores);
        $bestLang = array_key_first($scores);
        $bestScore = $scores[$bestLang];

        // Require minimum matches for confidence
        if ($bestScore >= self::MIN_STOPWORD_MATCHES) {
            return $bestLang;
        }

        return 'en'; // Default to English
    }

    /**
     * Check if text appears to be in a language that doesn't use spaces between words.
     * Useful for choosing n-gram strategy in MinHash.
     *
     * @return bool True if language uses no word spaces (th, zh, ja, ko)
     */
    public static function usesNoWordSpaces(string $language): bool
    {
        return in_array($language, ['th', 'zh', 'ja', 'ko'], true);
    }
}
