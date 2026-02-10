<?php

declare(strict_types=1);

namespace OpenRouterSDK\Tests\Unit\Support;

use OpenRouterSDK\Support\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * Test Configuration functionality
 */
class ConfigurationTest extends TestCase
{
    public function test_creates_default_configuration(): void
    {
        $config = new Configuration();

        $this->assertEquals('', $config->getApiKey());
        $this->assertEquals('https://openrouter.ai/api/v1', $config->getBaseUrl());
        $this->assertEquals(30, $config->getTimeout());
        $this->assertNull($config->getDefaultModel());
    }

    public function test_creates_configuration_with_custom_values(): void
    {
        $customConfig = [
            'api_key' => 'sk-test-123',
            'base_url' => 'https://custom.api.com/v1',
            'timeout' => 60,
            'default_model' => 'anthropic/claude-3',
            'headers' => [
                'HTTP-Referer' => 'https://myapp.com',
                'X-Title' => 'My App',
            ],
        ];

        $config = new Configuration($customConfig);

        $this->assertEquals('sk-test-123', $config->getApiKey());
        $this->assertEquals('https://custom.api.com/v1', $config->getBaseUrl());
        $this->assertEquals(60, $config->getTimeout());
        $this->assertEquals('anthropic/claude-3', $config->getDefaultModel());
    }

    public function test_gets_default_headers(): void
    {
        $config = new Configuration([
            'api_key' => 'sk-test-123',
            'headers' => [
                'HTTP-Referer' => 'https://myapp.com',
                'X-Title' => 'My App',
            ],
        ]);

        $headers = $config->getDefaultHeaders();

        $expected = [
            'Authorization' => 'Bearer sk-test-123',
            'Content-Type' => 'application/json',
            'HTTP-Referer' => 'https://myapp.com',
            'X-Title' => 'My App',
        ];

        $this->assertEquals($expected, $headers);
    }

    public function test_trims_base_url_slash(): void
    {
        $config = new Configuration([
            'base_url' => 'https://api.example.com/v1/',
        ]);

        $this->assertEquals('https://api.example.com/v1', $config->getBaseUrl());
    }

    public function test_converts_to_array(): void
    {
        $customConfig = [
            'api_key' => 'sk-test-123',
            'timeout' => 45,
        ];

        $config = new Configuration($customConfig);
        $result = $config->toArray();

        $this->assertArrayHasKey('api_key', $result);
        $this->assertArrayHasKey('base_url', $result);
        $this->assertArrayHasKey('timeout', $result);
        $this->assertArrayHasKey('default_model', $result);
        $this->assertArrayHasKey('headers', $result);
        $this->assertEquals('sk-test-123', $result['api_key']);
        $this->assertEquals(45, $result['timeout']);
    }
}