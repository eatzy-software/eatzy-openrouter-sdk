<?php

use OpenRouterSDK\Support\Configuration;

it('creates configuration with default values', function () {
    // Act
    $config = new Configuration();

    // Assert
    expect($config->getApiKey())->toBe('');
    expect($config->getBaseUrl())->toBe('https://openrouter.ai/api/v1');
    expect($config->getTimeout())->toBe(30);
    expect($config->getDefaultModel())->toBeNull();
});

it('accepts custom configuration values', function () {
    // Arrange
    $customConfig = [
        'api_key' => 'sk-custom-key-123',
        'base_url' => 'https://custom.openrouter.ai/v1',
        'timeout' => 45,
        'default_model' => 'anthropic/claude-3-opus',
        'headers' => [
            'HTTP-Referer' => 'https://myapp.com',
            'X-Title' => 'My Custom App',
        ],
    ];

    // Act
    $config = new Configuration($customConfig);

    // Assert
    expect($config->getApiKey())->toBe('sk-custom-key-123');
    expect($config->getBaseUrl())->toBe('https://custom.openrouter.ai/v1');
    expect($config->getTimeout())->toBe(45);
    expect($config->getDefaultModel())->toBe('anthropic/claude-3-opus');
});

it('merges partial configuration with defaults', function () {
    // Arrange - Only provide some values
    $partialConfig = [
        'api_key' => 'partial-key',
        'timeout' => 60,
    ];

    // Act
    $config = new Configuration($partialConfig);

    // Assert - Provided values should be set, others should use defaults
    expect($config->getApiKey())->toBe('partial-key');
    expect($config->getTimeout())->toBe(60);
    expect($config->getBaseUrl())->toBe('https://openrouter.ai/api/v1'); // Default
    expect($config->getDefaultModel())->toBeNull(); // Default
});

it('generates correct default headers', function () {
    // Arrange
    $config = new Configuration([
        'api_key' => 'test-key-123',
        'headers' => [
            'HTTP-Referer' => 'https://example.com',
            'X-Title' => 'Test Application',
        ],
    ]);

    // Act
    $headers = $config->getDefaultHeaders();

    // Assert
    expect($headers)->toMatchArray([
        'Authorization' => 'Bearer test-key-123',
        'Content-Type' => 'application/json',
        'HTTP-Referer' => 'https://example.com',
        'X-Title' => 'Test Application',
    ]);
});

it('filters out empty header values', function () {
    // Arrange
    $config = new Configuration([
        'api_key' => 'test-key',
        'headers' => [
            'HTTP-Referer' => '', // Empty value
            'X-Title' => 'My App',
        ],
    ]);

    // Act
    $headers = $config->getDefaultHeaders();

    // Assert
    expect($headers)->toHaveKey('Authorization');
    expect($headers)->toHaveKey('Content-Type');
    expect($headers)->toHaveKey('X-Title');
    expect($headers)->not()->toHaveKey('HTTP-Referer'); // Should be filtered out
});

it('handles base URL with trailing slash', function () {
    // Arrange
    $config = new Configuration([
        'base_url' => 'https://api.openrouter.ai/v1/', // With trailing slash
    ]);

    // Act
    $baseUrl = $config->getBaseUrl();

    // Assert
    expect($baseUrl)->toBe('https://api.openrouter.ai/v1'); // Should be trimmed
    expect($baseUrl)->not()->toEndWith('/');
});

it('converts configuration to array', function () {
    // Arrange
    $expectedConfig = [
        'api_key' => 'array-test-key',
        'base_url' => 'https://test.openrouter.ai/v1',
        'timeout' => 25,
        'default_model' => 'google/gemini-pro',
        'headers' => [
            'HTTP-Referer' => 'https://test-app.com',
            'X-Title' => 'Test App',
        ],
    ];

    // Act
    $config = new Configuration($expectedConfig);
    $arrayConfig = $config->toArray();

    // Assert
    expect($arrayConfig)->toEqual($expectedConfig);
});

