<?php

declare(strict_types=1);

namespace OpenRouterSDK\Support;

use OpenRouterSDK\Contracts\ConfigurationInterface;
use OpenRouterSDK\Exceptions\ValidationException;

/**
 * Configuration Validator
 * 
 * Validates OpenRouter SDK configuration parameters to ensure
 * they meet required formats and constraints.
 */
class ConfigurationValidator
{
    /**
     * Validate configuration parameters
     *
     * @param array $config Configuration array to validate
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $config): void
    {
        $errors = [];

        // Validate API key
        if (!isset($config['api_key'])) {
            $errors['api_key'] = 'API key is required';
        } elseif (!self::isValidApiKey($config['api_key'])) {
            $errors['api_key'] = 'Invalid API key format. Expected: sk-or-... or sk-...';
        }

        // Validate base URL
        if (isset($config['base_url']) && !filter_var($config['base_url'], FILTER_VALIDATE_URL)) {
            $errors['base_url'] = 'Invalid base URL format';
        }

        // Validate timeout
        if (isset($config['timeout'])) {
            if (!is_numeric($config['timeout'])) {
                $errors['timeout'] = 'Timeout must be a numeric value';
            } elseif ($config['timeout'] < 1 || $config['timeout'] > 300) {
                $errors['timeout'] = 'Timeout must be between 1 and 300 seconds';
            }
        }

        // Validate default model format
        if (isset($config['default_model']) && !empty($config['default_model'])) {
            if (!self::isValidModelName($config['default_model'])) {
                $errors['default_model'] = 'Invalid model name format. Expected: provider/model-name';
            }
        }

        // Validate retry configuration
        if (isset($config['retry'])) {
            if (!is_array($config['retry'])) {
                $errors['retry'] = 'Retry configuration must be an array';
            } else {
                if (isset($config['retry']['max_attempts']) && 
                    (!is_int($config['retry']['max_attempts']) || $config['retry']['max_attempts'] < 0)) {
                    $errors['retry.max_attempts'] = 'Max attempts must be a positive integer';
                }
                
                if (isset($config['retry']['backoff_ms']) && 
                    (!is_int($config['retry']['backoff_ms']) || $config['retry']['backoff_ms'] < 0)) {
                    $errors['retry.backoff_ms'] = 'Backoff milliseconds must be a positive integer';
                }
            }
        }

        // Validate headers
        if (isset($config['headers']) && !is_array($config['headers'])) {
            $errors['headers'] = 'Headers must be an array';
        }

        if (!empty($errors)) {
            throw new ValidationException('Configuration validation failed', $errors);
        }
    }

    /**
     * Validate configuration interface implementation
     *
     * @param ConfigurationInterface $config Configuration instance to validate
     * @return void
     * @throws ValidationException
     */
    public static function validateConfiguration(ConfigurationInterface $config): void
    {
        $errors = [];

        // Validate API key
        if (empty($config->getApiKey())) {
            $errors['api_key'] = 'API key is required';
        } elseif (!self::isValidApiKey($config->getApiKey())) {
            $errors['api_key'] = 'Invalid API key format';
        }

        // Validate base URL
        if (!filter_var($config->getBaseUrl(), FILTER_VALIDATE_URL)) {
            $errors['base_url'] = 'Invalid base URL format';
        }

        // Validate timeout bounds
        $timeout = $config->getTimeout();
        if ($timeout < 1 || $timeout > 300) {
            $errors['timeout'] = 'Timeout must be between 1 and 300 seconds';
        }

        // Validate default model if set
        $defaultModel = $config->getDefaultModel();
        if (!empty($defaultModel) && !self::isValidModelName($defaultModel)) {
            $errors['default_model'] = 'Invalid model name format';
        }

        if (!empty($errors)) {
            throw new ValidationException('Configuration validation failed', $errors);
        }
    }

    /**
     * Validate API key format
     *
     * @param string $apiKey API key to validate
     * @return bool
     */
    public static function isValidApiKey(string $apiKey): bool
    {
        // OpenRouter API keys typically start with 'sk-or-' or 'sk-'
        return preg_match('/^sk-(or-)?[a-zA-Z0-9]{32,}$/', $apiKey) === 1;
    }

    /**
     * Validate model name format
     *
     * @param string $modelName Model name to validate
     * @return bool
     */
    public static function isValidModelName(string $modelName): bool
    {
        // Model names should follow format: provider/model-name
        // Examples: openai/gpt-4, anthropic/claude-2, mistralai/mistral-7b-instruct:free
        return preg_match('/^[a-zA-Z0-9]+\/[a-zA-Z0-9\-:.]+$/', $modelName) === 1;
    }

    /**
     * Sanitize configuration values
     *
     * @param array $config Raw configuration array
     * @return array Sanitized configuration
     */
    public static function sanitize(array $config): array
    {
        $sanitized = $config;

        // Trim string values
        foreach ($sanitized as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim($value);
            }
        }

        // Ensure numeric values are properly typed
        if (isset($sanitized['timeout'])) {
            $sanitized['timeout'] = (int) $sanitized['timeout'];
        }

        // Set defaults for retry configuration
        if (!isset($sanitized['retry'])) {
            $sanitized['retry'] = [
                'max_attempts' => 3,
                'backoff_ms' => 1000
            ];
        }

        return $sanitized;
    }

    /**
     * Get validation rules for documentation
     *
     * @return array
     */
    public static function getValidationRules(): array
    {
        return [
            'api_key' => [
                'required' => true,
                'format' => 'sk-[or-]alphanumeric(32+)',
                'example' => 'sk-or-abc123def456ghi789jkl012mno345pqr'
            ],
            'base_url' => [
                'required' => false,
                'default' => 'https://openrouter.ai/api/v1',
                'format' => 'valid URL'
            ],
            'timeout' => [
                'required' => false,
                'default' => 30,
                'range' => '1-300 seconds'
            ],
            'default_model' => [
                'required' => false,
                'format' => 'provider/model-name',
                'examples' => ['openai/gpt-4', 'anthropic/claude-2', 'mistralai/mistral-7b-instruct:free']
            ],
            'retry.max_attempts' => [
                'required' => false,
                'default' => 3,
                'range' => '0-10'
            ],
            'retry.backoff_ms' => [
                'required' => false,
                'default' => 1000,
                'range' => '0-10000'
            ]
        ];
    }
}