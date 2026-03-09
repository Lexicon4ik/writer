<?php declare(strict_types=1);

namespace NewsBot\Services\Image;

use NewsBot\Core\{Logger, Settings};

/**
 * Downloads images from URLs and saves them to local storage.
 */
class ImageDownloader
{
    private const TIMEOUT        = 30;
    private const MAX_SIZE_BYTES = 20 * 1024 * 1024; // 20MB
    private const ALLOWED_MIME   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const EXT_MAP        = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    private string $basePath;

    public function __construct()
    {
        $storagePath    = Settings::get('image_storage_path', 'storage/images');
        $this->basePath = rtrim(BASE_PATH, '/') . '/' . ltrim($storagePath, '/');
    }

    /**
     * Download image from URL and save to local storage.
     *
     * @param string $category Optional category slug (e.g. 'politics', 'criminal').
     *                         If provided, file is stored under storage/images/{category}/.
     *                         If empty, falls back to storage/images/misc/.
     * @return array|null ['file_path' => relative, 'file_hash' => sha256, 'width' => int, 'height' => int, 'file_size' => int, 'mime_type' => string]
     */
    public function downloadFromUrl(string $url, string $category = ''): ?array
    {
        if (empty($url)) {
            return null;
        }

        // Download
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'NewsBot/1.0 (image-fetcher)',
        ]);

        $data     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200 || !$data) {
            Logger::debug('ImageDownloader: download failed', ['url' => substr($url, 0, 200), 'http' => $httpCode, 'err' => $curlErr]);
            return null;
        }

        return $this->saveFromBinary($data, '', $category);
    }

    /**
     * Save binary image data to local storage.
     * Returns null if invalid or already exists (returns existing path by hash).
     *
     * @param string $category Optional category slug. If provided, file is stored under
     *                         storage/images/{category}/. Falls back to storage/images/misc/.
     * @return array|null ['file_path', 'file_hash', 'width', 'height', 'file_size', 'mime_type']
     */
    public function saveFromBinary(string $data, string $mimeType = '', string $category = ''): ?array
    {
        if (empty($data)) {
            return null;
        }

        // Size check
        $size = strlen($data);
        if ($size > self::MAX_SIZE_BYTES) {
            Logger::debug('ImageDownloader: file too large', ['size' => $size]);
            return null;
        }

        // Detect MIME type from content
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($data);

        $mime = $detectedMime ?: $mimeType;
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            Logger::debug('ImageDownloader: invalid mime type', ['mime' => $mime]);
            return null;
        }

        // Get image dimensions
        $imgInfo = @getimagesizefromstring($data);
        if ($imgInfo === false) {
            Logger::debug('ImageDownloader: cannot read image dimensions');
            return null;
        }

        $width  = (int)$imgInfo[0];
        $height = (int)$imgInfo[1];
        $minWidth = (int)Settings::get('image_min_width', '600');

        if ($width < $minWidth) {
            Logger::debug('ImageDownloader: image too small', ['width' => $width, 'min' => $minWidth]);
            return null;
        }

        // SHA-256 hash for deduplication
        $hash = hash('sha256', $data);

        // Build path: storage/images/{category}/{hash}.ext
        // Category is sanitized to lowercase alphanumeric+underscore (e.g. 'politics', 'criminal').
        // Falls back to 'misc' when no category is provided.
        $ext      = self::EXT_MAP[$mime] ?? 'jpg';
        $catSlug  = $this->sanitizeCategory($category);
        $relDir   = "storage/images/{$catSlug}";
        $absDir   = $this->basePath . "/{$catSlug}";

        if (!is_dir($absDir)) {
            mkdir($absDir, 0755, true);
        }

        $relPath = "{$relDir}/{$hash}.{$ext}";
        $absPath = "{$absDir}/{$hash}.{$ext}";

        // Write file (may already exist — that's OK, dedup happens in ImageRepository)
        if (!file_exists($absPath)) {
            if (file_put_contents($absPath, $data) === false) {
                Logger::warning('ImageDownloader: cannot write file', ['path' => $absPath]);
                return null;
            }
        }

        return [
            'file_path' => $relPath,
            'file_hash' => $hash,
            'width'     => $width,
            'height'    => $height,
            'file_size' => $size,
            'mime_type' => $mime,
        ];
    }

    /**
     * Sanitize a category string to a safe folder slug.
     * Allows lowercase a-z, digits, underscore. Defaults to 'misc'.
     */
    private function sanitizeCategory(string $category): string
    {
        $slug = strtolower(trim($category));
        $slug = preg_replace('/[^a-z0-9_]/', '_', $slug);
        $slug = trim($slug, '_');
        return $slug !== '' ? $slug : 'misc';
    }

    /**
     * Return absolute filesystem path for a relative storage path.
     */
    public function getAbsolutePath(string $relPath): string
    {
        return rtrim(BASE_PATH, '/') . '/' . ltrim($relPath, '/');
    }
}
