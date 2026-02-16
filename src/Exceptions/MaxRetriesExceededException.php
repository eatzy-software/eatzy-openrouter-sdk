<?php

declare(strict_types=1);

namespace OpenRouterSDK\Exceptions;

/**
 * Exception thrown when maximum retry attempts are exceeded
 */
class MaxRetriesExceededException extends OpenRouterException
{
    private int $maxAttempts;
    private ?\Exception $lastException;

    /**
     * Create max retries exception
     */
    public function __construct(int $maxAttempts, string $message = '', int $code = 0, ?\Exception $previous = null)
    {
        $message = $message ?: "Maximum retry attempts ({$maxAttempts}) exceeded";
        
        // Store the last exception that caused the failure
        $this->lastException = $previous;
        
        // Enhance the message with the underlying error if available
        if ($previous && empty($message)) {
            $message = "Maximum retry attempts ({$maxAttempts}) exceeded. Last error: " . $previous->getMessage();
        } elseif ($previous && strpos($message, 'Last error:') === false) {
            $message .= ". Last error: " . $previous->getMessage();
        }
        
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

    /**
     * Get the last exception that caused the retry failure
     */
    public function getLastException(): ?\Exception
    {
        return $this->lastException;
    }

    /**
     * Get detailed error report
     */
    public function getErrorReport(): string
    {
        $report = "Max Retries Exceeded Report\n";
        $report .= str_repeat("=", 50) . "\n";
        $report .= "Max Attempts: {$this->maxAttempts}\n";
        $report .= "Main Error: " . $this->getMessage() . "\n";
        $report .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        
        if ($this->lastException) {
            $report .= "\nLast Exception Details:\n";
            $report .= "  Type: " . get_class($this->lastException) . "\n";
            $report .= "  Message: " . $this->lastException->getMessage() . "\n";
            $report .= "  File: " . $this->lastException->getFile() . "\n";
            $report .= "  Line: " . $this->lastException->getLine() . "\n";
            
            // Try to extract HTTP status code if it's a request exception
            if (method_exists($this->lastException, 'getResponse') && $this->lastException->getResponse()) {
                $statusCode = $this->lastException->getResponse()->getStatusCode();
                $report .= "  HTTP Status: {$statusCode}\n";
                
                // Try to get response body for more context
                try {
                    $body = $this->lastException->getResponse()->getBody()->getContents();
                    if (!empty($body)) {
                        $report .= "  Response Body: " . substr($body, 0, 500) . (strlen($body) > 500 ? '...' : '') . "\n";
                    }
                } catch (\Exception $e) {
                    // Ignore if we can't read the body
                }
            }
        }
        
        return $report;
    }
}