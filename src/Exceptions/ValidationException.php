<?php

declare(strict_types=1);

namespace OpenRouterSDK\Exceptions;

/**
 * Exception for data validation errors
 */
class ValidationException extends OpenRouterException
{
    private array $errors;

    /**
     * Create validation exception with error details
     */
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}