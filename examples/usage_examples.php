<?php

declare(strict_types=1);

/**
 * OpenRouter PHP SDK Usage Examples
 * 
 * This file demonstrates various ways to use the OpenRouter SDK
 */

require_once __DIR__ . '/vendor/autoload.php';

use OpenRouterSDK\Services\ChatService;
use OpenRouterSDK\Models\Chat\ChatCompletionRequest;
use OpenRouterSDK\Models\Chat\ChatMessage;
use OpenRouterSDK\Models\Chat\ResponseFormat;
use OpenRouterSDK\Support\Configuration;
// use GuzzleHttp\ClientInterface;
// use OpenRouterSDK\Http\Client\GuzzleHttpClient;

// Example 1: Basic Setup
function example1_basic_setup(): void
{
    echo "=== Example 1: Basic Setup ===\n";
    
    $config = new Configuration([
        'api_key' => 'your-api-key-here',
        'default_model' => 'openai/gpt-4'
    ]);
    
    // Note: To use the SDK, you need to:
    // 1. Install GuzzleHttp via composer
    // 2. Create a concrete Client instance
    // 3. Pass it to GuzzleHttpClient
    
    /*
    $httpClient = new GuzzleHttpClient(
        new \GuzzleHttp\Client(['timeout' => 30]),
        $config
    );
    
    $service = new ChatService($httpClient, $config);
    */
    
    echo "Configuration object created successfully!\n";
    echo "To use the full SDK, install GuzzleHttp dependencies.\n";
    
    echo "SDK configured successfully!\n\n";
}

// Example 2: Simple Chat
function example2_simple_chat(): void
{
    echo "=== Example 2: Simple Chat ===\n";
    
    // This would work with a real API key
    /*
    $response = $service->simpleChat('What is the capital of France?');
    echo "Response: " . $response . "\n\n";
    */
    
    echo "Simple chat example ready (requires valid API key)\n\n";
}

// Example 3: Advanced Chat with Configuration
function example3_advanced_chat(): void
{
    echo "=== Example 3: Advanced Chat ===\n";
    
    $request = new ChatCompletionRequest(
        messages: [
            ChatMessage::system('You are a helpful assistant that explains concepts clearly.'),
            ChatMessage::user('Explain quantum computing in simple terms.')
        ],
        model: 'openai/gpt-4',
        temperature: 0.7,
        max_tokens: 200,
        top_p: 0.9
    );
    
    echo "Request created with:\n";
    echo "- " . count($request->messages) . " messages\n";
    echo "- Model: " . ($request->model ?? 'default') . "\n";
    echo "- Temperature: " . ($request->temperature ?? 'default') . "\n";
    echo "- Max tokens: " . ($request->max_tokens ?? 'default') . "\n\n";
}

// Example 4: Structured Output
function example4_structured_output(): void
{
    echo "=== Example 4: Structured Output ===\n";
    
    $request = new ChatCompletionRequest(
        messages: [
            ChatMessage::user('Generate a user profile with name, age, and email.')
        ],
        response_format: ResponseFormat::jsonSchema([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer', 'minimum' => 18, 'maximum' => 100],
                'email' => ['type' => 'string', 'format' => 'email']
            ],
            'required' => ['name', 'age', 'email'],
            'additionalProperties' => false
        ])
    );
    
    echo "Structured output request configured\n";
    echo "Schema validates name, age (18-100), and email format\n\n";
}

// Example 5: Multi-modal Content
function example5_multimodal(): void
{
    echo "=== Example 5: Multi-modal Content ===\n";
    
    $message = ChatMessage::user([
        [
            'type' => 'text',
            'text' => 'What is in this image? Describe the main objects and colors.'
        ],
        [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/PNG_transparency_demonstration_1.png/640px-PNG_transparency_demonstration_1.png'
            ]
        ]
    ]);
    
    $request = new ChatCompletionRequest(
        messages: [$message],
        model: 'openai/gpt-4-vision-preview'
    );
    
    echo "Multi-modal request created\n";
    echo "Contains both text and image content\n\n";
}

// Example 6: Streaming Response
function example6_streaming(): void
{
    echo "=== Example 6: Streaming Response ===\n";
    
    $request = new ChatCompletionRequest(
        messages: [
            ChatMessage::user('Write a short story about a programmer learning AI.')
        ],
        stream: true
    );
    
    echo "Streaming request configured\n";
    echo "Will receive chunks of response as they're generated\n\n";
    
    // Example streaming callback
    $streamCallback = function ($chunk) {
        if (isset($chunk['choices'][0]['delta']['content'])) {
            echo $chunk['choices'][0]['delta']['content'];
            flush();
        }
    };
    
    echo "Streaming callback function ready\n\n";
}

// Example 7: Error Handling
function example7_error_handling(): void
{
    echo "=== Example 7: Error Handling ===\n";
    
    echo "Try-catch pattern for SDK usage:\n\n";
    echo "try {\n";
    echo "    \$response = \$service->create(\$request);\n";
    echo "} catch (ValidationException \$e) {\n";
    echo "    // Handle validation errors\n";
    echo "    echo \"Validation failed: \" . \$e->getMessage();\n";
    echo "} catch (HttpException \$e) {\n";
    echo "    // Handle HTTP errors\n";
    echo "    echo \"API error: \" . \$e->getMessage();\n";
    echo "} catch (Exception \$e) {\n";
    echo "    // Handle other errors\n";
    echo "    echo \"Unexpected error: \" . \$e->getMessage();\n";
    echo "}\n\n";
}

// Run all examples
echo "OpenRouter PHP SDK Examples\n";
echo str_repeat("=", 50) . "\n\n";

example1_basic_setup();
example2_simple_chat();
example3_advanced_chat();
example4_structured_output();
example5_multimodal();
example6_streaming();
example7_error_handling();

echo "All examples completed!\n";
echo "Remember to replace 'your-api-key-here' with your actual OpenRouter API key.\n";