<?php

declare(strict_types=1);

use OpenRouterSDK\Services\ChatService;
use OpenRouterSDK\Support\Configuration;
use OpenRouterSDK\Http\Client\GuzzleHttpClient;
use OpenRouterSDK\DTOs\Chat\ChatMessage;
use OpenRouterSDK\DTOs\Chat\ChatCompletionRequest;
use OpenRouterSDK\DTOs\Chat\ResponseFormat;
use OpenRouterSDK\Exceptions\ValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

beforeEach(function () {
    $this->mockHandler = new MockHandler();
    $handlerStack = HandlerStack::create($this->mockHandler);
    $this->mockClient = new Client(['handler' => $handlerStack]);
    
    $this->config = new Configuration([
        'api_key' => 'sk-or-test12345678901234567890123456789012',
        'default_model' => 'edgecase/model'
    ]);
    
    $this->httpClient = new GuzzleHttpClient($this->mockClient, $this->config);
    $this->chatService = new ChatService($this->httpClient, $this->config);
});

it('handles extremely long prompt messages', function () {
    // Create a very long prompt (approaching token limits)
    $longPrompt = str_repeat('This is a very long prompt that tests the system\'s ability to handle large inputs. ', 1000);
    expect(strlen($longPrompt))->toBeGreaterThan(50000); // ~50KB

    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Processed long prompt successfully'
                    ]
                ]
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', $longPrompt)],
        model: 'edgecase/model'
    );

    $response = $this->chatService->create($request);
    expect($response->getContent())->toBe('Processed long prompt successfully');
});

it('handles special characters and unicode in messages', function () {
    $specialContent = "Hello 世界! 🌍 Special chars: àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ";
    
    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => $specialContent
                    ]
                ]
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', $specialContent)],
        model: 'edgecase/model'
    );

    $response = $this->chatService->create($request);
    expect($response->getContent())->toBe($specialContent);
});

it('handles empty array messages gracefully', function () {
    $this->mockHandler->append(
        new Response(400, [], json_encode([
            'error' => [
                'message' => 'Messages array cannot be empty',
                'type' => 'invalid_request_error'
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [],
        model: 'edgecase/model'
    );

    expect(fn() => $this->chatService->create($request))
        ->toThrow(\Eatzy\OpenRouter\Exceptions\HttpException::class);
});

it('handles null and empty string content', function () {
    // Test null content
    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => null
                    ]
                ]
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Test null response')],
        model: 'edgecase/model'
    );

    $response = $this->chatService->create($request);
    expect($response->getContent())->toBeNull();

    // Test empty string content
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
        messages: [new ChatMessage('user', 'Test empty response')],
        model: 'edgecase/model'
    );

    $response = $this->chatService->create($request);
    expect($response->getContent())->toBe('');
});

it('handles malformed JSON responses', function () {
    // Test invalid JSON response
    $this->mockHandler->append(
        new Response(200, [], '{ invalid json response }')
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Test malformed JSON')],
        model: 'edgecase/model'
    );

    expect(fn() => $this->chatService->create($request))
        ->toThrow(Exception::class);
});

it('handles extremely nested JSON structures', function () {
    // Create deeply nested structure
    $nestedData = [];
    $current = &$nestedData;
    for ($i = 0; $i < 50; $i++) {
        $current['level_' . $i] = [];
        $current = &$current['level_' . $i];
    }
    $current['value'] = 'deeply nested';

    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode($nestedData)
                    ]
                ]
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Test nested structure')],
        model: 'edgecase/model',
        response_format: new ResponseFormat('json_object')
    );

    $response = $this->chatService->create($request);
    $content = json_decode($response->getContent(), true);
    
    expect($content)->toBeArray();
    expect($content['level_0']['level_1']['level_2'])->toBeArray();
});

