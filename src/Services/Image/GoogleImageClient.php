<?php declare(strict_types=1);

namespace NewsBot\Services\Image;

use NewsBot\Core\{Logger, Settings};
use NewsBot\Services\Crypto;

/**
 * Google Imagen API client for AI image generation.
 *
 * Reuses the existing gemini_api_key — the same Google AI Studio key
 * works for both Gemini text models and Imagen image generation.
 *
 * API: POST https://generativelanguage.googleapis.com/v1beta/models/{model}:predict?key={API_KEY}
 * Price: ~$0.04 per image (Imagen 3).
 */
class GoogleImageClient
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const TIMEOUT  = 60;

    private string $apiKey;
    private string $model;

    public function __construct()
    {
        // Reuse existing gemini_api_key
        $encrypted     = Settings::get('gemini_api_key', '');
        $this->apiKey  = \NewsBot\Core\Crypto::decryptSafe($encrypted);
        $this->model   = Settings::get('image_ai_model', 'imagen-3.0-generate-002');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Generate an image from a text prompt.
     *
     * @param string $prompt English description of the desired image
     * @return array|null ['data' => binary string, 'mime_type' => 'image/jpeg'] or null on failure
     */
    public function generateImage(string $prompt): ?array
    {
        if (!$this->isConfigured()) {
            Logger::debug('GoogleImageClient: gemini_api_key not configured');
            return null;
        }

        $url = self::API_BASE . urlencode($this->model) . ':predict?key=' . urlencode($this->apiKey);

        $payload = json_encode([
            'instances'  => [['prompt' => $prompt]],
            'parameters' => [
                'sampleCount' => 1,
                'aspectRatio' => '16:9',
            ],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200 || !$response) {
            Logger::warning('GoogleImageClient: generation failed', [
                'http_code' => $httpCode,
                'curl_err'  => $curlErr,
                'response'  => $response ? substr($response, 0, 300) : '',
            ]);
            return null;
        }

        $data       = json_decode($response, true);
        $prediction = $data['predictions'][0] ?? null;

        if (!$prediction || empty($prediction['bytesBase64Encoded'])) {
            Logger::warning('GoogleImageClient: empty prediction', [
                'response_keys' => array_keys($data ?? []),
            ]);
            return null;
        }

        return [
            'data'      => base64_decode($prediction['bytesBase64Encoded']),
            'mime_type' => $prediction['mimeType'] ?? 'image/jpeg',
        ];
    }

    /**
     * Build an image generation prompt from article image metadata.
     *
     * @param array|null $imageMeta Decoded image_meta from article_version
     * @param string     $fallbackTitle Article title as last resort
     */
    public static function buildPrompt(?array $imageMeta, string $fallbackTitle = ''): string
    {
        if (!empty($imageMeta)) {
            $parts = [];

            // Scene description
            if (!empty($imageMeta['event_title'])) {
                $parts[] = $imageMeta['event_title'];
            }

            // Category context
            if (!empty($imageMeta['category'])) {
                $parts[] = $imageMeta['category'] . ' news';
            }

            // Scene type
            $sceneMap = [
                'portrait'      => 'close-up portrait',
                'group'         => 'group of people',
                'outdoor_crowd' => 'outdoor crowd scene',
                'indoor'        => 'indoor scene',
                'aerial'        => 'aerial view',
                'object'        => 'object close-up',
                'abstract'      => 'abstract conceptual image',
            ];
            if (!empty($imageMeta['scene_type']) && isset($sceneMap[$imageMeta['scene_type']])) {
                $parts[] = $sceneMap[$imageMeta['scene_type']];
            }

            // Emotion / mood
            $emotionMap = [
                'tense'        => 'dramatic tense atmosphere',
                'dramatic'     => 'dramatic lighting',
                'celebratory'  => 'celebratory joyful mood',
                'positive'     => 'positive uplifting mood',
                'neutral'      => '',
            ];
            $emotion = $imageMeta['emotion'] ?? 'neutral';
            if (!empty($emotionMap[$emotion])) {
                $parts[] = $emotionMap[$emotion];
            }

            if (!empty($parts)) {
                $prompt = implode(', ', $parts);
                $prompt .= '. Photorealistic, high quality, news photography style, no text or watermarks.';
                return $prompt;
            }
        }

        // Fallback: use article title
        if (!empty($fallbackTitle)) {
            $title = mb_substr($fallbackTitle, 0, 100);
            return "News photo about: {$title}. Photorealistic, high quality, news photography style, no text or watermarks.";
        }

        return 'Generic news photograph, photorealistic, high quality, no text or watermarks.';
    }
}
