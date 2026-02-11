<?php

declare(strict_types=1);

namespace OpenRouterSDK\Http\Client;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Utils;
use OpenRouterSDK\Contracts\HttpClientInterface;
use OpenRouterSDK\Contracts\ConfigurationInterface;
use OpenRouterSDK\Exceptions\HttpException;
use OpenRouterSDK\Http\Middleware\RetryMiddleware;
use OpenRouterSDK\Http\Streaming\SSEParser;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle HTTP Client implementation with middleware support
 */
class GuzzleHttpClient implements HttpClientInterface
{
    private GuzzleClientInterface $client;
    private ConfigurationInterface $config;
    private RetryMiddleware $retryMiddleware;

    /**
     * Create HTTP client with Guzzle client and configuration
     */
    public function __construct(GuzzleClientInterface $client, ConfigurationInterface $config)
    {
        $this->client = $client;
        $this->config = $config;
        $this->retryMiddleware = new RetryMiddleware();
    }

    /**
     * Make HTTP request and return decoded JSON response
     */
    public function request(string $method, string $uri, array $options = []): array
    {
        $handler = function (string $method, string $uri, array $options) {
            try {
                $response = $this->client->request($method, $uri, $this->prepareOptions($options));
                return $this->decodeResponse($response);
            } catch (RequestException $e) {
                throw new HttpException(
                    $this->getErrorMessage($e),
                    $e->getResponse() ?? Utils::streamFor(''),
                    $e->getCode(),
                    $e
                );
            }
        };

        return $this->retryMiddleware->handle($handler, $method, $uri, $options);
    }

    /**
     * Handle Server-Sent Events streaming
     */
    public function stream(
        string $uri,
        array $body,
        callable $onChunk,
        ?callable $onComplete = null
    ): void {
        $options = [
            'body' => json_encode($body),
            'headers' => array_merge(
                $this->config->getDefaultHeaders(),
                ['Accept' => 'text/event-stream']
            ),
            'stream' => true,
        ];

        try {
            $response = $this->client->post($uri, $options);
            $parser = new SSEParser();
            $parser->parseStream($response->getBody(), $onChunk, $onComplete);
        } catch (RequestException $e) {
            throw new HttpException(
                $this->getErrorMessage($e),
                $e->getResponse() ?? Utils::streamFor(''),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Prepare request options with default configuration
     */
    private function prepareOptions(array $options): array
    {
        $defaults = [
            'timeout' => $this->config->getTimeout(),
            'headers' => $this->config->getDefaultHeaders(),
        ];

        return array_merge_recursive($defaults, $options);
    }

    /**
     * Decode JSON response
     */
    private function decodeResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        
        if (empty($body)) {
            return [];
        }

        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(
                'Invalid JSON response: ' . json_last_error_msg(),
                $response
            );
        }

        return $decoded;
    }

    /**
     * Get error message from request exception
     */
    private function getErrorMessage(RequestException $exception): string
    {
        if ($exception->getResponse()) {
            $statusCode = $exception->getResponse()->getStatusCode();
            $reasonPhrase = $exception->getResponse()->getReasonPhrase();
            return "HTTP {$statusCode}: {$reasonPhrase}";
        }

        return $exception->getMessage();
    }
}