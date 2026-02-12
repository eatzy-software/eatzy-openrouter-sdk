<?php

declare(strict_types=1);

namespace OpenRouterSDK\Services;

use OpenRouterSDK\Contracts\ChatServiceInterface;
use OpenRouterSDK\Contracts\HttpClientInterface;
use OpenRouterSDK\Contracts\ConfigurationInterface;
use OpenRouterSDK\DTOs\Chat\ChatCompletionRequest;
use OpenRouterSDK\DTOs\Chat\ChatCompletionResponse;
use OpenRouterSDK\DTOs\Chat\ChatMessage;

/**
 * Chat service implementation for OpenRouter API
 */
class ChatService implements ChatServiceInterface
{
    private HttpClientInterface $httpClient;
    private ConfigurationInterface $config;

    /**
     * Create chat service with HTTP client and configuration
     */
    public function __construct(
        HttpClientInterface $httpClient,
        ConfigurationInterface $config
    ) {
        $this->httpClient = $httpClient;
        $this->config = $config;
    }

    /**
     * Create a chat completion
     */
    public function create(ChatCompletionRequest $request): ChatCompletionResponse
    {
        $endpoint = $this->config->getBaseUrl() . '/chat/completions';
        $data = $request->toArray();

        // Set default model if not provided
        if ($request->model === null && $this->config->getDefaultModel()) {
            $data['model'] = $this->config->getDefaultModel();
        }

        $response = $this->httpClient->request('POST', $endpoint, [
            'json' => $data,
        ]);

        return ChatCompletionResponse::map($response);
    }

    /**
     * Stream chat completion responses
     */
    public function stream(ChatCompletionRequest $request, callable $onChunk): void
    {
        $endpoint = $this->config->getBaseUrl() . '/chat/completions';
        $data = array_merge($request->toArray(), ['stream' => true]);

        $this->httpClient->stream($endpoint, $data, $onChunk);
    }

    /**
     * Simple chat completion helper
     */
    public function simpleChat(string $prompt, ?string $model = null): string
    {
        $request = new ChatCompletionRequest(
            messages: [ChatMessage::user($prompt)],
            model: $model
        );

        $response = $this->create($request);
        return $response->getContent();
    }

}