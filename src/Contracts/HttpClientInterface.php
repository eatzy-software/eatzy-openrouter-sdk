<?php

declare(strict_types=1);

namespace OpenRouterSDK\Contracts;

/**
 * HTTP Client Interface for making API requests
 */
interface HttpClientInterface
{
    /**
     * Make HTTP request and return decoded JSON response
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $uri Endpoint URI
     * @param array $options Request options
     * @return array Decoded JSON response
     * @throws \OpenRouterSDK\Exceptions\HttpException on HTTP errors
     * @throws \OpenRouterSDK\Exceptions\OpenRouterException on other errors
     */
    public function request(string $method, string $uri, array $options = []): array;

    /**
     * Handle Server-Sent Events streaming
     *
     * @param string $uri Endpoint URI
     * @param array $body Request body
     * @param callable $onChunk Callback for each SSE chunk
     * @param callable|null $onComplete Optional completion callback
     * @throws \OpenRouterSDK\Exceptions\HttpException on HTTP errors
     * @throws \OpenRouterSDK\Exceptions\OpenRouterException on other errors
     */
    public function stream(
        string $uri,
        array $body,
        callable $onChunk,
        ?callable $onComplete = null
    ): void;
}