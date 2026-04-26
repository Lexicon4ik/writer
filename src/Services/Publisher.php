<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\{Logger, Crypto, Database};
use NewsBot\Models\{Article, ArticleVersion, Channel, Bot};
use NewsBot\Helpers\TelegramFormatter;
use NewsBot\Services\Image\ImageDownloader;

/**
 * Article publisher for Telegram channels.
 * Handles post building, formatting, and publishing logic.
 */
class Publisher
{
    private TelegramClient $telegram;

    public function __construct(?TelegramClient $telegram = null)
    {
        $this->telegram = $telegram ?? new TelegramClient();
    }

    /**
     * Build post text from channel template.
     * Replaces placeholders with article data.
     *
     * @param ArticleVersion $version Article version with processed content
     * @param Article $article Original article
     * @param Channel $channel Channel with post_template
     * @return string Formatted post text
     */
    public function buildPost(ArticleVersion $version, Article $article, Channel $channel): string
    {
        $template = $channel->post_template ?? "{{title}}\n\n{{body}}";

        // Prepare replacements
        $title = TelegramFormatter::escape($version->title ?? $article->rss_title ?? '');
        $shortTitle = TelegramFormatter::escape($version->short_title ?? $version->title ?? '');
        $body = TelegramFormatter::escape($version->body ?? '');
        $description = TelegramFormatter::escape($version->description ?? '');
        $sourceLink = $article->url ?? '';

        // Format hashtags
        $hashtags = $this->formatHashtags($version->getHashtags());

        // Format date
        $date = '';
        if (!empty($article->rss_pub_date)) {
            $date = date('d.m.Y', strtotime($article->rss_pub_date));
        } elseif (!empty($article->created_at)) {
            $date = date('d.m.Y', strtotime($article->created_at));
        }

        // Build replacements (support both {{double}} and {single} braces)
        $replacements = [
            // Double braces
            '{{title}}' => "<b>{$title}</b>",
            '{{short_title}}' => $shortTitle,
            '{{body}}' => $body,
            '{{description}}' => $description,
            '{{hashtags}}' => $hashtags,
            '{{source_link}}' => $sourceLink,
            '{{source_name}}' => $article->getSource()?->name ?? '',
            '{{url}}' => $sourceLink,
            '{{date}}' => $date,
            '{{source}}' => $article->getSource()?->name ?? '',
            // Single braces (for backwards compatibility with docs)
            '{title}' => "<b>{$title}</b>",
            '{short_title}' => $shortTitle,
            '{body}' => $body,
            '{description}' => $description,
            '{hashtags}' => $hashtags,
            '{source_link}' => $sourceLink,
            '{source_name}' => $article->getSource()?->name ?? '',
            '{url}' => $sourceLink,
            '{date}' => $date,
            '{source}' => $article->getSource()?->name ?? '',
        ];

        // Apply replacements
        $post = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );

        // Normalize whitespace
        $post = TelegramFormatter::normalizeWhitespace($post);

