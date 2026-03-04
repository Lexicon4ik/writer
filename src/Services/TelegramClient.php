<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\Logger;
use NewsBot\Helpers\TelegramFormatter;

/**
 * Telegram Bot API client.
 * Handles all communication with Telegram servers.
 */
class TelegramClient
{
    /**
     * Telegram Bot API base URL.
     */
    private const API_BASE = 'https://api.telegram.org/bot';

    /**
     * Connection timeout in seconds.
     */
    private const CONNECT_TIMEOUT = 10;

    /**
     * Request timeout in seconds.
     */
    private const REQUEST_TIMEOUT = 30;

    /**
     * Maximum retry attempts for network errors.
     */
    private const MAX_RETRIES = 3;

    /**
     * Base delay between retries in seconds.
     */
    private const RETRY_DELAY = 3;

    /**
     * Maximum text length for sendMessage.
     */
    public const MAX_TEXT_LENGTH = 4096;

    /**
     * Maximum caption length for sendPhoto.
     */
    public const MAX_CAPTION_LENGTH = 1024;

    /**
     * Send a text message to a chat.
     *
     * @param string $token Bot token
     * @param string $chatId Chat ID (can be @username for channels)
     * @param string $text Message text (will be formatted for Telegram HTML)
     * @param string $parseMode Parse mode (HTML or Markdown)
     * @return array API response with message_id
     * @throws TelegramException on permanent error
     */
    public function sendMessage(
        string $token,
        string $chatId,
        string $text,
        string $parseMode = 'HTML'
    ): array {
        // Format text for Telegram
        $formattedText = $this->formatText($text);

        // Truncate if needed
        if (mb_strlen(strip_tags($formattedText)) > self::MAX_TEXT_LENGTH) {
            $formattedText = TelegramFormatter::truncateSafe($formattedText, self::MAX_TEXT_LENGTH - 10);
        }

        return $this->apiCall($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $formattedText,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => false,
        ]);
    }

    /**
     * Send a photo with caption to a chat.
     *
     * @param string $token Bot token
     * @param string $chatId Chat ID
     * @param string $photoUrl Photo URL
     * @param string $caption Caption text (will be formatted)
     * @param string $parseMode Parse mode
     * @return array API response with message_id
     * @throws TelegramException on permanent error
     */
    public function sendPhoto(
        string $token,
        string $chatId,
        string $photoUrl,
        string $caption = '',
        string $parseMode = 'HTML'
    ): array {
        // Format and truncate caption
        $formattedCaption = '';
        if (!empty($caption)) {
            $formattedCaption = $this->formatText($caption);
            if (mb_strlen(strip_tags($formattedCaption)) > self::MAX_CAPTION_LENGTH) {
                $formattedCaption = TelegramFormatter::truncateSafe($formattedCaption, self::MAX_CAPTION_LENGTH - 10);
            }
        }

        $params = [
            'chat_id' => $chatId,
            'photo' => $photoUrl,
            'parse_mode' => $parseMode,
        ];

        if (!empty($formattedCaption)) {
            $params['caption'] = $formattedCaption;
        }

        return $this->apiCall($token, 'sendPhoto', $params);
    }

    /**
     * Edit a text message.
     *
     * @param string $token Bot token
     * @param string $chatId Chat ID
     * @param int $messageId Message ID to edit
     * @param string $text New text
     * @param string $parseMode Parse mode
     * @return array API response
     * @throws TelegramException on permanent error
     */
    public function editMessageText(
        string $token,
        string $chatId,
        int $messageId,
        string $text,
        string $parseMode = 'HTML'
    ): array {
        $formattedText = $this->formatText($text);

        if (mb_strlen(strip_tags($formattedText)) > self::MAX_TEXT_LENGTH) {
            $formattedText = TelegramFormatter::truncateSafe($formattedText, self::MAX_TEXT_LENGTH - 10);
        }

        return $this->apiCall($token, 'editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $formattedText,
            'parse_mode' => $parseMode,
        ]);
    }

    /**
     * Edit a message caption (for photos).
     *
     * @param string $token Bot token
     * @param string $chatId Chat ID
     * @param int $messageId Message ID to edit
     * @param string $caption New caption
     * @param string $parseMode Parse mode
     * @return array API response
     * @throws TelegramException on permanent error
     */
    public function editMessageCaption(
        string $token,
        string $chatId,
        int $messageId,
        string $caption,
        string $parseMode = 'HTML'
    ): array {
        $formattedCaption = $this->formatText($caption);

        if (mb_strlen(strip_tags($formattedCaption)) > self::MAX_CAPTION_LENGTH) {
            $formattedCaption = TelegramFormatter::truncateSafe($formattedCaption, self::MAX_CAPTION_LENGTH - 10);
        }

        return $this->apiCall($token, 'editMessageCaption', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $formattedCaption,
            'parse_mode' => $parseMode,
        ]);
    }

    /**
     * Delete a message.
     *
     * @param string $token Bot token
     * @param string $chatId Chat ID
     * @param int $messageId Message ID to delete
     * @return bool True on success
     * @throws TelegramException on permanent error
     */
    public function deleteMessage(string $token, string $chatId, int $messageId): bool
    {
        try {
            $result = $this->apiCall($token, 'deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
            return true;
        } catch (TelegramException $e) {
            // Message already deleted or too old (48h limit)
            if ($e->getCode() === 400) {
                Logger::warning('Cannot delete message', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }
            throw $e;
        }
    }

    /**
     * Check if an image URL is accessible by Telegram.
     *
     * @param string $url Image URL to check
     * @return bool True if accessible
     */
    public function isImageAccessible(string $url): bool
    {
        return ImageValidator::isAccessible($url);
    }

    /**
     * Get bot information.
     *
     * @param string $token Bot token
     * @return array Bot info
     */
    public function getMe(string $token): array
    {
        return $this->apiCall($token, 'getMe', []);
    }

    /**
     * Get chat information.
     *
     * @param string $token Bot token
     * @param string $chatId Chat ID
     * @return array Chat info
     */
    public function getChat(string $token, string $chatId): array
    {
        return $this->apiCall($token, 'getChat', ['chat_id' => $chatId]);
    }

    /**
     * Format text for Telegram HTML.
     *
     * @param string $text Raw text
     * @return string Formatted text
     */
    private function formatText(string $text): string
    {
        // Strip unsupported HTML tags
        $text = TelegramFormatter::stripUnsupportedTags($text);

        // Escape special characters while preserving allowed tags
        $text = TelegramFormatter::escape($text);

        // Normalize whitespace
        $text = TelegramFormatter::normalizeWhitespace($text);

        return $text;
    }

    /**
     * Make API call to Telegram Bot API.
     *
     * @param string $token Bot token
     * @param string $method API method name
     * @param array $params Method parameters
     * @return array API response (result field)
     * @throws TelegramException on error
     */
    private function apiCall(string $token, string $method, array $params): array
    {
        $url = self::API_BASE . $token . '/' . $method;

        $attempt = 0;
        $lastError = null;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            // Apply global rate limiting
            TelegramRateLimiter::wait();

            $ch = curl_init($url);
            if ($ch === false) {
                throw new TelegramException('cURL init failed', 0);
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);

            // Handle cURL errors (network issues)
            if ($curlErrno !== 0) {
                $lastError = new TelegramException("Network error: {$curlError}", $curlErrno);
                Logger::warning('Telegram API network error', [
                    'method' => $method,
                    'error' => $curlError,
                    'attempt' => $attempt,
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAY * $attempt;
                    sleep($delay);
                    continue;
                }

                throw $lastError;
            }

            // Parse response
            $data = json_decode($response, true);
            if (!is_array($data)) {
                $lastError = new TelegramException('Invalid JSON response', $httpCode);
                Logger::error('Telegram API invalid response', [
                    'method' => $method,
                    'response' => substr($response, 0, 500),
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    sleep(self::RETRY_DELAY);
                    continue;
                }

                throw $lastError;
            }

            // Success
            if (isset($data['ok']) && $data['ok'] === true) {
                Logger::debug('Telegram API success', [
                    'method' => $method,
                    'chat_id' => $params['chat_id'] ?? null,
                ]);
                return is_array($data['result']) ? $data['result'] : [];
            }

            // Handle API errors
            $errorCode = $data['error_code'] ?? 0;
            $errorDescription = $data['description'] ?? 'Unknown error';

            // Rate limit (429) - wait and retry
            if ($errorCode === 429) {
                $retryAfter = $data['parameters']['retry_after'] ?? 30;
                Logger::warning('Telegram rate limited', [
                    'method' => $method,
                    'retry_after' => $retryAfter,
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    sleep($retryAfter);
                    continue;
                }
            }

            // Permanent errors (400, 403) - don't retry
            if ($errorCode === 400 || $errorCode === 403) {
                Logger::error('Telegram API permanent error', [
                    'method' => $method,
                    'error_code' => $errorCode,
                    'description' => $errorDescription,
                    'params' => array_diff_key($params, ['text' => 1, 'caption' => 1]), // Don't log content
                ]);

                throw new TelegramException($errorDescription, $errorCode);
            }

            // Other errors - retry
            $lastError = new TelegramException($errorDescription, $errorCode);
            Logger::warning('Telegram API error, retrying', [
                'method' => $method,
                'error_code' => $errorCode,
                'description' => $errorDescription,
                'attempt' => $attempt,
            ]);

            if ($attempt < self::MAX_RETRIES) {
                $delay = self::RETRY_DELAY * $attempt;
                sleep($delay);
            }
        }

        throw $lastError ?? new TelegramException('Max retries exceeded', 0);
    }
}
