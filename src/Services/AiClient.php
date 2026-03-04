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
        $default = match ($operation) {
            'validate', 'deduplicate' => 'anthropic/claude-haiku-4-5-20251001',
            default => 'anthropic/claude-sonnet-4-20250514',
        };

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
