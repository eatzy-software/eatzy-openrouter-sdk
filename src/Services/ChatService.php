<?php

declare(strict_types=1);

namespace OpenRouterSDK\Services;

use OpenRouterSDK\Contracts\ChatServiceInterface;
use OpenRouterSDK\Contracts\HttpClientInterface;
use OpenRouterSDK\Contracts\ConfigurationInterface;
use OpenRouterSDK\Models\Chat\ChatCompletionRequest;
use OpenRouterSDK\Models\Chat\ChatCompletionResponse;
use OpenRouterSDK\Models\Chat\ChatMessage;
use OpenRouterSDK\Models\Chat\ChatChoice;
use OpenRouterSDK\Models\Chat\ChatUsage;

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

        return $this->transformResponse($response);
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

    /**
     * Transform API response to ChatCompletionResponse object
     */
    private function transformResponse(array $data): ChatCompletionResponse
    {
        $choices = array_map(function (array $choiceData) {
            $messageData = $choiceData['message'];
            $message = new ChatMessage(
                $messageData['role'],
                $messageData['content'],
                $messageData['name'] ?? null,
                $messageData['tool_call_id'] ?? null
            );

            return new ChatChoice(
                $choiceData['index'],
                $message,
                $choiceData['finish_reason'] ?? null,
                $choiceData['logprobs'] ?? null
            );
        }, $data['choices']);

        $usage = null;
        if (isset($data['usage'])) {
            $usageData = $data['usage'];
            $usage = new ChatUsage(
                $usageData['prompt_tokens'],
                $usageData['completion_tokens'],
                $usageData['total_tokens']
            );
        }

        return new ChatCompletionResponse(
            $data['id'],
            $data['object'],
            $data['created'],
            $data['model'],
            $choices,
            $usage,
            $data['system_fingerprint'] ?? null
        );
    }
}