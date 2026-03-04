<?php declare(strict_types=1);

namespace NewsBot\Helpers;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Converts Buddhist Era dates to Gregorian calendar.
 * Thai calendar years are 543 years ahead of Gregorian.
 */
class ThaiDateConverter
{
    /**
     * Buddhist Era offset (Thai year = Gregorian year + 543).
     */
    private const BE_OFFSET = 543;

    /**
     * Thai month names (both full and abbreviated).
     */
    private const THAI_MONTHS = [
        'มกราคม' => 1, 'ม.ค.' => 1,
        'กุมภาพันธ์' => 2, 'ก.พ.' => 2,
        'มีนาคม' => 3, 'มี.ค.' => 3,
        'เมษายน' => 4, 'เม.ย.' => 4,
        'พฤษภาคม' => 5, 'พ.ค.' => 5,
        'มิถุนายน' => 6, 'มิ.ย.' => 6,
        'กรกฎาคม' => 7, 'ก.ค.' => 7,
        'สิงหาคม' => 8, 'ส.ค.' => 8,
        'กันยายน' => 9, 'ก.ย.' => 9,
        'ตุลาคม' => 10, 'ต.ค.' => 10,
        'พฤศจิกายน' => 11, 'พ.ย.' => 11,
        'ธันวาคม' => 12, 'ธ.ค.' => 12,
    ];

    /**
     * Convert date string to Y-m-d H:i:s (UTC).
     * Handles Buddhist Era years (2500-2600) by subtracting 543.
     *
     * Supported formats:
     * - ISO 8601: 2024-01-15T10:30:00+07:00
     * - RFC 822/2822: Mon, 15 Jan 2024 10:30:00 +0700
     * - dd/mm/yyyy: 15/01/2567 (Thai)
     * - yyyy-mm-dd: 2567-01-15 (Thai)
     * - Thai text with Thai months
     *
     * @param string $dateString Date string to convert
     * @return string|null Date in 'Y-m-d H:i:s' format (UTC) or null if parsing failed
     */
    public static function convert(string $dateString): ?string
    {
        $dateString = trim($dateString);
        if ($dateString === '') {
            return null;
        }

        // Try Thai text format first (contains Thai month names)
        $thaiParsed = self::parseThaiText($dateString);
        if ($thaiParsed !== null) {
            return $thaiParsed;
        }

        // Try dd/mm/yyyy format (common in Thai sources)
        $slashParsed = self::parseSlashFormat($dateString);
        if ($slashParsed !== null) {
            return $slashParsed;
        }

        // Try yyyy-mm-dd format with potential BE year
        $dashParsed = self::parseDashFormat($dateString);
        if ($dashParsed !== null) {
            return $dashParsed;
        }

        // Try standard formats (ISO 8601, RFC 822/2822, etc.)
        try {
            $dt = new DateTimeImmutable($dateString);

            // Check if year is Buddhist Era (2500-2600)
            $year = (int)$dt->format('Y');
            if ($year >= 2500 && $year <= 2600) {
                $dt = $dt->modify('-' . self::BE_OFFSET . ' years');
            }

            // Convert to UTC
            $dt = $dt->setTimezone(new DateTimeZone('UTC'));

            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse Thai text format with Thai month names.
     * Example: "15 มกราคม 2567" or "15 ม.ค. 2567 10:30"
     */
    private static function parseThaiText(string $dateString): ?string
    {
        foreach (self::THAI_MONTHS as $thaiMonth => $monthNum) {
            if (str_contains($dateString, $thaiMonth)) {
                // Found Thai month, extract parts
                // Pattern: optional day + month name + year + optional time
                $pattern = '/(\d{1,2})?\s*' . preg_quote($thaiMonth, '/') . '\s*(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?/u';

                if (preg_match($pattern, $dateString, $matches)) {
                    $day = !empty($matches[1]) ? (int)$matches[1] : 1;
                    $year = (int)$matches[2];
                    $hour = isset($matches[3]) ? (int)$matches[3] : 0;
                    $minute = isset($matches[4]) ? (int)$matches[4] : 0;
                    $second = isset($matches[5]) ? (int)$matches[5] : 0;

                    // Convert Buddhist Era to Gregorian
                    if ($year >= 2500 && $year <= 2600) {
                        $year -= self::BE_OFFSET;
                    }

                    // Validate date components
                    if (!checkdate($monthNum, $day, $year)) {
                        return null;
                    }

                    try {
                        // Create datetime in Bangkok timezone (Thai sources)
                        $dt = new DateTimeImmutable(
                            sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $monthNum, $day, $hour, $minute, $second),
                            new DateTimeZone('Asia/Bangkok')
                        );
                        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                    } catch (\Throwable) {
                        return null;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Parse dd/mm/yyyy format.
     * Example: "15/01/2567" or "15/01/2567 10:30:00"
     */
    private static function parseSlashFormat(string $dateString): ?string
    {
        $pattern = '/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/';

        if (!preg_match($pattern, $dateString, $matches)) {
            return null;
        }

        $day = (int)$matches[1];
        $month = (int)$matches[2];
        $year = (int)$matches[3];
        $hour = isset($matches[4]) ? (int)$matches[4] : 0;
        $minute = isset($matches[5]) ? (int)$matches[5] : 0;
        $second = isset($matches[6]) ? (int)$matches[6] : 0;

        // Convert Buddhist Era to Gregorian
        if ($year >= 2500 && $year <= 2600) {
            $year -= self::BE_OFFSET;
        }

        // Validate date
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        try {
            $dt = new DateTimeImmutable(
                sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
                new DateTimeZone('Asia/Bangkok')
            );
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse yyyy-mm-dd format with potential BE year.
     * Example: "2567-01-15" or "2567-01-15 10:30:00"
     */
    private static function parseDashFormat(string $dateString): ?string
    {
        $pattern = '/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[\sT](\d{1,2}):(\d{2})(?::(\d{2}))?)?/';

        if (!preg_match($pattern, $dateString, $matches)) {
            return null;
        }

        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
        $hour = isset($matches[4]) ? (int)$matches[4] : 0;
        $minute = isset($matches[5]) ? (int)$matches[5] : 0;
        $second = isset($matches[6]) ? (int)$matches[6] : 0;

        // Only process as Thai format if year is in BE range
        if ($year < 2500 || $year > 2600) {
            return null; // Let standard parser handle it
        }

        // Convert Buddhist Era to Gregorian
        $year -= self::BE_OFFSET;

        // Validate date
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        try {
            $dt = new DateTimeImmutable(
                sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
                new DateTimeZone('Asia/Bangkok')
            );
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
