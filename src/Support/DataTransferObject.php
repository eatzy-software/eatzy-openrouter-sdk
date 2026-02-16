<?php

declare(strict_types=1);

namespace OpenRouterSDK\Support;

use OpenRouterSDK\Exceptions\ValidationException;

/**
 * Base Data Transfer Object class for all API DTOs
 * Provides automatic property assignment, validation, and mapping capabilities
 */
abstract class DataTransferObject
{
    /**
     * Create DTO from array data
     */
    public function __construct(array $data = [])
    {
        $this->assignProperties($data);
        $this->validate();
    }

    /**
     * Convert DTO to array representation
     */
    public function toArray(): array
    {
        $vars = get_object_vars($this);
        $result = [];
        
        foreach ($vars as $key => $value) {
            // Skip private/protected properties (those starting with \0)
            if (str_starts_with($key, "\0")) {
                continue;
            }
            
            // Only include properties that have been set and are not null
            // For arrays, include them if they're not empty
            if ($value !== null) {
                if (is_array($value)) {
                    // For arrays, include them if they're not empty
                    if (!empty($value)) {
                        $result[$key] = $this->processArrayValue($value);
                    }
                } else {
                    $result[$key] = $value;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Process array values to handle nested DTOs and remove null values
     */
    private function processArrayValue(array $array): array
    {
        $result = [];
        foreach ($array as $key => $item) {
            if ($item === null) {
                continue; // Skip null values
            }
            if ($item instanceof DataTransferObject) {
                $processed = $item->toArray();
                // Only add if the processed DTO is not empty
                if (!empty($processed)) {
                    $result[$key] = $processed;
                }
            } elseif (is_array($item)) {
                $processed = $this->processArrayValue($item);
                // Only add if the processed array is not empty
                if (!empty($processed)) {
                    $result[$key] = $processed;
                }
            } else {
                $result[$key] = $item;
            }
        }
        return $result;
    }

    /**
     * Create DTO instance from array data
     */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    /**
     * Assign properties from data array
     */
    protected function assignProperties(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Validate the DTO properties
     * Override in child classes for specific validation logic
     */
    protected function validate(): void
    {
        // Default implementation - override in child classes
    }

    /**
     * Assert that a value is not empty
     */
    protected function assertNotEmpty(mixed $value, string $propertyName): void
    {
        if (empty($value)) {
            throw new ValidationException("Property '{$propertyName}' cannot be empty");
        }
    }

    /**
     * Assert that a value is within a range
     */
    protected function assertInRange(float|int $value, float|int $min, float|int $max, string $propertyName): void
    {
        if ($value < $min || $value > $max) {
            throw new ValidationException("Property '{$propertyName}' must be between {$min} and {$max}");
        }
    }

    /**
     * Assert that a value is one of the allowed values
     */
    protected function assertInArray(mixed $value, array $allowedValues, string $propertyName): void
    {
        if (!in_array($value, $allowedValues, true)) {
            $allowedList = implode(', ', $allowedValues);
            throw new ValidationException("Property '{$propertyName}' must be one of: {$allowedList}");
        }
    }

    /**
     * Assert that a value is of expected type
     */
    protected function assertType(mixed $value, string $expectedType, string $propertyName): void
    {
        $actualType = gettype($value);
        
        if ($actualType !== $expectedType && !($expectedType === 'array' && is_array($value))) {
            throw new ValidationException("Property '{$propertyName}' must be of type {$expectedType}, got {$actualType}");
        }
    }

    /**
     * Map raw API response data to DTO instance
     * Must be implemented by concrete DTO classes
     */
    abstract public static function map(array $data): static;

    /**
     * Map array of raw data to array of DTO instances
     */
    public static function mapCollection(array $dataArray): array
    {
        return array_map([static::class, 'map'], $dataArray);
    }
}