<?php declare(strict_types=1);

namespace NewsBot\Services;

use NewsBot\Core\Settings;

/**
 * AI Client factory.
 * Creates appropriate AI client based on settings.
 */
class AiClient
{
    /**
     * Create AI client based on settings.
     *
     * @param string $operation Operation type (process, validate, deduplicate)
     * @return AiClientInterface
     */
    public static function create(string $operation = 'process'): AiClientInterface
    {
        $provider = Settings::get('ai_provider', 'openrouter');

        return match ($provider) {
            'anthropic' => new AnthropicClient(),
            'gemini' => new GeminiClient(),
            default => new OpenRouterClient(),
        };
    }

    /**
     * Get model name for operation.
     *
     * @param string $operation 'process', 'validate', 'deduplicate'
     * @param bool $useFallback Use fallback model
     * @return string Model identifier
     */
    public static function getModel(string $operation, bool $useFallback = false): string
    {
        $key = $useFallback ? "model_{$operation}_fallback" : "model_{$operation}";
        
        $provider = Settings::get('ai_provider', 'openrouter');
        
        // Return appropriate default models based on provider if not found in db
        $providerConfigs = [
            'anthropic' => [
                'validate' => 'anthropic/claude-haiku-4-5-20251001',
                'deduplicate' => 'anthropic/claude-haiku-4-5-20251001',
                'default' => 'anthropic/claude-sonnet-4-20250514'
            ],
            'gemini' => [
                'validate' => 'gemini-2.5-flash',
                'deduplicate' => 'gemini-2.5-flash',
                'default' => 'gemini-2.5-flash'
            ],
            'openrouter' => [
                'validate' => 'anthropic/claude-haiku-4-5-20251001',
                'deduplicate' => 'anthropic/claude-haiku-4-5-20251001',
                'default' => 'anthropic/claude-sonnet-4-20250514'
            ],
        ];

        $default = $providerConfigs[$provider][$operation] ?? current($providerConfigs[$provider]) ?? $providerConfigs['openrouter']['default'];

        return Settings::get($key, $default);
    }

    /**
     * Get temperature for operation.
     */
    public static function getTemperature(string $operation): float
    {
        return match ($operation) {
            'deduplicate' => Settings::getFloat('temperature_deduplicate', 0.1),
            'validate' => Settings::getFloat('temperature_validate', 0.3),
            default => Settings::getFloat('temperature_process', 0.4),
        };
    }
}
