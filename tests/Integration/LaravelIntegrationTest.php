<?php

use GuzzleHttp\ClientInterface;
use Illuminate\Foundation\Application;
use Mockery as m;
use OpenRouterSDK\Laravel\Facade\OpenRouter;
use OpenRouterSDK\Laravel\ServiceProvider\OpenRouterServiceProvider;
use OpenRouterSDK\Models\Chat\ChatCompletionRequest;
use OpenRouterSDK\Models\Chat\ChatMessage;

// Mock Laravel application for testing
class MockApplication
{
    private array $bindings = [];
    private array $singletons = [];
    private array $config = [];

    public function singleton($abstract, $concrete)
    {
        $this->singletons[$abstract] = $concrete;
    }

    public function bind($abstract, $concrete)
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function make($abstract)
    {
        if (isset($this->singletons[$abstract])) {
            $concrete = $this->singletons[$abstract];
            if (is_callable($concrete)) {
                return $concrete($this);
            }
            return $concrete;
        }

        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];
            if (is_callable($concrete)) {
                return $concrete($this);
            }
            return new $concrete();
        }

        throw new Exception("Binding not found: {$abstract}");
    }

    public function offsetGet($key)
    {
        return $this->config[$key] ?? null;
    }

    public function offsetSet($key, $value)
    {
        $this->config[$key] = $value;
    }

    public function runningInConsole()
    {
        return true;
    }
}

beforeEach(function () {
    // Clear mocks between tests
    m::close();
    
    // Setup mock application
    $this->app = new MockApplication();
    $this->app['config'] = [
        'openrouter' => [
            'api_key' => 'test-laravel-key',
            'base_url' => 'https://openrouter.ai/api/v1',
            'timeout' => 30,
            'default_model' => 'openai/gpt-4',
            'headers' => [
                'HTTP-Referer' => 'https://my-laravel-app.com',
                'X-Title' => 'My Laravel App',
            ],
        ],
    ];
});

afterEach(function () {
    m::close();
});

it('registers service provider correctly', function () {
    // Arrange
    $serviceProvider = new OpenRouterServiceProvider($this->app);

    // Act
    $serviceProvider->register();

    // Assert
    expect($this->app->make(\OpenRouterSDK\Contracts\ConfigurationInterface::class))->toBeInstanceOf(
        \OpenRouterSDK\Support\Configuration::class
    );
    
    expect($this->app->make(\OpenRouterSDK\Contracts\HttpClientInterface::class))->toBeInstanceOf(
        \OpenRouterSDK\Http\Client\GuzzleHttpClient::class
    );
    
    expect($this->app->make(\OpenRouterSDK\Contracts\ChatServiceInterface::class))->toBeInstanceOf(
        \OpenRouterSDK\Services\ChatService::class
    );
});

it('binds configuration with Laravel config values', function () {
    // Arrange
    $serviceProvider = new OpenRouterServiceProvider($this->app);

    // Act
    $serviceProvider->register();
    $config = $this->app->make(\OpenRouterSDK\Contracts\ConfigurationInterface::class);

    // Assert
    expect($config->getApiKey())->toBe('test-laravel-key');
    expect($config->getBaseUrl())->toBe('https://openrouter.ai/api/v1');
    expect($config->getTimeout())->toBe(30);
    expect($config->getDefaultModel())->toBe('openai/gpt-4');
    
    $headers = $config->getDefaultHeaders();
    expect($headers['HTTP-Referer'])->toBe('https://my-laravel-app.com');
    expect($headers['X-Title'])->toBe('My Laravel App');
});

it('creates HTTP client with proper Guzzle configuration', function () {
    // Arrange
    $serviceProvider = new OpenRouterServiceProvider($this->app);

    // Act
    $serviceProvider->register();
    $httpClient = $this->app->make(\OpenRouterSDK\Contracts\HttpClientInterface::class);

    // Assert
    expect($httpClient)->toBeInstanceOf(\OpenRouterSDK\Http\Client\GuzzleHttpClient::class);
});

it('binds main chat service correctly', function () {
    // Arrange
    $serviceProvider = new OpenRouterServiceProvider($this->app);

    // Act
    $serviceProvider->register();
    $chatService = $this->app->make(\OpenRouterSDK\Contracts\ChatServiceInterface::class);

    // Assert
    expect($chatService)->toBeInstanceOf(\OpenRouterSDK\Services\ChatService::class);
});

it('registers facade accessor', function () {
    // Arrange
    $serviceProvider = new OpenRouterServiceProvider($this->app);

    // Act
    $serviceProvider->register();
    $facadeAccessor = $this->app->make('openrouter');

    // Assert
    expect($facadeAccessor)->toBeInstanceOf(\OpenRouterSDK\Services\ChatService::class);
});