it('handles concurrent streaming requests', function () {
    // This test simulates concurrent streaming (sequential simulation)
    $streamResponses = [
        ['Hello', ' world', '!'],
        ['Testing', ' concurrent', ' streams'],
        ['Another', ' stream', ' here']
    ];

    foreach ($streamResponses as $responseChunks) {
        $chunks = [];
        foreach ($responseChunks as $chunk) {
            $chunks[] = "data: " . json_encode([
                'choices' => [['delta' => ['content' => $chunk]]]
            ]) . "\n\n";
        }
        $chunks[] = "data: [DONE]\n\n";
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'text/event-stream'], implode('', $chunks)));
    }

    $results = [];
    
    for ($i = 0; $i < 3; $i++) {
        $capturedContent = '';
        $request = new ChatCompletionRequest(
            messages: [new ChatMessage('user', "Stream test {$i}")],
            model: 'edgecase/model'
        );

        $this->chatService->stream($request, function ($chunk) use (&$capturedContent) {
            if (isset($chunk['choices'][0]['delta']['content'])) {
                $capturedContent .= $chunk['choices'][0]['delta']['content'];
            }
        });
        
        $results[] = $capturedContent;
    }

    expect($results[0])->toBe('Hello world!');
    expect($results[1])->toBe('Testing concurrent streams');
    expect($results[2])->toBe('Another stream here');
});

it('handles rate limit recovery scenarios', function () {
    // First request: rate limited
    $this->mockHandler->append(
        new Response(429, [
            'X-RateLimit-Reset' => time() + 2 // Reset in 2 seconds
        ], json_encode([
            'error' => ['message' => 'Rate limit exceeded']
        ]))
    );

    // Second request: successful after rate limit reset
    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Success after rate limit'
                    ]
                ]
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Rate limit test')],
        model: 'edgecase/model'
    );

    // First attempt should fail
    expect(fn() => $this->chatService->create($request))
        ->toThrow(\Eatzy\OpenRouter\Exceptions\HttpException::class);

    // Wait for rate limit reset (simulated)
    sleep(3);

    // Second attempt should succeed
    $response = $this->chatService->create($request);
    expect($response->getContent())->toBe('Success after rate limit');
});

it('handles network interruption scenarios', function () {
    // Simulate network interruption with exception
    $this->mockHandler->append(
        new GuzzleHttp\Exception\ConnectException(
            'Network connection was interrupted',
            new GuzzleHttp\Psr7\Request('POST', '/chat/completions')
        )
    );

    // Second request succeeds
    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Recovered from network error'
                    ]
                ]
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Network resilience test')],
        model: 'edgecase/model'
    );

    // First attempt fails
    expect(fn() => $this->chatService->create($request))
        ->toThrow(Exception::class);

    // Second attempt succeeds
    $response = $this->chatService->create($request);
    expect($response->getContent())->toBe('Recovered from network error');
});

it('handles invalid model names gracefully', function () {
    $this->mockHandler->append(
        new Response(404, [], json_encode([
            'error' => [
                'message' => 'The model `invalid/model@name` does not exist',
                'type' => 'invalid_request_error'
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Test')],
        model: 'invalid/model@name' // Invalid characters
    );

    expect(fn() => $this->chatService->create($request))
        ->toThrow(\Eatzy\OpenRouter\Exceptions\HttpException::class);
});

it('handles maximum token limit scenarios', function () {
    $this->mockHandler->append(
        new Response(400, [], json_encode([
            'error' => [
                'message' => 'Maximum tokens exceeded',
                'type' => 'invalid_request_error'
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Test')],
        model: 'edgecase/model',
        max_tokens: 1000000 // Excessive token limit
    );

    expect(fn() => $this->chatService->create($request))
        ->toThrow(\Eatzy\OpenRouter\Exceptions\HttpException::class);
});

it('handles mixed content types in messages', function () {
    $mixedContentMessage = new ChatMessage('user', [
        ['type' => 'text', 'text' => 'Analyze this:'],
        [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='
            ]
        ],
        ['type' => 'text', 'text' => 'And also consider this text']
    ]);

    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Analyzed mixed content successfully'
                    ]
                ]
            ]
        ]))
    );

    $request = new ChatCompletionRequest(
        messages: [$mixedContentMessage],
        model: 'edgecase/multimodal-model'
    );

    $response = $this->chatService->create($request);
    expect($response->getContent())->toBe('Analyzed mixed content successfully');
});