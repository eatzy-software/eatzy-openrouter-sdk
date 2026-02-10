<?php

declare(strict_types=1);

namespace OpenRouterSDK\Exceptions;

/**
 * Exception thrown when maximum retry attempts are exceeded
 */
class MaxRetriesExceededException extends OpenRouterException
{
    private int $maxAttempts;

    /**
     * Create max retries exception
     */
    public function __construct(int $maxAttempts, string $message = '', int $code = 0, ?\Exception $previous = null)
    {
        $message = $message ?: "Maximum retry attempts ({$maxAttempts}) exceeded";
        parent::__construct($message, $code, $previous);
        $this->maxAttempts = $maxAttempts;
    }

    /**
     * Get maximum attempts that were exceeded
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }
}