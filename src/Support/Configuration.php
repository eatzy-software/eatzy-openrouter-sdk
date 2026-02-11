<?php

declare(strict_types=1);

namespace OpenRouterSDK\Support;

use OpenRouterSDK\Contracts\ConfigurationInterface;
use OpenRouterSDK\Exceptions\ValidationException;

/**
 * Configuration implementation for OpenRouter SDK
 */
class Configuration implements ConfigurationInterface
{
    private array $config;

    /**
     * Create configuration with optional overrides
     *
     * @param array $config Configuration array with optional overrides
     */
    public function __construct(array $config = [])
    {
        // Sanitize and validate configuration
        $sanitizedConfig = ConfigurationValidator::sanitize($config);
        ConfigurationValidator::validate($sanitizedConfig);
        
        $this->config = array_merge($this->getDefaultConfig(), $sanitizedConfig);
    }

    /**
     * Get API key
     */
    public function getApiKey(): string
    {
        return $this->config['api_key'];
    }

    /**
     * Get base URL for API requests
     */
    public function getBaseUrl(): string
    {
        return rtrim($this->config['base_url'], '/');
    }

    /**
     * Get request timeout in seconds
     */
    public function getTimeout(): int
    {
        return $this->config['timeout'];
    }

    /**
     * Get default headers for requests
     */
    public function getDefaultHeaders(): array
    {
        return array_filter([
            'Authorization' => 'Bearer ' . $this->getApiKey(),
            'Content-Type' => 'application/json',
            'HTTP-Referer' => $this->config['headers']['HTTP-Referer'] ?? '',
            'X-Title' => $this->config['headers']['X-Title'] ?? '',
        ]);
    }

    /**
     * Get default model name
     */
    public function getDefaultModel(): ?string
    {
        return $this->config['default_model'] ?: null;
    }

    /**
     * Get raw configuration array
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Get default configuration values
     */
    private function getDefaultConfig(): array
    {
        return [
            'api_key' => '',
            'base_url' => 'https://openrouter.ai/api/v1',
            'timeout' => 30,
            'default_model' => null,
            'headers' => [
                'HTTP-Referer' => '',
                'X-Title' => '',
            ],
        ];
    }
}