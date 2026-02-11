<?php

declare(strict_types=1);

use OpenRouterSDK\Services\ChatService;
use OpenRouterSDK\Support\Configuration;
use OpenRouterSDK\Http\Client\GuzzleHttpClient;
use OpenRouterSDK\DTOs\Chat\ChatMessage;
use OpenRouterSDK\DTOs\Chat\ChatCompletionRequest;
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
        'default_model' => 'benchmark/model'
    ]);
    
    $this->httpClient = new GuzzleHttpClient($this->mockClient, $this->config);
    $this->chatService = new ChatService($this->httpClient, $this->config);
});

it('measures basic chat completion performance', function () {
    // Mock response for benchmarking
    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => str_repeat('Hello world! ', 20) // ~240 characters
                    ]
                ]
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 50,
                'total_tokens' => 60
            ]
        ]))
    );

    $startTime = microtime(true);
    
    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Benchmark test message')],
        model: 'benchmark/model'
    );
    
    $response = $this->chatService->create($request);
    
    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
    
    // Performance assertions
    expect($executionTime)->toBeLessThan(1000); // Should complete within 1 second
    expect($response->getContent())->toHaveLengthGreaterThan(50);
    expect($response->getUsage()['total_tokens'])->toBe(60);
    
    // Log performance metrics
    test()->addProfileData('basic_completion_time_ms', $executionTime);
})->profile();

it('benchmarks concurrent requests handling', function () {
    // Mock multiple responses for concurrent testing
    for ($i = 0; $i < 5; $i++) {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => "Response {$i}"
                        ]
                    ]
                ]
            ]))
        );
    }

    $requests = [];
    for ($i = 0; $i < 5; $i++) {
        $requests[] = new ChatCompletionRequest(
            messages: [new ChatMessage('user', "Concurrent test message {$i}")],
            model: 'benchmark/model'
        );
    }

    $startTime = microtime(true);
    
    // Execute requests concurrently (simulated)
    $responses = [];
    foreach ($requests as $request) {
        $responses[] = $this->chatService->create($request);
    }
    
    $endTime = microtime(true);
    $totalTime = ($endTime - $startTime) * 1000;
    
    expect($responses)->toHaveCount(5);
    expect($totalTime)->toBeLessThan(2000); // All 5 requests should complete within 2 seconds
    
    // Verify all responses are valid
    foreach ($responses as $index => $response) {
        expect($response->getContent())->toBe("Response {$index}");
    }
    
    test()->addProfileData('concurrent_requests_time_ms', $totalTime);
    test()->addProfileData('requests_per_second', 5000 / $totalTime); // Requests per second
})->profile();

it('measures memory usage for large responses', function () {
    // Create a large response payload
    $largeContent = str_repeat('This is a test response with substantial content. ', 1000); // ~50KB
    
    $this->mockHandler->append(
        new Response(200, [], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => $largeContent
                    ]
                ]
            ],
            'usage' => [
                'prompt_tokens' => 20,
                'completion_tokens' => 1000,
                'total_tokens' => 1020
            ]
        ]))
    );

    $startMemory = memory_get_usage(true);
    
    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Large response test')],
        model: 'benchmark/model',
        max_tokens: 1000
    );
    
    $response = $this->chatService->create($request);
    
    $endMemory = memory_get_usage(true);
    $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB
    
    expect(strlen($response->getContent()))->toBeGreaterThan(40000); // ~40KB minimum
    expect($memoryUsed)->toBeLessThan(10); // Should use less than 10MB additional memory
    
    test()->addProfileData('memory_usage_mb', $memoryUsed);
    test()->addProfileData('response_size_kb', strlen($response->getContent()) / 1024);
})->profile();

it('benchmarks streaming performance', function () {
    // Create streaming chunks
    $chunks = [];
    $contentParts = ['Hello', ' there', '! ', 'This', ' is', ' a', ' streaming', ' test', '.'];
    
    foreach ($contentParts as $part) {
        $chunks[] = "data: " . json_encode([
            'choices' => [
                [
                    'delta' => [
                        'content' => $part
                    ]
                ]
            ]
        ]) . "\n\n";
    }
    $chunks[] = "data: [DONE]\n\n";
    
    $streamBody = implode('', $chunks);
    $this->mockHandler->append(new Response(200, ['Content-Type' => 'text/event-stream'], $streamBody));

    $capturedContent = '';
    $chunkCount = 0;
    
    $startTime = microtime(true);
    
    $request = new ChatCompletionRequest(
        messages: [new ChatMessage('user', 'Streaming benchmark')],
        model: 'benchmark/model'
    );

    $this->chatService->stream($request, function ($chunk) use (&$capturedContent, &$chunkCount) {
        $chunkCount++;
        if (isset($chunk['choices'][0]['delta']['content'])) {
            $capturedContent .= $chunk['choices'][0]['delta']['content'];
        }
    });
    
    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000;
    
    expect($capturedContent)->toBe('Hello there! This is a streaming test.');
    expect($chunkCount)->toBe(10); // 9 content chunks + 1 DONE
    expect($executionTime)->toBeLessThan(500); // Should stream within 500ms
    
    test()->addProfileData('streaming_time_ms', $executionTime);
    test()->addProfileData('streaming_chunks', $chunkCount);
})->profile();

