<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\Database;

/**
 * MinHash pre-filter for obvious copy-paste detection.
 * Used as fast pass before AI deduplication.
 */
class MinHash
{
    // Number of hash functions (128 = 512 bytes signature)
    public const NUM_HASHES = 128;

    // N-gram sizes
    private const WORD_NGRAM_SIZE = 3;     // For languages with spaces
    private const CHAR_NGRAM_SIZE = 5;     // For languages without spaces

    // Languages that don't use spaces between words
    private const NO_SPACE_LANGUAGES = ['th', 'zh', 'ja', 'ko'];

    // Prime numbers for hash functions
    private const PRIME_A = 0x7FFFFFFF; // Large prime
    private const PRIME_B = 0x5BD1E995; // MurmurHash constant

    /**
     * Compute MinHash signature for text.
     *
     * @param string $text Text to fingerprint
     * @param string $language ISO 639-1 language code
     * @return string Binary signature (512 bytes for VARBINARY storage)
     */
    public static function compute(string $text, string $language = 'en'): string
    {
        // Generate shingles based on language
        $shingles = self::shingles($text, self::WORD_NGRAM_SIZE, $language);

        if (empty($shingles)) {
            // Return empty signature
            return str_repeat("\x00", self::NUM_HASHES * 4);
        }

        // Compute MinHash signature
        return self::signature($shingles);
    }

    /**
     * Generate shingles from text.
     * Automatically switches to character n-grams for languages without spaces.
     *
     * @param string $text Text to shingle
     * @param int $n N-gram size for word-based (ignored for char-based)
     * @param string $language ISO 639-1 language code
     * @return array Array of unique shingles
     */
    public static function shingles(string $text, int $n = 3, string $language = 'en'): array
    {
        // Normalize text
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        if (empty($text)) {
            return [];
        }

        // Use character n-grams for languages without word spaces
        if (in_array($language, self::NO_SPACE_LANGUAGES, true)) {
            return self::characterShingles($text, self::CHAR_NGRAM_SIZE);
        }

        return self::wordShingles($text, $n);
    }

    /**
     * Generate character n-grams.
     * Best for Thai, Chinese, Japanese, Korean.
     */
    public static function characterShingles(string $text, int $n = 5): array
    {
        // Remove spaces for character n-grams
        $text = preg_replace('/\s+/u', '', $text);
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (!$chars || count($chars) < $n) {
            return [];
        }

        $shingles = [];
        $count = count($chars) - $n + 1;

        for ($i = 0; $i < $count; $i++) {
            $shingle = implode('', array_slice($chars, $i, $n));
            $shingles[$shingle] = true;
        }

        return array_keys($shingles);
    }

    /**
     * Generate word n-grams.
     * Best for English and other space-delimited languages.
     */
    public static function wordShingles(string $text, int $n = 3): array
    {
        // Split into words, removing punctuation
        $words = preg_split('/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (!$words || count($words) < $n) {
            return [];
        }

        $shingles = [];
        $count = count($words) - $n + 1;

        for ($i = 0; $i < $count; $i++) {
            $shingle = implode(' ', array_slice($words, $i, $n));
            $shingles[$shingle] = true;
        }

        return array_keys($shingles);
    }

    /**
     * Generate MinHash signature from shingles.
     *
     * @param array $shingles Array of shingles
     * @return string Binary string (NUM_HASHES * 4 bytes)
     */
    public static function signature(array $shingles): string
    {
        // Initialize signature with max values
        $signature = array_fill(0, self::NUM_HASHES, PHP_INT_MAX);

        // Hash each shingle with different seeds and keep minimums
        foreach ($shingles as $shingle) {
            $shingleHash = self::hashString($shingle);

            for ($i = 0; $i < self::NUM_HASHES; $i++) {
                // Different hash function for each position
                $hash = self::hashCombine($shingleHash, $i);
                if ($hash < $signature[$i]) {
                    $signature[$i] = $hash;
                }
            }
        }

        // Pack into binary string (4 bytes per hash, unsigned int)
        $binary = '';
        foreach ($signature as $hash) {
            $binary .= pack('N', $hash & 0xFFFFFFFF);
        }

        return $binary;
    }

    /**
     * Calculate similarity between two signatures.
     * Returns Jaccard similarity estimate (0.0 - 1.0).
     */
    public static function similarity(string $sig1, string $sig2): float
    {
        if (strlen($sig1) !== strlen($sig2)) {
            return 0.0;
        }

        $expectedLen = self::NUM_HASHES * 4;
        if (strlen($sig1) !== $expectedLen) {
            return 0.0;
        }

        // Count matching hashes
        $matches = 0;
        for ($i = 0; $i < self::NUM_HASHES; $i++) {
            $offset = $i * 4;
            if (substr($sig1, $offset, 4) === substr($sig2, $offset, 4)) {
                $matches++;
            }
        }

        return $matches / self::NUM_HASHES;
    }

    /**
     * Find exact duplicates (similarity > threshold) in database.
     *
     * @param string $signature Signature to compare
     * @param int $excludeArticleId Article ID to exclude from results
     * @param float $threshold Minimum similarity (default 0.8)
     * @param int $hours Look back hours (default 72)
     * @return array|null {article_id: int, similarity: float} or null if no match
     */
    public static function findExactDuplicates(
        string $signature,
        int $excludeArticleId,
        float $threshold = 0.8,
        int $hours = 72
    ): ?array {
        // Get recent fingerprints
        $rows = Database::fetchAll(
            "SELECT af.article_id, af.signature
             FROM article_fingerprints af
             JOIN articles a ON a.id = af.article_id
             WHERE af.article_id != ?
               AND a.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
               AND a.status NOT IN ('scrape_failed', 'expired', 'cancelled')
             ORDER BY a.created_at DESC
             LIMIT 500",
            [$excludeArticleId, $hours]
        );

        $bestMatch = null;
        $bestSimilarity = 0.0;

        foreach ($rows as $row) {
            $sim = self::similarity($signature, $row['signature']);

            if ($sim >= $threshold && $sim > $bestSimilarity) {
                $bestSimilarity = $sim;
                $bestMatch = [
                    'article_id' => (int)$row['article_id'],
                    'similarity' => $sim,
                ];
            }
        }

        return $bestMatch;
    }

    /**
     * Save fingerprint to database.
     */
    public static function saveFingerprint(int $articleId, string $signature): void
    {
        Database::execute(
            "INSERT INTO article_fingerprints (article_id, signature)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE signature = VALUES(signature)",
            [$articleId, $signature]
        );
    }

    /**
     * Hash a string using a simple hash function.
     */
    private static function hashString(string $str): int
    {
        // Use crc32 for speed, combined with length for uniqueness
        $hash = crc32($str);
        $hash ^= strlen($str) * self::PRIME_B;
        return $hash & PHP_INT_MAX; // Keep positive
    }

    /**
     * Combine hash with seed for different hash functions.
     */
    private static function hashCombine(int $hash, int $seed): int
    {
        // Linear congruential generator-style combination
        $combined = ($hash * self::PRIME_A + $seed * self::PRIME_B) & PHP_INT_MAX;
        // Additional mixing
        $combined ^= ($combined >> 16);
        $combined = ($combined * self::PRIME_B) & PHP_INT_MAX;
        $combined ^= ($combined >> 13);

        return $combined;
    }
}
