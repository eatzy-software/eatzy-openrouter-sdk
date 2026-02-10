<?php

declare(strict_types=1);

namespace OpenRouterSDK\Contracts;

/**
 * Configuration Interface for SDK configuration
 */
interface ConfigurationInterface
{
    /**
     * Get API key
     */
    public function getApiKey(): string;

    /**
     * Get base URL for API requests
     */
    public function getBaseUrl(): string;

    /**
     * Get request timeout in seconds
     */
    public function getTimeout(): int;

    /**
     * Get default headers for requests
     */
    public function getDefaultHeaders(): array;

    /**
     * Get default model name
     */
    public function getDefaultModel(): ?string;
}