it('measures configuration overhead', function () {
    $iterations = 1000;
    $startTime = microtime(true);
    
    // Measure configuration creation overhead
    for ($i = 0; $i < $iterations; $i++) {
        $config = new Configuration([
            'api_key' => 'sk-or-test' . str_repeat('x', 32),
            'default_model' => 'test/model',
            'timeout' => 30
        ]);
        
        // Force object creation but don't use it
        $config->getApiKey();
    }
    
    $endTime = microtime(true);
    $totalTime = ($endTime - $startTime) * 1000;
    $avgTimePerConfig = $totalTime / $iterations;
    
    expect($avgTimePerConfig)->toBeLessThan(1); // Average less than 1ms per config
    
    test()->addProfileData('config_creation_avg_ms', $avgTimePerConfig);
    test()->addProfileData('config_creations_per_second', 1000 / $avgTimePerConfig);
})->profile();

it('benchmarks DTO creation and validation', function () {
    $iterations = 1000;
    $startTime = microtime(true);
    
    // Benchmark ChatMessage creation
    for ($i = 0; $i < $iterations; $i++) {
        $message = new ChatMessage('user', "Test message {$i}");
        expect($message->getRole())->toBe('user');
    }
    
    $endTime = microtime(true);
    $totalTime = ($endTime - $startTime) * 1000;
    $avgTimePerMessage = $totalTime / $iterations;
    
    expect($avgTimePerMessage)->toBeLessThan(0.5); // Very fast DTO creation
    
    test()->addProfileData('dto_creation_avg_ms', $avgTimePerMessage);
    test()->addProfileData('dtos_per_second', 1000 / $avgTimePerMessage);
})->profile();

it('measures HTTP client overhead', function () {
    // Mock a simple response
    $this->mockHandler->append(
        new Response(200, [], json_encode(['test' => 'response']))
    );

    $iterations = 100;
    $startTime = microtime(true);
    
    // Benchmark HTTP client calls
    for ($i = 0; $i < $iterations; $i++) {
        $this->httpClient->post('/test', ['test' => 'data']);
    }
    
    $endTime = microtime(true);
    $totalTime = ($endTime - $startTime) * 1000;
    $avgTimePerCall = $totalTime / $iterations;
    
    expect($avgTimePerCall)->toBeLessThan(10); // Reasonable HTTP overhead
    
    test()->addProfileData('http_call_avg_ms', $avgTimePerCall);
    test()->addProfileData('http_calls_per_second', 1000 / $avgTimePerCall);
})->profile();

it('stress tests with high volume requests', function () {
    // This is a stress test - skipped by default but available for manual execution
    $this->markTestSkipped('Stress test - run manually when needed');
    
    $requestCount = 1000;
    
    // Prepare mock responses
    for ($i = 0; $i < $requestCount; $i++) {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => "Response {$i}"
                        ]
                    ]
                ]
            ]))
        );
    }

    $startTime = microtime(true);
    $successfulRequests = 0;
    $failedRequests = 0;
    
    // Execute high volume requests
    for ($i = 0; $i < $requestCount; $i++) {
        try {
            $request = new ChatCompletionRequest(
                messages: [new ChatMessage('user', "Stress test {$i}")],
                model: 'stress/model'
            );
            
            $response = $this->chatService->create($request);
            if ($response->getContent() === "Response {$i}") {
                $successfulRequests++;
            }
        } catch (Exception $e) {
            $failedRequests++;
        }
    }
    
    $endTime = microtime(true);
    $totalTime = ($endTime - $startTime) * 1000;
    
    expect($successfulRequests)->toBe($requestCount);
    expect($failedRequests)->toBe(0);
    expect($totalTime)->toBeLessThan(30000); // Should complete within 30 seconds
    
    $requestsPerSecond = ($requestCount / $totalTime) * 1000;
    
    test()->addProfileData('stress_test_total_requests', $requestCount);
    test()->addProfileData('stress_test_successful', $successfulRequests);
    test()->addProfileData('stress_test_failed', $failedRequests);
    test()->addProfileData('stress_test_duration_ms', $totalTime);
    test()->addProfileData('stress_test_rps', $requestsPerSecond);
})->group('stress');

// Helper function to add profiling data
function addProfileData(string $key, mixed $value): void
{
    static $profileData = [];
    $profileData[$key] = $value;
    
    if (isset($_ENV['PROFILE_OUTPUT'])) {
        file_put_contents(
            $_ENV['PROFILE_OUTPUT'], 
            json_encode($profileData, JSON_PRETTY_PRINT)
        );
    }
}