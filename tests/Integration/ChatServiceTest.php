<?php

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery as m;
use OpenRouterSDK\Contracts\ConfigurationInterface;
use OpenRouterSDK\Contracts\HttpClientInterface;
use OpenRouterSDK\Http\Client\GuzzleHttpClient;
use OpenRouterSDK\DTOs\Chat\ChatCompletionRequest;
use OpenRouterSDK\DTOs\Chat\ChatMessage;
use OpenRouterSDK\DTOs\Chat\ResponseFormat;
use OpenRouterSDK\Services\ChatService;
use OpenRouterSDK\Support\Configuration;

beforeEach(function () {
    // Clear mocks between tests
    m::close();
});

afterEach(function () {
    m::close();
});

// Mock data generators
function createMockChatResponse(array $overrides = []): array
{
    return array_merge([
        'id' => 'chatcmpl-123',
        'object' => 'chat.completion',
        'created' => time(),
        'model' => 'openai/gpt-4',
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Hello! How can I help you today?',
                ],
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
        ],
    ], $overrides);
}

function createMockErrorResponse(int $statusCode = 400): array
{
    return [
        'error' => [
            'message' => 'Invalid API key',
            'type' => 'authentication_error',
            'param' => null,
            'code' => 'invalid_api_key',
        ],
    ];
}

it('creates chat completion successfully', function () {
    // Arrange
    $config = m::mock(ConfigurationInterface::class);
    $config->shouldReceive('getBaseUrl')->andReturn('https://openrouter.ai/api/v1');
    $config->shouldReceive('getDefaultModel')->andReturn('openai/gpt-4');
    $config->shouldReceive('getTimeout')->andReturn(30);
    $config->shouldReceive('getDefaultHeaders')->andReturn([
        'Authorization' => 'Bearer test-key',
        'Content-Type' => 'application/json',
    ]);

    $httpClient = m::mock(HttpClientInterface::class);
    $httpClient->shouldReceive('request')
        ->with('POST', 'https://openrouter.ai/api/v1/chat/completions', m::any())
        ->andReturn(createMockChatResponse());

    $service = new ChatService($httpClient, $config);
    $request = new ChatCompletionRequest(
        messages: [ChatMessage::user('Hello!')],
        model: 'openai/gpt-4'
    );

    // Act
    $response = $service->create($request);

    // Assert
    expect($response)->toBeInstanceOf(\OpenRouterSDK\Models\Chat\ChatCompletionResponse::class);
    expect($response->id)->toBe('chatcmpl-123');
    expect($response->model)->toBe('openai/gpt-4');
    expect($response->object)->toBe('chat.completion');
    expect($response->choices)->toHaveCount(1);
    expect($response->getContent())->toBe('Hello! How can I help you today?');
});

it('uses default model when not provided in request', function () {
    // Arrange
    $config = m::mock(ConfigurationInterface::class);
    $config->shouldReceive('getBaseUrl')->andReturn('https://openrouter.ai/api/v1');
    $config->shouldReceive('getDefaultModel')->andReturn('openai/gpt-4-turbo');
    $config->shouldReceive('getTimeout')->andReturn(30);
    $config->shouldReceive('getDefaultHeaders')->andReturn([
        'Authorization' => 'Bearer test-key',
        'Content-Type' => 'application/json',
    ]);

    $httpClient = m::mock(HttpClientInterface::class);
    $httpClient->shouldReceive('request')
        ->with('POST', 'https://openrouter.ai/api/v1/chat/completions', m::subset([
            'json' => m::subset(['model' => 'openai/gpt-4-turbo'])
        ]))
        ->andReturn(createMockChatResponse());

    $service = new ChatService($httpClient, $config);
    $request = new ChatCompletionRequest(
        messages: [ChatMessage::user('Hello!')]
        // No model specified - should use default
    );

    // Act
    $response = $service->create($request);

    // Assert
    expect($response->model)->toBe('openai/gpt-4'); // From mock response
});

