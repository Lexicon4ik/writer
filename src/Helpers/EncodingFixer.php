<?php declare(strict_types=1);

namespace NewsBot\Helpers;

/**
 * Encoding detection and UTF-8 conversion.
 */
class EncodingFixer
{
    private const UTF8_BOM = "\xEF\xBB\xBF";
    private const UTF16_BE_BOM = "\xFE\xFF";
    private const UTF16_LE_BOM = "\xFF\xFE";

    private const CHARSET_ALIASES = [
        'tis-620' => 'TIS-620',
        'tis620' => 'TIS-620',
        'windows-874' => 'Windows-874',
        'cp874' => 'Windows-874',
        'iso-8859-1' => 'ISO-8859-1',
        'latin1' => 'ISO-8859-1',
        'windows-1251' => 'Windows-1251',
        'cp1251' => 'Windows-1251',
        'windows-1252' => 'Windows-1252',
        'cp1252' => 'Windows-1252',
        'utf-8' => 'UTF-8',
        'utf8' => 'UTF-8',
    ];

    public static function toUtf8(string $content, ?string $declaredCharset = null): string
    {
        if ($content === '') {
            return '';
        }

        $bomEncoding = null;
        $content = self::removeBom($content, $bomEncoding);
        if ($bomEncoding !== null) {
            return self::convert($content, $bomEncoding);
        }

        $encoding = null;

        if ($declaredCharset !== null) {
            $encoding = self::normalizeCharset($declaredCharset);
        }

        if ($encoding === null) {
            $encoding = self::detectMetaCharset($content);
        }

        if ($encoding === null) {
            $encoding = self::detectEncoding($content);
        }

        if ($encoding === 'UTF-8' && self::isValidUtf8($content)) {
            return $content;
        }

        return self::convert($content, $encoding ?? 'UTF-8');
    }

    private static function removeBom(string $content, ?string &$encoding): string
    {
        $encoding = null;

        if (str_starts_with($content, self::UTF8_BOM)) {
            $encoding = 'UTF-8';
            return substr($content, 3);
        }

        if (str_starts_with($content, self::UTF16_BE_BOM)) {
            $encoding = 'UTF-16BE';
            return substr($content, 2);
        }

        if (str_starts_with($content, self::UTF16_LE_BOM)) {
            $encoding = 'UTF-16LE';
            return substr($content, 2);
        }

        return $content;
    }

    private static function normalizeCharset(string $charset): ?string
    {
        $charset = strtolower(trim($charset));
        return self::CHARSET_ALIASES[$charset] ?? null;
    }

    private static function detectMetaCharset(string $content): ?string
    {
        $head = substr($content, 0, 2048);

        if (preg_match('/<meta\s+charset=["\x27]?([^"\x27>\s]+)/i', $head, $matches)) {
            return self::normalizeCharset($matches[1]);
        }

        if (preg_match('/<meta[^>]+content=["\x27][^"\x27]*charset=([^"\x27;\s]+)/i', $head, $matches)) {
            return self::normalizeCharset($matches[1]);
        }

        if (preg_match('/<\?xml[^>]+encoding=["\x27]([^"\x27]+)/i', $head, $matches)) {
            return self::normalizeCharset($matches[1]);
        }

        return null;
    }

    private static function detectEncoding(string $content): string
    {
        if (self::isValidUtf8($content) && self::hasMultibyteUtf8($content)) {
            return 'UTF-8';
        }

        if (self::looksLikeThai($content)) {
            return 'TIS-620';
        }

        if (self::looksLikeCyrillic($content)) {
            return 'Windows-1251';
        }

        // TIS-620 not supported by mb_detect_encoding, use available encodings
        $detected = mb_detect_encoding(
            $content,
            ['UTF-8', 'Windows-1252', 'Windows-1251', 'ISO-8859-1'],
            true
        );

        return $detected ?: 'UTF-8';
    }

    private static function looksLikeThai(string $content): bool
    {
        $sample = substr($content, 0, 1024);
        $thaiCharCount = 0;
        $totalHighBytes = 0;

        for ($i = 0; $i < strlen($sample); $i++) {
            $byte = ord($sample[$i]);
            if ($byte >= 0x80) {
                $totalHighBytes++;
                if ($byte >= 0xA1 && $byte <= 0xFB) {
                    $thaiCharCount++;
                }
            }
        }

        return $totalHighBytes > 10 && ($thaiCharCount / $totalHighBytes) > 0.5;
    }

    private static function looksLikeCyrillic(string $content): bool
    {
        $sample = substr($content, 0, 1024);
        $cyrillicCount = 0;
        $totalHighBytes = 0;

        for ($i = 0; $i < strlen($sample); $i++) {
            $byte = ord($sample[$i]);
            if ($byte >= 0x80) {
                $totalHighBytes++;
                if ($byte >= 0xC0 && $byte <= 0xFF) {
                    $cyrillicCount++;
                }
            }
        }

        return $totalHighBytes > 10 && ($cyrillicCount / $totalHighBytes) > 0.5;
    }

    private static function isValidUtf8(string $content): bool
    {
        return mb_check_encoding($content, 'UTF-8');
    }

    private static function hasMultibyteUtf8(string $content): bool
    {
        return strlen($content) !== mb_strlen($content, 'UTF-8');
    }

    private static function convert(string $content, string $fromEncoding): string
    {
        if ($fromEncoding === 'UTF-8') {
            return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        $iconvEncoding = match ($fromEncoding) {
            'TIS-620' => 'TIS-620',
            'Windows-874' => 'CP874',
            default => $fromEncoding,
        };

        $result = @iconv($iconvEncoding, 'UTF-8//IGNORE', $content);
        if ($result !== false) {
            return $result;
        }

        $result = @mb_convert_encoding($content, 'UTF-8', $fromEncoding);
        if ($result !== false) {
            return $result;
        }

        return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
    }

    public static function extractCharsetFromContentType(string $contentType): ?string
    {
        if (preg_match('/charset\s*=\s*["\x27]?([^"\x27;\s]+)/i', $contentType, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
