test# OpenRouter PHP SDK

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

// Simple chat
$response = OpenRouter::simpleChat('Write a haiku about programming.');
echo $response;

// Advanced usage
$request = new ChatCompletionRequest(
    messages: [
        ChatMessage::user('Generate a product description for a smart coffee maker.')
    ],
    temperature: 0.8
);

$response = OpenRouter::create($request);
echo $response->getContent();

// Streaming
OpenRouter::stream($request, function ($chunk) {
    if (isset($chunk['choices'][0]['delta']['content'])) {
        echo $chunk['choices'][0]['delta']['content'];
        flush();
    }
});
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

$request = new ChatCompletionRequest(
    messages: [ChatMessage::user('Generate a user profile with name, age, and email.')],
    response_format: ResponseFormat::jsonSchema([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
            'email' => ['type' => 'string', 'format' => 'email']
        ],
        'required' => ['name', 'age', 'email']
    ])
);

$response = $service->create($request);
$userData = json_decode($response->getContent(), true);
```

### Multi-modal Content

```php
$message = ChatMessage::user([
    [
        'type' => 'text',
        'text' => 'What is in this image?'
    ],
    [
        'type' => 'image_url',
        'image_url' => [
            'url' => 'https://example.com/image.jpg'
        ]
    ]
]);

$request = new ChatCompletionRequest(messages: [$message]);
$response = $service->create($request);
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

try {
    $response = $service->create($request);
} catch (ValidationException $e) {
    echo "Validation error: " . $e->getMessage();
    print_r($e->getErrors());
} catch (HttpException $e) {
    echo "HTTP error: " . $e->getMessage();
    echo "Status code: " . $e->getStatusCode();
} catch (Exception $e) {
    echo "General error: " . $e->getMessage();
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
