<?php declare(strict_types=1);

namespace NewsBot\Pipeline;

use NewsBot\Core\Database;
use NewsBot\Core\Logger;
use NewsBot\Core\ShutdownHandler;
use NewsBot\Models\Article;
use NewsBot\Models\Channel;
use NewsBot\Models\ChannelSource;
use NewsBot\Models\Feed;
use NewsBot\Models\Source;
use NewsBot\Models\SourceParser;
use NewsBot\Services\RssParser;
use NewsBot\Services\UrlCanonizer;
use NewsBot\Helpers\UrlResolver;

/**
 * Pipeline Step 1: Fetch articles from RSS feeds and custom parsers.
 */
class FetchStep
{
    private const DOMAIN_DELAY_SECONDS_DEFAULT = 1.5;

    private RssParser $rssParser;
    private int $articlesTotal = 0;
    private int $articlesOk = 0;
    private int $articlesFailed = 0;
    private array $domainLastFetch = [];

    public function __construct()
    {
        $this->rssParser = new RssParser();
    }

    /**
     * Run the fetch step.
     *
     * @param array $channelIds Channel IDs to process (empty = all active)
     */
    public function run(array $channelIds = []): void
    {
        $startedAt = new \DateTimeImmutable();

        try {
            // Get active channels
            $channels = $this->getChannels($channelIds);
            if (empty($channels)) {
                Logger::info('FetchStep: No active channels to process');
                return;
            }

            // Collect unique feeds from all channels
            $feedsMap = $this->collectFeeds($channels);
            $feedCount = count($feedsMap);

            Logger::info('FetchStep: Starting', [
                'channels' => count($channels),
                'feeds' => $feedCount,
            ]);

            // Process RSS feeds
            $batchUrls = []; // Track canonical URLs within this fetch run
            foreach ($feedsMap as $feedId => $feedData) {
                if (ShutdownHandler::shouldShutdown()) {
                    Logger::info('FetchStep: Shutdown requested');
                    break;
                }

                $this->throttleDomain($feedData['feed']->url);
                $this->processFeed($feedData['feed'], $feedData['source'], $batchUrls);
            }

            // Process custom parser sources
            $customParserSources = $this->collectCustomParserSources($channels);
            $this->processCustomParsers($customParserSources, $batchUrls);

            Logger::info('FetchStep: Completed', [
                'total' => $this->articlesTotal,
                'ok' => $this->articlesOk,
                'failed' => $this->articlesFailed,
            ]);

        } finally {
            $this->logPipelineRun($startedAt, $channelIds);
        }
    }

    /**
     * Get channels to process.
     */
    private function getChannels(array $channelIds): array
    {
        if (empty($channelIds)) {
            return Channel::getActive();
        }

        $channels = [];
        foreach ($channelIds as $id) {
            $channel = Channel::find($id);
            if ($channel && $channel->isActive()) {
                $channels[] = $channel;
            }
        }
        return $channels;
    }

    /**
     * Collect unique feeds from channels.
     *
     * @return array [feedId => ['feed' => Feed, 'source' => Source], ...]
     */
    private function collectFeeds(array $channels): array
    {
        $feedsMap = [];

        foreach ($channels as $channel) {
            $feeds = $channel->getActiveFeeds();
            foreach ($feeds as $feed) {
                if (!isset($feedsMap[$feed->id])) {
                    $source = $feed->getSource();
                    if ($source && $source->isActive()) {
                        $feedsMap[$feed->id] = [
                            'feed' => $feed,
                            'source' => $source,
                        ];
                    }
                }
            }
        }

        return $feedsMap;
    }

    /**
     * Collect custom parser sources from channels.
     *
     * @return array [sourceId => Source, ...]
     */
    private function collectCustomParserSources(array $channels): array
    {
        $sourcesMap = [];

        foreach ($channels as $channel) {
            $sources = $channel->getSources();
            foreach ($sources as $source) {
                if ($source->isActive() && $source->usesCustomParser()) {
                    if (!isset($sourcesMap[$source->id])) {
                        $sourcesMap[$source->id] = $source;
                    }
                }
            }
        }

        return $sourcesMap;
    }

    /**
     * Pause before fetching a feed if the same domain was recently requested.
     */
    private function throttleDomain(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST) ?? $url;
        $now = microtime(true);

        if (isset($this->domainLastFetch[$host])) {
            $delay = (float)(($_ENV['FETCH_DOMAIN_DELAY_MS'] ?? (self::DOMAIN_DELAY_SECONDS_DEFAULT * 1000)) / 1000);
            $elapsed = $now - $this->domainLastFetch[$host];
            if ($elapsed < $delay) {
                usleep((int)(($delay - $elapsed) * 1_000_000));
            }
        }