it('handles API errors gracefully', function () {
    // Arrange
    $config = m::mock(ConfigurationInterface::class);
    $config->shouldReceive('getBaseUrl')->andReturn('https://openrouter.ai/api/v1');
    $config->shouldReceive('getDefaultModel')->andReturn(null);
    $config->shouldReceive('getTimeout')->andReturn(30);
    $config->shouldReceive('getDefaultHeaders')->andReturn([
        'Authorization' => 'Bearer invalid-key',
        'Content-Type' => 'application/json',
    ]);

    $httpClient = m::mock(HttpClientInterface::class);
    $httpClient->shouldReceive('request')
        ->andThrow(new \OpenRouterSDK\Exceptions\HttpException(
            'HTTP 401: Unauthorized',
            new Response(401, [], json_encode(createMockErrorResponse(401)))
        ));

    $service = new ChatService($httpClient, $config);
    $request = new ChatCompletionRequest(
        messages: [ChatMessage::user('Hello!')],
        model: 'openai/gpt-4'
    );

    // Act & Assert
    expect(fn() => $service->create($request))
        ->toThrow(\OpenRouterSDK\Exceptions\HttpException::class, 'HTTP 401: Unauthorized');
});

it('streams chat completion responses', function () {
    // Arrange
    $config = m::mock(ConfigurationInterface::class);
    $config->shouldReceive('getBaseUrl')->andReturn('https://openrouter.ai/api/v1');
    $config->shouldReceive('getDefaultModel')->andReturn(null);
    $config->shouldReceive('getTimeout')->andReturn(30);
    $config->shouldReceive('getDefaultHeaders')->andReturn([
        'Authorization' => 'Bearer test-key',
        'Content-Type' => 'application/json',
    ]);

    $chunksReceived = [];
    $onChunk = function ($chunk) use (&$chunksReceived) {
        $chunksReceived[] = $chunk;
    };

    $httpClient = m::mock(HttpClientInterface::class);
    $httpClient->shouldReceive('stream')
        ->with(
            'https://openrouter.ai/api/v1/chat/completions',
            m::subset(['stream' => true]),
            m::type('callable'),
            null
        )
        ->andReturnUsing(function ($uri, $body, $callback) {
            // Simulate streaming chunks
            $chunks = [
                ['choices' => [['delta' => ['content' => 'Hello']]]],
                ['choices' => [['delta' => ['content' => ' world']]]],
                ['choices' => [['delta' => ['content' => '!']]]],
            ];
            
            foreach ($chunks as $chunk) {
                $callback($chunk);
            }
        });

    $service = new ChatService($httpClient, $config);
    $request = new ChatCompletionRequest(
        messages: [ChatMessage::user('Say hello')],
        model: 'openai/gpt-4',
        stream: true
    );

    // Act
    $service->stream($request, $onChunk);

    // Assert
    expect($chunksReceived)->toHaveCount(3);
});

it('performs simple chat completion', function () {
    // Arrange
    $config = m::mock(ConfigurationInterface::class);
    $config->shouldReceive('getBaseUrl')->andReturn('https://openrouter.ai/api/v1');
    $config->shouldReceive('getDefaultModel')->andReturn('openai/gpt-4');
    $config->shouldReceive('getTimeout')->andReturn(30);
    $config->shouldReceive('getDefaultHeaders')->andReturn([
        'Authorization' => 'Bearer test-key',
        'Content-Type' => 'application/json',
    ]);

    $httpClient = m::mock(HttpClientInterface::class);
    $httpClient->shouldReceive('request')
        ->andReturn(createMockChatResponse([
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'The capital of France is Paris.',
                ],
                'finish_reason' => 'stop',
            ]]
        ]));

    $service = new ChatService($httpClient, $config);

    // Act
    $result = $service->simpleChat('What is the capital of France?');

    // Assert
    expect($result)->toBeString();
    expect($result)->toBe('The capital of France is Paris.');
});

it('handles structured output with response format', function () {
    // Arrange
    $config = m::mock(ConfigurationInterface::class);
    $config->shouldReceive('getBaseUrl')->andReturn('https://openrouter.ai/api/v1');
    $config->shouldReceive('getDefaultModel')->andReturn('openai/gpt-4');
    $config->shouldReceive('getTimeout')->andReturn(30);
    $config->shouldReceive('getDefaultHeaders')->andReturn([
        'Authorization' => 'Bearer test-key',
        'Content-Type' => 'application/json',
    ]);

    $structuredResponse = createMockChatResponse([
        'choices' => [[
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => '{"name": "John Doe", "age": 30}',
            ],
            'finish_reason' => 'stop',
        ]]
    ]);

    $httpClient = m::mock(HttpClientInterface::class);
    $httpClient->shouldReceive('request')
        ->with('POST', 'https://openrouter.ai/api/v1/chat/completions', m::subset([
            'json' => m::subset([
                'response_format' => ['type' => 'json_object']
            ])
        ]))
        ->andReturn($structuredResponse);

    $service = new ChatService($httpClient, $config);
    
    $responseFormat = new ResponseFormat('json_object');
    $request = new ChatCompletionRequest(
        messages: [ChatMessage::user('Give me a person\'s info in JSON')],
        model: 'openai/gpt-4',
        response_format: $responseFormat
    );

    // Act
    $response = $service->create($request);

    // Assert
    expect($response->getContent())->toBe('{"name": "John Doe", "age": 30}');
    expect(json_decode($response->getContent()))->toBeObject();
});