        return $post;
    }

    /**
     * Truncate post to fit Telegram limits.
     * Adds "Read more" link if truncated.
     *
     * @param string $post Post text
     * @param int $maxLength Maximum length
     * @param string|null $sourceUrl Source URL for "Read more" link
     * @return string Truncated post
     */
    public function truncatePost(string $post, int $maxLength, ?string $sourceUrl = null): string
    {
        $visibleLength = mb_strlen(strip_tags($post));

        if ($visibleLength <= $maxLength) {
            return $post;
        }

        // Reserve space for ellipsis and read more link
        $readMoreText = '';
        $reservedLength = 3; // "…"

        if ($sourceUrl) {
            $readMoreText = "\n\n<a href=\"{$sourceUrl}\">Читать полностью</a>";
            $reservedLength += mb_strlen(strip_tags($readMoreText)) + 2;
        }

        // Truncate
        $truncated = TelegramFormatter::truncateSafe($post, $maxLength - $reservedLength);
        $truncated .= '…';

        if ($readMoreText) {
            $truncated .= $readMoreText;
        }

        return $truncated;
    }

    /**
     * Publish an article version to Telegram.
     *
     * @param ArticleVersion $version Article version to publish
     * @param Article $article Original article
     * @param Channel $channel Target channel
     * @return int|null Telegram message ID on success, null on failure
     * @throws TelegramException on permanent error
     */
    public function publish(ArticleVersion $version, Article $article, Channel $channel): ?int
    {
        // Get bot
        $bot = $channel->getBot();
        if (!$bot) {
            Logger::error('Channel has no bot', ['channel_id' => $channel->id]);
            return null;
        }

        // Check bot status
        if (!$bot->isActive()) {
            Logger::warning('Bot is not active', [
                'bot_id' => $bot->id,
                'channel_id' => $channel->id,
            ]);
            return null;
        }

        // Get token
        $token = $bot->getToken();
        if (empty($token)) {
            Logger::error('Bot token is empty', ['bot_id' => $bot->id]);
            return null;
        }

        // Build post
        $post = $this->buildPost($version, $article, $channel);

        // Get chat ID
        $chatId = $channel->chat_id ?? '';
        if (empty($chatId)) {
            Logger::error('Channel has no chat_id', ['channel_id' => $channel->id]);
            return null;
        }

        // Determine send method: look up local image from article_version_images
        $photo = $this->shouldSendAsPhoto($version, $channel)
            ? $this->getVersionPhoto((int)$version->id)
            : null;

        try {
            if ($photo !== null) {
                // Send as photo if caption fits
                $visibleLength = mb_strlen(strip_tags($post));
                if ($visibleLength <= TelegramClient::MAX_CAPTION_LENGTH) {
                    $result = $this->telegram->sendPhoto($token, $chatId, $photo, $post);
                    Logger::info('Published with photo', [
                        'channel_id' => $channel->id,
                        'article_id' => $article->id,
                        'version_id' => $version->id,
                    ]);
                    return (int)($result['message_id'] ?? 0);
                }

                // Caption too long — send photo without caption, then full text separately
                $this->telegram->sendPhoto($token, $chatId, $photo, '');
                Logger::info('Published photo without caption (text too long)', [
                    'channel_id' => $channel->id,
                    'article_id' => $article->id,
                ]);

                // Fall through to send text as separate message below
            }

            // Send as text message (full text, no truncation unless exceeds 4096)
            $visibleLength = mb_strlen(strip_tags($post));
            if ($visibleLength > TelegramClient::MAX_TEXT_LENGTH) {
                $post = $this->truncatePost($post, TelegramClient::MAX_TEXT_LENGTH, $article->url);
            }

            $result = $this->telegram->sendMessage($token, $chatId, $post);
            Logger::info('Published as text', [
                'channel_id' => $channel->id,
                'article_id' => $article->id,
                'version_id' => $version->id,
            ]);
            return (int)($result['message_id'] ?? 0);

        } catch (TelegramException $e) {
            Logger::error('Telegram publish failed', [
                'channel_id' => $channel->id,
                'article_id' => $article->id,
                'version_id' => $version->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            // Re-throw permanent errors
            if ($e->isPermanent()) {
                throw $e;
            }

            return null;
        }
    }

    /**
     * Edit an already published post.
     *
     * @param ArticleVersion $version Version with updated content
     * @param Channel $channel Channel where post was published
     * @return bool True on success
     */
    public function editPost(ArticleVersion $version, Channel $channel): bool
    {
        if (empty($version->telegram_message_id)) {
            Logger::warning('Cannot edit: no telegram_message_id', ['version_id' => $version->id]);
            return false;
        }

        $bot = $channel->getBot();
        if (!$bot) {
            return false;
        }

        $token = $bot->getToken();
        $chatId = $channel->chat_id ?? '';

        if (empty($token) || empty($chatId)) {
            return false;
        }

        $article = $version->getArticle();
        if (!$article) {
            return false;
        }

        try {
            $post = $this->buildPost($version, $article, $channel);

            // Check if it was sent as photo or text
            // For simplicity, we try to edit as text first, then caption
            try {
                $this->telegram->editMessageText(
                    $token,
                    $chatId,
                    (int)$version->telegram_message_id,
                    $post
                );
            } catch (TelegramException $e) {
                if ($e->getCode() === 400 && str_contains($e->getMessage(), 'no text')) {
                    // Message has no text, try caption
                    $this->telegram->editMessageCaption(
                        $token,
                        $chatId,
                        (int)$version->telegram_message_id,
                        $post
                    );
                } else {
                    throw $e;
                }
            }

            Logger::info('Post edited', [
                'version_id' => $version->id,
                'message_id' => $version->telegram_message_id,
            ]);

            return true;

        } catch (TelegramException $e) {
            Logger::error('Edit post failed', [
                'version_id' => $version->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete a published post.
     *
     * @param ArticleVersion $version Version to delete
     * @param Channel $channel Channel where post was published
     * @return bool True on success
     */
    public function deletePost(ArticleVersion $version, Channel $channel): bool
    {
        if (empty($version->telegram_message_id)) {
            Logger::warning('Cannot delete: no telegram_message_id', ['version_id' => $version->id]);
            return false;
        }

        $bot = $channel->getBot();
        if (!$bot) {
            return false;
        }

        $token = $bot->getToken();
        $chatId = $channel->chat_id ?? '';

        if (empty($token) || empty($chatId)) {
            return false;
        }

        try {
            $result = $this->telegram->deleteMessage(
                $token,
                $chatId,
                (int)$version->telegram_message_id
            );

            if ($result) {
                Logger::info('Post deleted', [
                    'version_id' => $version->id,
                    'message_id' => $version->telegram_message_id,
                ]);
            }

            return $result;

        } catch (TelegramException $e) {
            Logger::error('Delete post failed', [
                'version_id' => $version->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Determine if article version should be sent as photo.
     * Checks channel settings and whether a local image is attached to the version.
     */
    private function shouldSendAsPhoto(ArticleVersion $version, Channel $channel): bool
    {
        if (!($channel->use_images ?? true)) {
            return false;
        }

        $imageMode = $channel->image_mode ?? 'source';
        if ($imageMode === 'disabled') {
            return false;
        }

        // Check that a local image is actually attached
        $row = Database::fetchOne(
            "SELECT 1 FROM article_version_images WHERE article_version_id = ? LIMIT 1",
            [(int)$version->id]
        );

        return $row !== null;
    }

    /**
     * Get a CURLFile (local file) or null for the version's primary image.
     * Falls back to null if file is missing on disk.
     */
    private function getVersionPhoto(int $versionId): ?\CURLFile
    {
        $row = Database::fetchOne(
            "SELECT i.file_path, i.mime_type
             FROM article_version_images avi
             JOIN images i ON i.id = avi.image_id
             WHERE avi.article_version_id = ? AND avi.position = 1
             LIMIT 1",
            [$versionId]
        );

        if (!$row || empty($row['file_path'])) {
            return null;
        }

        $downloader = new ImageDownloader();
        $absPath    = $downloader->getAbsolutePath($row['file_path']);

        if (!file_exists($absPath)) {
            Logger::warning('Publisher: image file missing on disk', [
                'version_id' => $versionId,
                'path'       => $absPath,
            ]);
            return null;
        }

        return new \CURLFile($absPath, $row['mime_type'] ?? 'image/jpeg');
    }

    /**
     * Format hashtags array to string.
     *
     * @param array $hashtags Array of hashtag strings
     * @return string Formatted hashtags (each prefixed with #)
     */
    private function formatHashtags(array $hashtags): string
    {
        if (empty($hashtags)) {
            return '';
        }

        $formatted = array_map(function ($tag) {
            // Ensure tag starts with #
            $tag = ltrim($tag, '#');
            // Remove spaces and special chars
            $tag = preg_replace('/[^a-zA-Z0-9_\p{L}]/u', '', $tag);
            return '#' . $tag;
        }, $hashtags);

        // Remove empty tags
        $formatted = array_filter($formatted, fn($t) => strlen($t) > 1);

        return implode(' ', $formatted);
    }
}
