<?php

declare(strict_types=1);

namespace OpenRouterSDK\Exceptions;

use Exception;

/**
 * Base exception for all OpenRouter SDK exceptions
 */
class OpenRouterException extends Exception
{
    /**
     * Create exception with message and optional previous exception
     */
    public function __construct(string $message, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}