<?php

declare(strict_types=1);

namespace OpenRouterSDK\Exceptions;

/**
 * Exception for data validation errors
 */
class ValidationException extends OpenRouterException
{
    private array $errors;
    private string $context = '';

    /**
     * Create validation exception with error details
     */
    public function __construct(string $message, array $errors = [], string $context = '')
    {
        parent::__construct($message);
        $this->errors = $errors;
        $this->context = $context;
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get validation context
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * Get formatted error report
     */
    public function getErrorReport(): string
    {
        $report = "Validation Exception Report\n";
        $report .= str_repeat("=", 50) . "\n";
        $report .= "Message: " . $this->getMessage() . "\n";
        $report .= "Context: " . $this->context . "\n";
        $report .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        
        if (!empty($this->errors)) {
            $report .= "\nValidation Errors:\n";
            foreach ($this->errors as $field => $error) {
                $report .= "  • {$field}: {$error}\n";
            }
        }
        
        return $report;
    }
}