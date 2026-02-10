<?php

declare(strict_types=1);

namespace OpenRouterSDK\Contracts;

use OpenRouterSDK\Models\Chat\ChatCompletionRequest;
use OpenRouterSDK\Models\Chat\ChatCompletionResponse;

/**
 * Chat Service Interface for chat completion functionality
 */
interface ChatServiceInterface
{
    /**
     * Create a chat completion
     *
     * @param ChatCompletionRequest $request Chat completion request
     * @return ChatCompletionResponse Chat completion response
     * @throws \OpenRouterSDK\Exceptions\HttpException on HTTP errors
     * @throws \OpenRouterSDK\Exceptions\ValidationException on validation errors
     */
    public function create(ChatCompletionRequest $request): ChatCompletionResponse;

    /**
     * Stream chat completion responses
     *
     * @param ChatCompletionRequest $request Chat completion request
     * @param callable $onChunk Callback function to handle each chunk
     * @throws \OpenRouterSDK\Exceptions\HttpException on HTTP errors
     * @throws \OpenRouterSDK\Exceptions\ValidationException on validation errors
     */
    public function stream(ChatCompletionRequest $request, callable $onChunk): void;

    /**
     * Simple chat completion helper
     *
     * @param string $prompt User prompt
     * @param string|null $model Model to use (uses default if null)
     * @return string Response content
     * @throws \OpenRouterSDK\Exceptions\HttpException on HTTP errors
     */
    public function simpleChat(string $prompt, ?string $model = null): string;
}