<?php

declare(strict_types=1);

use OpenRouterSDK\Services\ChatService;
use OpenRouterSDK\Support\Configuration;
use OpenRouterSDK\Http\Client\GuzzleHttpClient;
use OpenRouterSDK\DTOs\Chat\ChatMessage;
use OpenRouterSDK\DTOs\Chat\ChatCompletionRequest;
use OpenRouterSDK\DTOs\Chat\ResponseFormat;
use OpenRouterSDK\Exceptions\HttpException;
use OpenRouterSDK\Exceptions\ValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenRouterSDK\DTOs\Chat\ChatCompletionResponse;

beforeEach(function () {
    // Create mock handler for testing
    $this->mockHandler = new MockHandler();
    $handlerStack = HandlerStack::create($this->mockHandler);
    $this->mockClient = new Client(['handler' => $handlerStack]);
    
    $this->config = new Configuration([
        'api_key' => 'sk-or-test12345678901234567890123456789012',
        'default_model' => 'test/model'
    ]);
    
    $this->httpClient = new GuzzleHttpClient($this->mockClient, $this->config);
    $this->chatService = new ChatService($this->httpClient, $this->config);
});

it('handles successful chat completion with mock response', function () {
    // Mock successful response
    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'id' => 'chatcmpl-test123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'test/model',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello! How can I help you today?'
                    ],
                    'finish_reason' => 'stop'
                ]
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 8,
                'total_tokens' => 18
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Hello!')],
        model: 'test/model'
    );

    $response = $this->chatService->create($request);

    expect($response)->toBeInstanceOf(ChatCompletionResponse::class);
    expect($response->id)->toBe('chatcmpl-test123');
    expect($response->model)->toBe('test/model');
    expect($response->getContent())->toBe('Hello! How can I help you today?');
    expect($response->getUsage()->total_tokens)->toBe(18);
});

it('handles streaming response with mock data', function () {
    // Mock streaming response chunks
    $chunks = [
        "data: " . json_encode(['choices' => [['delta' => ['content' => 'Hello']]]]) . "\n\n",
        "data: " . json_encode(['choices' => [['delta' => ['content' => ' world']]]]) . "\n\n",
        "data: " . json_encode(['choices' => [['delta' => ['content' => '!']]]]) . "\n\n",
        "data: [DONE]\n\n"
    ];

    $streamBody = implode('', $chunks);
    $this->mockHandler->append(new Response(200, ['Content-Type' => 'text/event-stream'], $streamBody));

    $capturedContent = '';
    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Say hello')],
        model: 'test/model'
    );

    $this->chatService->stream($request, function ($chunk) use (&$capturedContent) {
        if (isset($chunk['choices'][0]['delta']['content'])) {
            $capturedContent .= $chunk['choices'][0]['delta']['content'];
        }
    });

    expect($capturedContent)->toBe('Hello world!');
});

