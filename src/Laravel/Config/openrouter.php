<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | OpenRouter API Configuration
    |--------------------------------------------------------------------------
    */

    'api_key' => env('OPENROUTER_API_KEY'),
    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
    'timeout' => env('OPENROUTER_TIMEOUT', 30),
    'default_model' => env('OPENROUTER_DEFAULT_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Application Identification
    | These help your app appear on OpenRouter leaderboards
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'HTTP-Referer' => env('OPENROUTER_REFERER', ''),
        'X-Title' => env('OPENROUTER_TITLE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => env('OPENROUTER_RETRY_ATTEMPTS', 3),
        'backoff_ms' => env('OPENROUTER_RETRY_BACKOFF', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('OPENROUTER_CACHE_ENABLED', false),
        'ttl' => env('OPENROUTER_CACHE_TTL', 3600),
        'store' => env('OPENROUTER_CACHE_STORE', 'default'),
    ],
];