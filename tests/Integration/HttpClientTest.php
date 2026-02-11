<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OpenRouterSDK\Http\Client\GuzzleHttpClient;
use OpenRouterSDK\Support\Configuration;

beforeEach(function () {
    // Default configuration for tests
    $this->config = new Configuration([
        'api_key' => 'test-api-key',
        'base_url' => 'https://openrouter.ai/api/v1',
        'timeout' => 30,
        'headers' => [
            'HTTP-Referer' => 'https://example.com',
            'X-Title' => 'Test App',
        ],
    ]);
});

function createMockHandler(array $responses): HandlerStack
{
    $mock = new MockHandler($responses);
    return HandlerStack::create($mock);
}

it('makes successful HTTP requests', function () {
    // Arrange
    $mockResponses = [
        new Response(200, [], json_encode([
            'id' => 'test-123',
            'data' => 'success'
        ])),
    ];
    
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    // Act
    $result = $httpClient->request('GET', 'https://api.example.com/test');

    // Assert
    expect($result)->toBeArray();
    expect($result['id'])->toBe('test-123');
    expect($result['data'])->toBe('success');
});

it('handles JSON decoding errors', function () {
    // Arrange
    $mockResponses = [
        new Response(200, [], 'invalid json'),
    ];
    
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    // Act & Assert
    expect(fn() => $httpClient->request('GET', 'https://api.example.com/test'))
        ->toThrow(\OpenRouterSDK\Exceptions\HttpException::class, 'Invalid JSON response');
});

it('handles HTTP errors with proper exception wrapping', function () {
    // Arrange
    $mockResponses = [
        new RequestException(
            'Internal Server Error',
            new Request('POST', 'https://api.example.com/test'),
            new Response(500, [], json_encode(['error' => 'Server error']))
        ),
    ];
    
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    // Act & Assert
    expect(fn() => $httpClient->request('POST', 'https://api.example.com/test'))
        ->toThrow(\OpenRouterSDK\Exceptions\HttpException::class, 'HTTP 500: Internal Server Error');
});

it('includes proper headers in requests', function () {
    // Arrange
    $capturedRequest = null;
    $mockResponses = [
        function (Request $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200, [], json_encode(['success' => true]));
        },
    ];
    
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    // Act
    $httpClient->request('POST', 'https://api.example.com/test', [
        'json' => ['message' => 'hello']
    ]);

    // Assert
    expect($capturedRequest)->not()->toBeNull();
    expect($capturedRequest->getHeaderLine('Authorization'))->toBe('Bearer test-api-key');
    expect($capturedRequest->getHeaderLine('Content-Type'))->toBe('application/json');
    expect($capturedRequest->getHeaderLine('HTTP-Referer'))->toBe('https://example.com');
    expect($capturedRequest->getHeaderLine('X-Title'))->toBe('Test App');
});

it('applies timeout configuration', function () {
    // Arrange
    $capturedOptions = null;
    $mockResponses = [
        function (Request $request, array $options) use (&$capturedOptions) {
            $capturedOptions = $options;
            return new Response(200, [], json_encode(['success' => true]));
        },
    ];
    
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    // Act
    $httpClient->request('GET', 'https://api.example.com/test');

    // Assert
    expect($capturedOptions)->toHaveKey('timeout');
    expect($capturedOptions['timeout'])->toBe(30); // From config
});

it('merges custom options with defaults', function () {
    // Arrange
    $capturedOptions = null;
    $mockResponses = [
        function (Request $request, array $options) use (&$capturedOptions) {
            $capturedOptions = $options;
            return new Response(200, [], json_encode(['success' => true]));
        },
    ];
    
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    // Act
    $httpClient->request('POST', 'https://api.example.com/test', [
        'json' => ['custom' => 'data'],
        'timeout' => 45, // Override default
        'connect_timeout' => 10,
    ]);

    // Assert
    expect($capturedOptions['timeout'])->toBe(45); // Custom timeout
    expect($capturedOptions['connect_timeout'])->toBe(10);
    expect($capturedOptions['json'])->toEqual(['custom' => 'data']);
});

it('handles empty response bodies', function () {
    // Arrange
    $mockResponses = [
        new Response(204, [], ''), // No content
    ];
    
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    // Act
    $result = $httpClient->request('DELETE', 'https://api.example.com/resource');

    // Assert
    expect($result)->toBeArray();
    expect($result)->toBeEmpty();
});

it('streams Server-Sent Events correctly', function () {
    // Arrange
    $streamContent = "data: {\"choices\":[{\"delta\":{\"content\":\"Hello\"}}]}\n\n";
    $streamContent .= "data: {\"choices\":[{\"delta\":{\"content\":\" World\"}}]}\n\n";
    $streamContent .= "data: [DONE]\n\n";

    $mockResponses = [
        new Response(200, [
            'Content-Type' => 'text/event-stream',
        ], $streamContent),
    ];
    
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    $chunksReceived = [];
    $onChunk = function ($chunk) use (&$chunksReceived) {
        $chunksReceived[] = $chunk;
    };

    // Act
    $httpClient->stream(
        'https://api.example.com/stream',
        ['stream' => true],
        $onChunk
    );

    // Assert
    expect($chunksReceived)->toHaveCount(2);
    expect($chunksReceived[0])->toEqual(['choices' => [['delta' => ['content' => 'Hello']]]);
    expect($chunksReceived[1])->toEqual(['choices' => [['delta' => ['content' => ' World']]]);
});

it('handles streaming errors', function () {
    // Arrange
    $mockResponses = [
        new RequestException(
            'Connection failed',
            new Request('POST', 'https://api.example.com/stream')
        ),
    ];
    
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    $onChunk = function ($chunk) {
        // Should not be called
    };

    // Act & Assert
    expect(fn() => $httpClient->stream(
        'https://api.example.com/stream',
        ['stream' => true],
        $onChunk
    ))->toThrow(\OpenRouterSDK\Exceptions\HttpException::class);
});

it('prepares request options correctly', function () {
    // Arrange
    $capturedOptions = null;
    $mockResponses = [
        function (Request $request, array $options) use (&$capturedOptions) {
            $capturedOptions = $options;
            return new Response(200, [], json_encode(['success' => true]));
        },
    ];
    
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    // Act
    $httpClient->request('POST', 'https://api.example.com/test', [
        'headers' => [
            'Custom-Header' => 'custom-value',
        ],
        'json' => ['data' => 'test'],
    ]);

    // Assert
    expect($capturedOptions['headers'])->toMatchArray([
        'Authorization' => 'Bearer test-api-key',
        'Content-Type' => 'application/json',
        'HTTP-Referer' => 'https://example.com',
        'X-Title' => 'Test App',
        'Custom-Header' => 'custom-value',
    ]);
    expect($capturedOptions['timeout'])->toBe(30);
    expect($capturedOptions['json'])->toEqual(['data' => 'test']);
});

it('handles different HTTP methods', function () {
    // Arrange
    $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    $capturedMethods = [];
    
    $mockResponses = array_map(function ($method) use (&$capturedMethods) {
        return function (Request $request) use ($method, &$capturedMethods) {
            $capturedMethods[] = $request->getMethod();
            return new Response(200, [], json_encode(['method' => $method]));
        };
    }, $methods);
    
    $handlerStack = createMockHandler($mockResponses);
    $client = new Client(['handler' => $handlerStack]);
    $httpClient = new GuzzleHttpClient($client, $this->config);

    // Act
    foreach ($methods as $method) {
        $httpClient->request($method, 'https://api.example.com/test');
    }

    // Assert
    expect($capturedMethods)->toEqual($methods);
});
