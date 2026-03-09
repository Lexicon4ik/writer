<?php declare(strict_types=1);

namespace NewsBot\Services\Image;

use NewsBot\Core\{Logger, Settings};
use NewsBot\Models\{Article, ArticleVersion, Channel};

/**
 * Orchestrates image selection for an article version.
 * Strategy depends on channel->image_mode:
 *   source    → download original image from RSS/scraping
 *   enhanced  → source → Pexels → AI generation
 *   generated → Pexels → AI generation (skip original)
 *   disabled  → no image
 */
class ImageSelector
{
    private ImageDownloader   $downloader;
    private ImageRepository   $repository;
    private PexelsClient      $pexels;
    private GoogleImageClient $googleImage;

    public function __construct()
    {
        $this->downloader  = new ImageDownloader();
        $this->repository  = new ImageRepository();
        $this->pexels      = new PexelsClient();
        $this->googleImage = new GoogleImageClient();
    }

    /**
     * Select and attach an image to the given article version.
     * Returns true if an image was successfully attached.
     */
    public function select(ArticleVersion $version, Article $article, Channel $channel): bool
    {
        $mode = $channel->image_mode ?? 'source';

        // Respect the old use_images flag
        if (!($channel->use_images ?? true)) {
            $mode = 'disabled';
        }

        if ($mode === 'disabled') {
            return false;
        }

        // Skip if already has image
        if ($this->repository->hasImage((int)$version->id)) {
            Logger::debug('ImageSelector: version already has image', ['version_id' => $version->id]);
            return true;
        }

        Logger::debug('ImageSelector: selecting image', [
            'version_id' => $version->id,
            'mode'       => $mode,
        ]);

        switch ($mode) {
            case 'source':
                return $this->trySource($version, $article);

            case 'enhanced':
                // source → library → Pexels → AI
                $ok = $this->trySource($version, $article);
                if (!$ok) {
                    $ok = $this->tryLibrary($version, $article);
                }
                if (!$ok) {
                    $ok = $this->tryPexels($version, $article);
                }
                if (!$ok) {
                    $ok = $this->tryAiGenerate($version, $article);
                }
                return $ok;

            case 'generated':
                // library → Pexels → AI
                $ok = $this->tryLibrary($version, $article);
                if (!$ok) {
                    $ok = $this->tryPexels($version, $article);
                }
                if (!$ok) {
                    $ok = $this->tryAiGenerate($version, $article);
                }
                return $ok;

            case 'library':
                // library only — no external calls
                return $this->tryLibrary($version, $article);

            case 'ai_only':
                return $this->tryAiGenerate($version, $article);

            case 'pexels_ai':
                // Pexels → AI генерация
                $ok = $this->tryPexels($version, $article);
                if (!$ok) {
                    $ok = $this->tryAiGenerate($version, $article);
                }
                return $ok;

            default:
                return false;
        }
    }

    /**
     * Extract the article's primary category slug from image_meta.
     * Returns lowercase English category (e.g. 'politics', 'criminal') or empty string.
     */
    private function getCategory(ArticleVersion $version): string
    {
        if (empty($version->image_meta)) {
            return '';
        }
        $meta = is_array($version->image_meta)
            ? $version->image_meta
            : json_decode($version->image_meta, true);

        $cat = strtolower(trim($meta['category'] ?? ''));
        // Sanitize: keep only a-z, digits, underscore
        $cat = preg_replace('/[^a-z0-9_]/', '_', $cat);
        return trim($cat, '_');
    }

    /**
     * Try to download the original image from the article (RSS / scraped).
     */
    private function trySource(ArticleVersion $version, Article $article): bool
    {
        $url = $article->scraped_image_url ?? $article->rss_image_url ?? null;
        if (empty($url)) {
            return false;
        }

        $category = $this->getCategory($version);
        $t = microtime(true);
        $fileData = $this->downloader->downloadFromUrl($url, $category);
        $ms = (int)((microtime(true) - $t) * 1000);

        if (!$fileData) {
            $this->repository->logSearch((int)$version->id, $url, 'local', 0, null, $ms);
            return false;
        }

        $imageId = $this->repository->upsert(array_merge($fileData, [
            'source'     => 'scraped',
            'source_url' => $url,
            'category'   => $category ?: null,
        ]));

        $this->repository->linkToVersion((int)$version->id, $imageId, 'scraped');
        $this->repository->markUsed($imageId);
        $this->repository->logSearch((int)$version->id, $url, 'local', 1, $imageId, $ms);

        Logger::info('ImageSelector: attached scraped image', [
            'version_id' => $version->id,
            'image_id'   => $imageId,
        ]);

        return true;
    }