        $this->domainLastFetch[$host] = microtime(true);
    }

    /**
     * Process a single RSS feed.
     */
    private function processFeed(Feed $feed, Source $source, array &$batchUrls): void
    {
        try {
            $items = $this->rssParser->parse(
                $feed->url,
                $feed->date_filter ?? 'none',
                $feed->date_filter_hours ? (int)$feed->date_filter_hours : null
            );

            $newArticles = 0;
            foreach ($items as $item) {
                $result = $this->processItem($item, $feed, $source, $batchUrls);
                if ($result) {
                    $newArticles++;
                    $this->articlesOk++;
                }
                $this->articlesTotal++;
            }

            // Reset errors on success
            $feed->resetErrors();

            Logger::debug('Feed processed', [
                'feed_id' => $feed->id,
                'url' => $feed->url,
                'items' => count($items),
                'new' => $newArticles,
            ]);

        } catch (\Throwable $e) {
            $this->articlesFailed++;
            $feed->incrementErrors($e->getMessage());

            Logger::error('Feed processing failed', [
                'feed_id' => $feed->id,
                'url' => $feed->url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process a single RSS item.
     *
     * @return bool True if article was created
     */
    private function processItem(array $item, Feed $feed, Source $source, array &$batchUrls): bool
    {
        $link = $item['link'] ?? '';
        if (empty($link)) {
            return false;
        }

        // Resolve relative URL
        if (!preg_match('#^https?://#i', $link)) {
            $link = UrlResolver::resolve($link, $source->site_url);
        }

        // Canonize URL
        $canonicalUrl = UrlCanonizer::canonize($link);
        $urlHash = UrlCanonizer::hash($link);

        // Check if already in current batch
        if (isset($batchUrls[$urlHash])) {
            return false;
        }

        // Check if URL already exists in database
        $existing = Article::findByUrlHash($urlHash);
        if ($existing) {
            return false;
        }

        // Check if RSS GUID already exists
        $guid = $item['guid'] ?? null;
        if ($guid) {
            $existingByGuid = Article::findBy('rss_guid', $guid);
            if ($existingByGuid) {
                return false;
            }
        }

        // Mark URL as seen in batch
        $batchUrls[$urlHash] = true;

        // Create article
        try {
            Article::create([
                'feed_id' => $feed->id,
                'source_id' => $source->id,
                'url' => $canonicalUrl,
                'url_hash' => $urlHash,
                'rss_guid' => $guid,
                'rss_title' => $this->truncate($item['title'] ?? '', 500),
                'rss_description' => $item['description'] ?? null,
                'rss_content' => $item['content'] ?? null,
                'rss_pub_date' => $item['pub_date'] ?? null,
                'rss_image_url' => $this->truncate($item['image_url'] ?? '', 1000),
                'status' => 'fetched',
            ]);

            return true;
        } catch (\Throwable $e) {
            Logger::warning('Failed to create article', [
                'url' => $canonicalUrl,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Process custom parser sources.
     */
    private function processCustomParsers(array $sources, array &$batchUrls): void
    {
        // Check if WebParser class exists (Module 2a)
        if (!class_exists('NewsBot\Services\WebParser')) {
            if (!empty($sources)) {
                Logger::debug('FetchStep: WebParser not available, skipping custom parsers', [
                    'sources' => count($sources),
                ]);
            }
            return;
        }

        foreach ($sources as $source) {
            if (ShutdownHandler::shouldShutdown()) {
                break;
            }

            $parser = SourceParser::findForSource((int)$source->id);
            if (!$parser || !$parser->isActive()) {
                continue;
            }

            if (!$parser->isDueForFetch()) {
                Logger::debug('Custom parser skipped (interval not elapsed)', [
                    'source_id' => $source->id,
                    'last_parsed_at' => $parser->last_parsed_at,
                    'interval_min' => $parser->fetch_interval_min,
                ]);
                continue;
            }

            $this->processCustomParser($source, $parser, $batchUrls);
        }
    }

    /**
     * Process a single custom parser source.
     */
    private function processCustomParser(Source $source, SourceParser $parserConfig, array &$batchUrls): void
    {
        $startedAt = new \DateTimeImmutable();
        $pagesParsersd = 0;
        $articlesFound = 0;
        $articlesNew = 0;
        $articlesSkipped = 0;
        $errorMessage = null;

        try {
            // Create WebParser instance
            $webParserClass = 'NewsBot\Services\WebParser';
            $webParser = new $webParserClass();

            // Parse list of articles
            $items = $webParser->parseList($parserConfig);
            $articlesFound = count($items);
            $pagesParsersd = $parserConfig->max_pages ?? 1;

            foreach ($items as $item) {
                $url = $item['url'] ?? '';
                if (empty($url)) {
                    continue;
                }

                // Resolve relative URL
                if (!preg_match('#^https?://#i', $url)) {
                    $url = UrlResolver::resolve($url, $source->site_url);
                }

                // Canonize and hash
                $canonicalUrl = UrlCanonizer::canonize($url);
                $urlHash = UrlCanonizer::hash($url);

                // Check duplicates
                if (isset($batchUrls[$urlHash])) {
                    $articlesSkipped++;
                    continue;
                }

                $existing = Article::findByUrlHash($urlHash);
                if ($existing) {
                    $articlesSkipped++;
                    continue;
                }

                $batchUrls[$urlHash] = true;

                // Create article (feed_id = NULL for custom parser)
                Article::create([
                    'feed_id' => null,
                    'source_id' => $source->id,
                    'url' => $canonicalUrl,
                    'url_hash' => $urlHash,
                    'rss_guid' => null,
                    'rss_title' => $this->truncate($item['title'] ?? '', 500),
                    'rss_description' => $item['description'] ?? null,
                    'rss_content' => null,
                    'rss_pub_date' => $item['date'] ?? null,
                    'rss_image_url' => $this->truncate($item['image'] ?? '', 1000),
                    'status' => 'fetched',
                ]);

                $articlesNew++;
                $this->articlesOk++;
                $this->articlesTotal++;
            }

            $parserConfig->resetErrors($articlesNew);

            Logger::debug('Custom parser processed', [
                'source_id' => $source->id,
                'found' => $articlesFound,
                'new' => $articlesNew,
                'skipped' => $articlesSkipped,
            ]);

        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $this->articlesFailed++;
            $parserConfig->incrementErrors($errorMessage);

            Logger::error('Custom parser failed', [
                'source_id' => $source->id,
                'error' => $errorMessage,
            ]);
        }

        // Log parser run
        $this->logParserRun(
            $parserConfig->id,
            $startedAt,
            $pagesParsersd,
            $articlesFound,
            $articlesNew,
            $articlesSkipped,
            $errorMessage
        );
    }

    /**
     * Log parser run to parser_runs table.
     */
    private function logParserRun(
        int $parserId,
        \DateTimeImmutable $startedAt,
        int $pagesParsersd,
        int $articlesFound,
        int $articlesNew,
        int $articlesSkipped,
        ?string $errorMessage
    ): void {
        $finishedAt = new \DateTimeImmutable();
        $durationMs = (int)(($finishedAt->getTimestamp() - $startedAt->getTimestamp()) * 1000);

        try {
            Database::insert('parser_runs', [
                'source_parser_id' => $parserId,
                'started_at' => $startedAt->format('Y-m-d H:i:s'),
                'finished_at' => $finishedAt->format('Y-m-d H:i:s'),
                'duration_ms' => $durationMs,
                'pages_parsed' => $pagesParsersd,
                'articles_found' => $articlesFound,
                'articles_new' => $articlesNew,
                'articles_skipped' => $articlesSkipped,
                'error_message' => $errorMessage,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Failed to log parser run', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Log pipeline run to pipeline_runs table.
     */
    private function logPipelineRun(\DateTimeImmutable $startedAt, array $channelIds): void
    {
        $finishedAt = new \DateTimeImmutable();
        $durationMs = (int)(($finishedAt->getTimestamp() - $startedAt->getTimestamp()) * 1000);

        try {
            Database::insert('pipeline_runs', [
                'step' => 'fetch',
                'channel_ids' => empty($channelIds) ? null : implode(',', $channelIds),
                'started_at' => $startedAt->format('Y-m-d H:i:s'),
                'finished_at' => $finishedAt->format('Y-m-d H:i:s'),
                'duration_ms' => $durationMs,
                'articles_total' => $this->articlesTotal,
                'articles_ok' => $this->articlesOk,
                'articles_failed' => $this->articlesFailed,
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Failed to log pipeline run', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Truncate string to max length.
     */
    private function truncate(?string $str, int $maxLength): ?string
    {
        if ($str === null || $str === '') {
            return null;
        }

        if (mb_strlen($str, 'UTF-8') <= $maxLength) {
            return $str;
        }

        return mb_substr($str, 0, $maxLength, 'UTF-8');
    }
}