it('handles validation errors with detailed error information', function () {
    // Mock validation error response
    $this->mockHandler->append(
        new Response(400, [], json_encode([
            'error' => [
                'message' => 'Invalid request',
                'type' => 'validation_error',
                'param' => 'messages',
                'code' => 'missing_required_field'
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [], // Empty messages - should trigger validation
        model: 'test/model'
    );

    $exception = expect(fn() => $this->chatService->create($request))
        ->toThrow(HttpException::class);

    expect($exception->getStatusCode())->toBe(400);
    expect($exception->getMessage())->toContain('Invalid request');
});

it('handles rate limiting with proper error information', function () {
    // Mock rate limit response
    $this->mockHandler->append(
        new Response(429, [
            'X-RateLimit-Limit' => '1000',
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (time() + 60)
        ], json_encode([
            'error' => [
                'message' => 'Rate limit exceeded',
                'type' => 'rate_limit_exceeded'
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Test message')],
        model: 'test/model'
    );

    $exception = expect(fn() => $this->chatService->create($request))
        ->toThrow(HttpException::class);

    expect($exception->getStatusCode())->toBe(429);
    expect($exception->getMessage())->toContain('Rate limit exceeded');
});

it('handles authentication errors properly', function () {
    // Mock authentication error
    $this->mockHandler->append(
        new Response(401, [], json_encode([
            'error' => [
                'message' => 'Invalid API key',
                'type' => 'authentication_error'
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Test')],
        model: 'test/model'
    );

    $exception = expect(fn() => $this->chatService->create($request))
        ->toThrow(HttpException::class);

    expect($exception->getStatusCode())->toBe(401);
    expect($exception->getMessage())->toContain('Invalid API key');
});

it('handles model not found errors', function () {
    // Mock model not found error
    $this->mockHandler->append(
        new Response(404, [], json_encode([
            'error' => [
                'message' => 'The model `nonexistent/model` does not exist',
                'type' => 'invalid_request_error'
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Test')],
        model: 'nonexistent/model'
    );

    $exception = expect(fn() => $this->chatService->create($request))
        ->toThrow(HttpException::class);

    expect($exception->getStatusCode())->toBe(404);
    expect($exception->getMessage())->toContain('does not exist');
});

it('handles server errors gracefully', function () {
    // Mock server error
    $this->mockHandler->append(
        new Response(500, [], json_encode([
            'error' => [
                'message' => 'Internal server error',
                'type' => 'server_error'
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Test')],
        model: 'test/model'
    );

    $exception = expect(fn() => $this->chatService->create($request))
        ->toThrow(HttpException::class);

    expect($exception->getStatusCode())->toBe(500);
    expect($exception->getMessage())->toContain('Internal server error');
});

it('handles network timeouts properly', function () {
    // Mock timeout error
    $this->mockHandler->append(
        new GuzzleHttp\Exception\ConnectException(
            'Operation timed out',
            new GuzzleHttp\Psr7\Request('POST', '/chat/completions')
        )
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Test')],
        model: 'test/model'
    );

    expect(fn() => $this->chatService->create($request))
        ->toThrow(Exception::class);
});

it('processes structured JSON responses correctly', function () {
    // Mock JSON structured response
    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'id' => 'chatcmpl-json123',
            'object' => 'chat.completion',
            'choices' => [
                [
                    'message' => [
                        'content' => '{"name":"John Doe","age":30,"email":"john@example.com"}'
                    ]
                ]
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Generate user data')],
        model: 'test/model',
        response_format: new ResponseFormat('json_object')
    );

    $response = $this->chatService->create($request);
    $content = $response->getContent();
    
    expect($content)->toBeJson();
    
    $parsed = json_decode($content, true);
    expect($parsed)->toBeArray();
    expect($parsed)->toHaveKeys(['name', 'age', 'email']);
});

it('handles multi-modal content requests', function () {
    // Mock response for multi-modal request
    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'id' => 'chatcmpl-multimodal123',
            'choices' => [
                [
                    'message' => [
                        'content' => 'I can see an image showing a beautiful landscape with mountains and a lake.'
                    ]
                ]
            ]
        ]))
    );

    $message = new ChatMessage('user', [
        ['type' => 'text', 'text' => 'What do you see in this image?'],
        [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'https://example.com/image.jpg',
                'detail' => 'high'
            ]
        ]
    ]);

    $request = new ChatCompletionRequest(
        messages: [$message],
        model: 'test/vision-model'
    );

    $response = $this->chatService->create($request);
    
    expect($response->getContent())->toContain('image');
    expect($response->getId())->toBe('chatcmpl-multimodal123');
});

it('respects custom timeout settings', function () {
    $customConfig = new Configuration([
        'api_key' => 'sk-or-test12345678901234567890123456789012',
        'timeout' => 5, // 5 seconds
        'default_model' => 'test/model'
    ]);

    $httpClient = new GuzzleHttpClient($this->mockClient, $customConfig);
    $service = new ChatService($httpClient, $customConfig);

    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'choices' => [['message' => ['content' => 'Quick response']]]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Fast request')],
        model: 'test/model'
    );

    $response = $service->create($request);
    expect($response->getContent())->toBe('Quick response');
});

it('handles edge cases with empty or malformed responses', function () {
    // Mock empty response
    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => ''
                    ]
                ]
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Empty response test')],
        model: 'test/model'
    );

    $response = $this->chatService->create($request);
    expect($response->getContent())->toBe('');
});