    /**
     * Try to find an image via Pexels API using image_meta queries.
     */
    private function tryPexels(ArticleVersion $version, Article $article): bool
    {
        if (!$this->pexels->isConfigured()) {
            Logger::debug('ImageSelector: Pexels not configured');
            return false;
        }

        $queries  = $this->buildPexelsQueries($version, $article);
        $category = $this->getCategory($version);

        if (empty($queries)) {
            Logger::debug('ImageSelector: no Pexels queries available', ['version_id' => $version->id]);
            return false;
        }

        foreach ($queries as $query) {
            $t       = microtime(true);
            $results = $this->pexels->search($query, 3);
            $ms      = (int)((microtime(true) - $t) * 1000);

            $this->repository->logSearch((int)$version->id, $query, 'pexels', count($results), null, $ms);

            if (empty($results)) {
                continue;
            }

            $photo    = $results[0];
            $imageUrl = $photo['url'] ?? '';
            if (empty($imageUrl)) {
                continue;
            }

            $fileData = $this->downloader->downloadFromUrl($imageUrl, $category);
            if (!$fileData) {
                continue;
            }

            $imageId = $this->repository->upsert(array_merge($fileData, [
                'source'        => 'pexels',
                'external_id'   => $photo['id'],
                'alt_text'      => $photo['alt_text'] ?? '',
                'license_type'  => 'pexels',
                'license_url'   => 'https://www.pexels.com/license/',
                'photographer'  => $photo['photographer'] ?? '',
                'source_url'    => $photo['source_url'] ?? '',
                'category'      => $category ?: null,
            ]));

            $this->repository->linkToVersion((int)$version->id, $imageId, 'pexels');
            $this->repository->markUsed($imageId);

            // Update log with selected image
            $this->repository->logSearch((int)$version->id, $query, 'pexels', count($results), $imageId, $ms);

            Logger::info('ImageSelector: attached Pexels image', [
                'version_id' => $version->id,
                'image_id'   => $imageId,
                'query'      => $query,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Try to generate an image via Google Imagen AI.
     */
    private function tryAiGenerate(ArticleVersion $version, Article $article): bool
    {
        if (!$this->googleImage->isConfigured()) {
            Logger::debug('ImageSelector: Google Imagen not configured (gemini_api_key missing)');
            return false;
        }

        // Decode image_meta for prompt building
        $imageMeta = null;
        if (!empty($version->image_meta)) {
            $imageMeta = is_array($version->image_meta)
                ? $version->image_meta
                : json_decode($version->image_meta, true);
        }

        $fallbackTitle = $article->rss_title ?? $article->scraped_title ?? '';
        $prompt        = GoogleImageClient::buildPrompt($imageMeta, $fallbackTitle);

        Logger::debug('ImageSelector: AI generation prompt', [
            'version_id' => $version->id,
            'prompt'     => $prompt,
        ]);

        $t      = microtime(true);
        $result = $this->googleImage->generateImage($prompt);
        $ms     = (int)((microtime(true) - $t) * 1000);

        $this->repository->logSearch((int)$version->id, $prompt, 'ai', $result ? 1 : 0, null, $ms);

        if (!$result) {
            Logger::warning('ImageSelector: AI generation returned null', ['version_id' => $version->id]);
            return false;
        }

        $category = $this->getCategory($version);
        $fileData = $this->downloader->saveFromBinary($result['data'], $result['mime_type'], $category);
        if (!$fileData) {
            Logger::warning('ImageSelector: failed to save AI-generated image', ['version_id' => $version->id]);
            return false;
        }

        $imageId = $this->repository->upsert(array_merge($fileData, [
            'source'       => 'ai',
            'license_type' => 'ai-generated',
            'alt_text'     => mb_substr($prompt, 0, 200),
            'category'     => $category ?: null,
        ]));

        $this->repository->linkToVersion((int)$version->id, $imageId, 'ai_generated');
        $this->repository->markUsed($imageId);
        $this->repository->logSearch((int)$version->id, $prompt, 'ai', 1, $imageId, $ms);

        Logger::info('ImageSelector: attached AI-generated image', [
            'version_id' => $version->id,
            'image_id'   => $imageId,
            'model'      => \NewsBot\Core\Settings::get('image_ai_model', 'imagen-3.0-generate-002'),
        ]);

        return true;
    }

    /**
     * Try to select an image from the local image library by category.
     * Picks the least-used image to distribute usage evenly.
     */
    private function tryLibrary(ArticleVersion $version, Article $article): bool
    {
        $category = $this->getCategory($version);

        if (empty($category)) {
            Logger::debug('ImageSelector: library - no category in image_meta', ['version_id' => $version->id]);
            return false;
        }

        $images = $this->repository->findByCategory($category, (int)$version->id, 5);

        if (empty($images)) {
            Logger::debug('ImageSelector: library - no images for category', [
                'version_id' => $version->id,
                'category'   => $category,
            ]);
            return false;
        }

        // Pick randomly from top-5 least-used to add variety
        $image   = $images[array_rand($images)];
        $imageId = (int)$image['id'];

        $this->repository->linkToVersion((int)$version->id, $imageId, 'library');
        $this->repository->markUsed($imageId);

        Logger::info('ImageSelector: attached from library', [
            'version_id' => $version->id,
            'image_id'   => $imageId,
            'category'   => $category,
        ]);

        return true;
    }

    /**
     * Build Pexels search queries from image_meta + article title fallback.
     *
     * @return string[]
     */
    private function buildPexelsQueries(ArticleVersion $version, Article $article): array
    {
        $queries = [];

        // Extract from image_meta JSON
        if (!empty($version->image_meta)) {
            $meta = is_array($version->image_meta)
                ? $version->image_meta
                : json_decode($version->image_meta, true);

            if (is_array($meta)) {
                // Use specific image_queries first
                if (!empty($meta['image_queries']) && is_array($meta['image_queries'])) {
                    foreach ($meta['image_queries'] as $q) {
                        if (!empty(trim((string)$q))) {
                            $queries[] = trim((string)$q);
                        }
                    }
                }

                // Fallback: event_title
                if (!empty($meta['event_title'])) {
                    $queries[] = (string)$meta['event_title'];
                }

                // Fallback: category + main_entity
                if (!empty($meta['category']) && !empty($meta['main_entity'])) {
                    $queries[] = $meta['category'] . ' ' . $meta['main_entity'];
                }
            }
        }

        // Final fallback: use article title (first 60 chars)
        $title = $article->rss_title ?? $article->scraped_title ?? '';
        if (!empty($title)) {
            $queries[] = mb_substr($title, 0, 60);
        }

        return array_unique(array_filter($queries));
    }
}
