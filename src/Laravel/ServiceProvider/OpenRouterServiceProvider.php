<?php

declare(strict_types=1);

namespace OpenRouterSDK\Laravel\ServiceProvider;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use OpenRouterSDK\Contracts\ChatServiceInterface;
use OpenRouterSDK\Contracts\ConfigurationInterface;
use OpenRouterSDK\Contracts\HttpClientInterface;
use OpenRouterSDK\Http\Client\GuzzleHttpClient;
use OpenRouterSDK\Services\ChatService;
use OpenRouterSDK\Support\Configuration;

/**
 * OpenRouter Service Provider for Laravel
 */
class OpenRouterServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/openrouter.php',
            'openrouter'
        );

        // Bind configuration
        $this->app->singleton(ConfigurationInterface::class, function ($app) {
            return new Configuration($app['config']['openrouter']);
        });

        // Bind HTTP client
        $this->app->singleton(HttpClientInterface::class, function ($app) {
            $guzzleClient = new Client([
                'timeout' => $app[ConfigurationInterface::class]->getTimeout(),
            ]);

            return new GuzzleHttpClient(
                $guzzleClient,
                $app[ConfigurationInterface::class]
            );
        });

        // Bind main service
        $this->app->singleton(ChatServiceInterface::class, function ($app) {
            return new ChatService(
                $app[HttpClientInterface::class],
                $app[ConfigurationInterface::class]
            );
        });

        // Bind facade accessor
        $this->app->singleton('openrouter', function ($app) {
            return $app[ChatServiceInterface::class];
        });
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../Config/openrouter.php' => config_path('openrouter.php'),
            ], 'openrouter-config');
        }
    }
}