it('respects temperature and max_tokens parameters', function () {
    // Arrange
    $config = m::mock(ConfigurationInterface::class);
    $config->shouldReceive('getBaseUrl')->andReturn('https://openrouter.ai/api/v1');
    $config->shouldReceive('getDefaultModel')->andReturn(null);
    $config->shouldReceive('getTimeout')->andReturn(30);
    $config->shouldReceive('getDefaultHeaders')->andReturn([
        'Authorization' => 'Bearer test-key',
        'Content-Type' => 'application/json',
    ]);

    $httpClient = m::mock(HttpClientInterface::class);
    $httpClient->shouldReceive('request')
        ->with('POST', 'https://openrouter.ai/api/v1/chat/completions', m::subset([
            'json' => m::subset([
                'temperature' => 0.7,
                'max_tokens' => 150
            ])
        ]))
        ->andReturn(createMockChatResponse());

    $service = new ChatService($httpClient, $config);
    $request = new ChatCompletionRequest(
        messages: [ChatMessage::user('Write a short story')],
        model: 'openai/gpt-4',
        temperature: 0.7,
        max_tokens: 150
    );

    // Act
    $response = $service->create($request);

    // Assert
    expect($response)->toBeInstanceOf(\OpenRouterSDK\Models\Chat\ChatCompletionResponse::class);
});

it('handles multiple messages in conversation', function () {
    // Arrange
    $config = m::mock(ConfigurationInterface::class);
    $config->shouldReceive('getBaseUrl')->andReturn('https://openrouter.ai/api/v1');
    $config->shouldReceive('getDefaultModel')->andReturn('openai/gpt-4');
    $config->shouldReceive('getTimeout')->andReturn(30);
    $config->shouldReceive('getDefaultHeaders')->andReturn([
        'Authorization' => 'Bearer test-key',
        'Content-Type' => 'application/json',
    ]);

    $httpClient = m::mock(HttpClientInterface::class);
    $httpClient->shouldReceive('request')
        ->andReturn(createMockChatResponse([
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Yes, the weather in London is typically rainy and cool.',
                ],
                'finish_reason' => 'stop',
            ]]
        ]));

    $service = new ChatService($httpClient, $config);
    
    $messages = [
        ChatMessage::system('You are a helpful weather assistant.'),
        ChatMessage::user('What is the weather like in London?'),
    ];
    
    $request = new ChatCompletionRequest(messages: $messages, model: 'openai/gpt-4');

    // Act
    $response = $service->create($request);

    // Assert
    expect($response->choices)->toHaveCount(1);
    expect($response->getContent())->toContain('London');
});

it('validates request parameters', function () {
    // Arrange
    $config = m::mock(ConfigurationInterface::class);
    $config->shouldReceive('getBaseUrl')->andReturn('https://openrouter.ai/api/v1');
    $config->shouldReceive('getDefaultModel')->andReturn('openai/gpt-4');
    $config->shouldReceive('getTimeout')->andReturn(30);
    $config->shouldReceive('getDefaultHeaders')->andReturn([
        'Authorization' => 'Bearer test-key',
        'Content-Type' => 'application/json',
    ]);

    $httpClient = m::mock(HttpClientInterface::class);

    $service = new ChatService($httpClient, $config);

    // Act & Assert - Empty messages should throw validation exception
    expect(fn() => new ChatCompletionRequest(messages: []))
        ->toThrow(\InvalidArgumentException::class, 'messages cannot be empty');

    // Invalid temperature should throw validation exception
    expect(fn() => new ChatCompletionRequest(
        messages: [ChatMessage::user('Hello')],
        temperature: 3.0 // Above valid range of 0-2
    ))->toThrow(\InvalidArgumentException::class, 'temperature must be between 0 and 2');

    // Invalid max_tokens should throw validation exception
    expect(fn() => new ChatCompletionRequest(
        messages: [ChatMessage::user('Hello')],
        max_tokens: 0 // Below minimum of 1
    ))->toThrow(\InvalidArgumentException::class, 'max_tokens must be between 1 and 9223372036854775807');
});
