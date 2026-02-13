# OpenRouter PHP SDK

Production-ready, framework-agnostic PHP SDK for OpenRouter API with Laravel integration.

## Features

- ✅ **Framework-agnostic core** - Works with any PHP 8.1+ application
- ✅ **Laravel integration** - Seamless Laravel 9+ integration with Service Provider and Facade
- ✅ **Full OpenRouter API support** - Chat completions, streaming, embeddings, models
- ✅ **Robust error handling** - Comprehensive exception hierarchy with detailed error information
- ✅ **Production-ready** - Retry logic, timeout handling, and middleware support
- ✅ **Type-safe** - Strict typing with comprehensive validation
- ✅ **Lightweight** - Minimal dependencies, only Guzzle HTTP client required

## Installation

```bash
composer require eatzy/openrouter-php-sdk
```

## Quick Start

### Vanilla PHP Usage

```php
<?php

use OpenRouterSDK\Services\ChatService;
use OpenRouterSDK\Models\Chat\ChatCompletionRequest;
use OpenRouterSDK\Models\Chat\ChatMessage;
use OpenRouterSDK\Support\Configuration;
use GuzzleHttp\Client;
use OpenRouterSDK\Http\Client\GuzzleHttpClient;

// Configure the SDK
$config = new Configuration([
    'api_key' => 'your-openrouter-api-key',
    'default_model' => 'openai/gpt-4'
]);

// Create HTTP client
$httpClient = new GuzzleHttpClient(
    new Client(['timeout' => 30]),
    $config
);

// Create chat service
$service = new ChatService($httpClient, $config);

// Simple chat
$response = $service->simpleChat('What is the capital of France?');
echo $response; // "The capital of France is Paris."

// Advanced usage with full control
$request = new ChatCompletionRequest(
    messages: [
        ChatMessage::system('You are a helpful assistant.'),
        ChatMessage::user('Explain quantum computing in simple terms.')
    ],
    model: 'openai/gpt-4',
    temperature: 0.7,
    max_tokens: 150
);

$response = $service->create($request);
echo $response->getContent();

// Streaming responses
$service->stream($request, function ($chunk) {
    if (isset($chunk['choices'][0]['delta']['content'])) {
        echo $chunk['choices'][0]['delta']['content'];
        flush();
    }
});
```

### Laravel Usage

#### 1. Publish Configuration

```bash
php artisan vendor:publish --tag=openrouter-config
```

#### 2. Configure Environment Variables

```env
OPENROUTER_API_KEY=your-api-key-here
OPENROUTER_DEFAULT_MODEL=openai/gpt-4
OPENROUTER_TIMEOUT=30
OPENROUTER_REFERER=https://yourapp.com
OPENROUTER_TITLE=Your App Name
```

#### 3. Use the Facade