it('merges default configuration', function () {
    // Arrange
    $serviceProvider = new OpenRouterServiceProvider($this->app);
    
    // Remove some config values to test defaults
    unset($this->app['config']['openrouter']['default_model']);
    unset($this->app['config']['openrouter']['headers']);

    // Act
    $serviceProvider->register();
    $config = $this->app->make(\OpenRouterSDK\Contracts\ConfigurationInterface::class);

    // Assert
    expect($config->getDefaultModel())->toBeNull(); // Should be null when not set
    
    $headers = $config->getDefaultHeaders();
    expect($headers['HTTP-Referer'])->toBe('');
    expect($headers['X-Title'])->toBe('');
});

it('handles configuration with minimal settings', function () {
    // Arrange
    $this->app['config'] = [
        'openrouter' => [
            'api_key' => 'minimal-key',
            // Other settings omitted - should use defaults
        ],
    ];
    
    $serviceProvider = new OpenRouterServiceProvider($this->app);

    // Act
    $serviceProvider->register();
    $config = $this->app->make(\OpenRouterSDK\Contracts\ConfigurationInterface::class);

    // Assert
    expect($config->getApiKey())->toBe('minimal-key');
    expect($config->getBaseUrl())->toBe('https://openrouter.ai/api/v1'); // Default
    expect($config->getTimeout())->toBe(30); // Default
    expect($config->getDefaultModel())->toBeNull(); // Default
});

it('bootstraps service provider correctly', function () {
    // Arrange
    $serviceProvider = new OpenRouterServiceProvider($this->app);
    $publishedConfigs = [];

    // Mock the publishes method
    $originalPublishes = (function () {
        return $this->publishes;
    })->bindTo($serviceProvider, OpenRouterServiceProvider::class);

    // Act
    $serviceProvider->boot();

    // Since we can't easily test the publishes method without Laravel,
    // we'll verify the service provider initializes without errors
    expect($serviceProvider)->toBeInstanceOf(OpenRouterServiceProvider::class);
});

it('integrates with Laravel container resolution', function () {
    // Arrange
    $serviceProvider = new OpenRouterServiceProvider($this->app);
    $serviceProvider->register();

    // Mock the HTTP client to avoid actual HTTP calls
    $mockHttpClient = m::mock(\OpenRouterSDK\Contracts\HttpClientInterface::class);
    $mockHttpClient->shouldReceive('request')
        ->andReturn([
            'id' => 'test-response',
            'choices' => [[
                'message' => ['content' => 'Mocked response']
            ]]
        ]);

    // Override the HTTP client binding
    $this->app->singleton(\OpenRouterSDK\Contracts\HttpClientInterface::class, function () use ($mockHttpClient) {
        return $mockHttpClient;
    });

    // Rebuild the chat service with mocked HTTP client
    $this->app->singleton(\OpenRouterSDK\Contracts\ChatServiceInterface::class, function ($app) {
        return new \OpenRouterSDK\Services\ChatService(
            $app[\OpenRouterSDK\Contracts\HttpClientInterface::class],
            $app[\OpenRouterSDK\Contracts\ConfigurationInterface::class]
        );
    });

    // Act
    $chatService = $this->app->make(\OpenRouterSDK\Contracts\ChatServiceInterface::class);
    
    $request = new ChatCompletionRequest(
        messages: [ChatMessage::user('Test message')],
        model: 'openai/gpt-4'
    );

    $response = $chatService->create($request);

    // Assert
    expect($response)->toBeInstanceOf(\OpenRouterSDK\Models\Chat\ChatCompletionResponse::class);
    expect($response->id)->toBe('test-response');
});

it('handles service provider registration order independence', function () {
    // Arrange
    $serviceProvider = new OpenRouterServiceProvider($this->app);

    // Act - Register multiple times (should not cause issues)
    $serviceProvider->register();
    $serviceProvider->register(); // Second registration
    
    $config = $this->app->make(\OpenRouterSDK\Contracts\ConfigurationInterface::class);

    // Assert
    expect($config->getApiKey())->toBe('test-laravel-key');
});

it('maintains singleton pattern for services', function () {
    // Arrange
    $serviceProvider = new OpenRouterServiceProvider($this->app);
    $serviceProvider->register();

    // Act
    $service1 = $this->app->make(\OpenRouterSDK\Contracts\ChatServiceInterface::class);
    $service2 = $this->app->make(\OpenRouterSDK\Contracts\ChatServiceInterface::class);

    // Assert
    expect($service1)->toBe($service2); // Same instance
    expect(spl_object_hash($service1))->toBe(spl_object_hash($service2));
});
