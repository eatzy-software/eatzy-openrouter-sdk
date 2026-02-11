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
        return get_object_vars($this);
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