```php
<?php

use OpenRouterSDK\Laravel\Facade\OpenRouter;
use OpenRouterSDK\Models\Chat\ChatMessage;
use OpenRouterSDK\Models\Chat\ChatCompletionRequest;
use OpenRouterSDK\Models\Chat\ResponseFormat;
use Illuminate\Support\Facades\Log;

// Simple chat
$response = OpenRouter::simpleChat('Write a haiku about programming.');
echo $response;

// Advanced usage with full configuration
$request = new ChatCompletionRequest(
    messages: [
        ChatMessage::system('You are a professional marketing copywriter.'),
        ChatMessage::user('Generate a compelling product description for a smart coffee maker.')
    ],
    model: 'openai/gpt-4',
    temperature: 0.8,
    max_tokens: 300,
    top_p: 0.9,
    frequency_penalty: 0.5,
    presence_penalty: 0.5
);

$response = OpenRouter::create($request);
echo $response->getContent();

// Streaming with real-time output
OpenRouter::stream($request, function ($chunk) {
    if (isset($chunk['choices'][0]['delta']['content'])) {
        echo $chunk['choices'][0]['delta']['content'];
        flush();
        ob_flush(); // Ensure output is sent immediately
    }
});

// Laravel integration with error handling
try {
    $response = OpenRouter::create(new ChatCompletionRequest(
        messages: [ChatMessage::user('Process customer inquiry')],
        timeout: 15 // Override default timeout
    ));
    
    // Store in database or cache
    DB::table('ai_responses')->insert([
        'content' => $response->getContent(),
        'model' => $response->getModel(),
        'created_at' => now()
    ]);
    
} catch (Exception $e) {
    Log::error('OpenRouter API error', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return response()->json([
        'error' => 'Service temporarily unavailable',
        'retry_after' => 30
    ], 503);
}


// Queue job example
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAIQuery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $prompt;
    protected $userId;
    
    public function __construct($prompt, $userId)
    {
        $this->prompt = $prompt;
        $this->userId = $userId;
    }
    
    public function handle()
    {
        try {
            $response = OpenRouter::simpleChat($this->prompt);
            
            // Save result
            AiResult::create([
                'user_id' => $this->userId,
                'prompt' => $this->prompt,
                'response' => $response,
                'processed_at' => now()
            ]);
            
        } catch (Exception $e) {
            // Log error and potentially retry
            Log::error('AI Query Processing Failed', [
                'prompt' => $this->prompt,
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            
            // Optionally re-queue with delay
            if ($this->attempts() < 3) {
                $this->release(60); // Retry after 60 seconds
            }
        }
    }
}
```

## Configuration

### Vanilla PHP Configuration

```php
$config = new Configuration([
    'api_key' => 'your-api-key',
    'base_url' => 'https://openrouter.ai/api/v1',
    'timeout' => 30,
    'default_model' => 'openai/gpt-4',
    'headers' => [
        'HTTP-Referer' => 'https://yourapp.com',
        'X-Title' => 'Your App Name'
    ]
]);
```

### Laravel Configuration

Publish the config file and modify `config/openrouter.php`:

```php
return [
    'api_key' => env('OPENROUTER_API_KEY'),
    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
    'timeout' => env('OPENROUTER_TIMEOUT', 30),
    'default_model' => env('OPENROUTER_DEFAULT_MODEL'),
    'headers' => [
        'HTTP-Referer' => env('OPENROUTER_REFERER', ''),
        'X-Title' => env('OPENROUTER_TITLE', ''),
    ],
];
```

## Advanced Features

### Structured Outputs

```php
use OpenRouterSDK\Models\Chat\ResponseFormat;

// JSON Schema validation for complex structures
$request = new ChatCompletionRequest(
    messages: [ChatMessage::user('Generate a user profile with name, age, and email.')],
    response_format: ResponseFormat::jsonSchema([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 120],
            'email' => ['type' => 'string', 'format' => 'email'],
            'preferences' => [
                'type' => 'object',
                'properties' => [
                    'theme' => ['type' => 'string', 'enum' => ['light', 'dark']],
                    'notifications' => ['type' => 'boolean']
                ]
            ]
        ],
        'required' => ['name', 'age', 'email']
    ])
);

$response = $service->create($request);
$userData = json_decode($response->getContent(), true);

// Simple JSON object mode (less strict)
$simpleJsonRequest = new ChatCompletionRequest(
    messages: [ChatMessage::user('List 5 programming languages as a JSON array.')],
    response_format: new ResponseFormat(['type' => 'json_object'])
);

$languages = json_decode($service->create($simpleJsonRequest)->getContent(), true);

// Array of objects example
$productCatalogRequest = new ChatCompletionRequest(
    messages: [ChatMessage::user('Generate 3 product entries with name, price, and category.')],
    response_format: ResponseFormat::jsonSchema([
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'price' => ['type' => 'number', 'minimum' => 0],
                'category' => ['type' => 'string']
            ],
            'required' => ['name', 'price', 'category']
        ]
    ])
);
```

### Multi-modal Content