it('handles empty headers configuration', function () {
    // Arrange
    $config = new Configuration([
        'api_key' => 'test-key',
        'headers' => [], // Empty headers
    ]);

    // Act
    $headers = $config->getDefaultHeaders();

    // Assert
    expect($headers)->toMatchArray([
        'Authorization' => 'Bearer test-key',
        'Content-Type' => 'application/json',
    ]);
    expect(count($headers))->toBe(2); // Only required headers
});

it('works with null default model', function () {
    // Arrange
    $config = new Configuration([
        'api_key' => 'test-key',
        'default_model' => null,
    ]);

    // Act
    $defaultModel = $config->getDefaultModel();

    // Assert
    expect($defaultModel)->toBeNull();
});

it('works with empty string default model', function () {
    // Arrange
    $config = new Configuration([
        'api_key' => 'test-key',
        'default_model' => '',
    ]);

    // Act
    $defaultModel = $config->getDefaultModel();

    // Assert
    expect($defaultModel)->toBeNull();
});

it('handles zero timeout value', function () {
    // Arrange
    $config = new Configuration([
        'api_key' => 'test-key',
        'timeout' => 0,
    ]);

    // Act
    $timeout = $config->getTimeout();

    // Assert
    expect($timeout)->toBe(0);
});

it('maintains immutability of configuration values', function () {
    // Arrange
    $initialConfig = [
        'api_key' => 'initial-key',
        'base_url' => 'https://initial.url',
    ];
    
    $config = new Configuration($initialConfig);

    // Act - Try to modify the internal array (should not affect config)
    $array = $config->toArray();
    $array['api_key'] = 'modified-key';
    $array['base_url'] = 'https://modified.url';

    // Assert - Original config should remain unchanged
    expect($config->getApiKey())->toBe('initial-key');
    expect($config->getBaseUrl())->toBe('https://initial.url');
    
    // New config from modified array should have new values
    $newConfig = new Configuration($array);
    expect($newConfig->getApiKey())->toBe('modified-key');
    expect($newConfig->getBaseUrl())->toBe('https://modified.url');
});

it('handles special characters in API key', function () {
    // Arrange
    $specialKey = 'sk-test-key_with-special.chars+123=';
    $config = new Configuration([
        'api_key' => $specialKey,
    ]);

    // Act
    $headers = $config->getDefaultHeaders();

    // Assert
    expect($headers['Authorization'])->toBe("Bearer {$specialKey}");
});

it('provides backward compatibility with older configuration formats', function () {
    // Arrange - Simulate older config format
    $oldConfig = [
        'api_key' => 'old-format-key',
        'base_url' => 'https://old.api.url/v1',
        'timeout' => 30,
        // Missing some newer fields
    ];

    // Act
    $config = new Configuration($oldConfig);

    // Assert - Should work with defaults for missing fields
    expect($config->getApiKey())->toBe('old-format-key');
    expect($config->getBaseUrl())->toBe('https://old.api.url/v1');
    expect($config->getDefaultModel())->toBeNull(); // Default
    expect($config->getTimeout())->toBe(30);
});

it('handles numeric string values correctly', function () {
    // Arrange
    $config = new Configuration([
        'api_key' => 'test-key',
        'timeout' => '45', // String instead of integer
        'default_model' => 'openai/gpt-4',
    ]);

    // Act
    $timeout = $config->getTimeout();

    // Assert
    expect($timeout)->toBe(45);
    expect($timeout)->toBeInt(); // Should be converted to int
});

it('validates configuration through usage', function () {
    // Arrange
    $config = new Configuration([
        'api_key' => 'validation-test-key',
    ]);

    // Act & Assert - All getter methods should work without throwing exceptions
    expect($config->getApiKey())->toBeString();
    expect($config->getBaseUrl())->toBeString();
    expect($config->getTimeout())->toBeInt();
    expect($config->getDefaultHeaders())->toBeArray();
    
    // Should not throw any exceptions
    expect(fn() => $config->getApiKey())->not->toThrow(Exception::class);
    expect(fn() => $config->getBaseUrl())->not->toThrow(Exception::class);
    expect(fn() => $config->getTimeout())->not->toThrow(Exception::class);
    expect(fn() => $config->getDefaultHeaders())->not->toThrow(Exception::class);
});