```php
use OpenRouterSDK\Models\Chat\ChatMessage;

// Image analysis with URL
$message = ChatMessage::user([
    [
        'type' => 'text',
        'text' => 'What is in this image? Describe the scene in detail.'
    ],
    [
        'type' => 'image_url',
        'image_url' => [
            'url' => 'https://example.com/image.jpg',
            'detail' => 'high' // or 'low' for faster processing
        ]
    ]
]);

$request = new ChatCompletionRequest(
    messages: [$message],
    model: 'openai/gpt-4-vision-preview'
);
$response = $service->create($request);

// Image analysis with base64 encoded image
$imageData = base64_encode(file_get_contents('path/to/local/image.jpg'));

$localImageMessage = ChatMessage::user([
    [
        'type' => 'text',
        'text' => 'Analyze this document and extract key information.'
    ],
    [
        'type' => 'image_url',
        'image_url' => [
            'url' => "data:image/jpeg;base64,{$imageData}",
            'detail' => 'high'
        ]
    ]
]);

// Audio input (for models that support audio)
$audioData = base64_encode(file_get_contents('path/to/audio.wav'));

$audioMessage = ChatMessage::user([
    [
        'type' => 'text',
        'text' => 'Transcribe and summarize this audio recording.'
    ],
    [
        'type' => 'input_audio',
        'input_audio' => [
            'data' => $audioData,
            'format' => 'wav' // wav, mp3, flac, etc.
        ]
    ]
]);
```

### Retry Logic

The SDK includes built-in retry logic with exponential backoff:

```php
// Configurable in Laravel config
'retry' => [
    'max_attempts' => 3,
    'backoff_ms' => 1000,
]
```

## Error Handling

```php
use OpenRouterSDK\Exceptions\HttpException;
use OpenRouterSDK\Exceptions\ValidationException;
use OpenRouterSDK\Exceptions\MaxRetriesExceededException;

try {
    $response = $service->create($request);
} catch (ValidationException $e) {
    // Handle validation errors (invalid parameters, missing required fields)
    echo "Validation error: " . $e->getMessage();
    print_r($e->getErrors()); // Array of specific validation errors
} catch (HttpException $e) {
    // Handle HTTP errors (4xx, 5xx responses)
    echo "HTTP error: " . $e->getMessage();
    echo "Status code: " . $e->getStatusCode();
    echo "Response body: " . $e->getBody();
    
    // Specific error handling
    switch ($e->getStatusCode()) {
        case 401:
            echo "Invalid API key";
            break;
        case 404:
            echo "Model not found";
            break;
        case 429:
            echo "Rate limit exceeded";
            break;
        case 500:
            echo "Server error, please try again later";
            break;
    }
} catch (MaxRetriesExceededException $e) {
    // Handle retry exhaustion
    echo "Max retries exceeded after " . $e->getMaxRetries() . " attempts";
    echo "Last error: " . $e->getPrevious()->getMessage();
} catch (Exception $e) {
    // Handle other unexpected errors
    echo "Unexpected error: " . $e->getMessage();
}

// Graceful degradation with fallback models
function getReliableResponse($service, $primaryModel, $fallbackModels, $messages) {
    $modelsToTry = array_merge([$primaryModel], $fallbackModels);
    
    foreach ($modelsToTry as $model) {
        try {
            return $service->create(new ChatCompletionRequest(
                messages: $messages,
                model: $model
            ));
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                continue; // Try next model
            }
            throw $e; // Re-throw other HTTP errors
        }
    }
    
    throw new Exception('All models failed');
}

// Usage
try {
    $response = getReliableResponse(
        $service,
        'premium-model-not-available',
        ['openai/gpt-3.5-turbo', 'mistralai/mistral-7b-instruct:free'],
        [ChatMessage::user('Hello world')]
    );
    echo $response->getContent();
} catch (Exception $e) {
    echo "All fallback attempts failed: " . $e->getMessage();
}
```

## Testing

Run the test suite:

```bash
composer test

# With coverage
composer test-coverage
```

## Requirements

- PHP 8.1+
- Guzzle HTTP Client 7.8+
- JSON extension

## License

MIT License - see LICENSE file for details.

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting pull requests.

## Support

For support, please open an issue on GitHub or contact the maintainers.
