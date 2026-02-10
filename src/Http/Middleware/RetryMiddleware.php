<?php

declare(strict_types=1);

namespace OpenRouterSDK\Http\Middleware;

use GuzzleHttp\Exception\RequestException;
use OpenRouterSDK\Exceptions\MaxRetriesExceededException;

/**
 * Retry middleware for handling failed requests
 */
class RetryMiddleware
{
    private int $maxAttempts;
    private int $backoffMs;

    /**
     * Create retry middleware
     */
    public function __construct(int $maxAttempts = 3, int $backoffMs = 1000)
    {
        $this->maxAttempts = $maxAttempts;
        $this->backoffMs = $backoffMs;
    }

    /**
     * Handle request with retry logic
     */
    public function handle(callable $handler, string $method, string $uri, array $options = []): array
    {
        $attempt = 0;

        while ($attempt < $this->maxAttempts) {
            try {
                return $handler($method, $uri, $options);
            } catch (\Exception $e) {
                $attempt++;

                // Don't retry on client errors (4xx)
                if ($this->isClientError($e)) {
                    throw $e;
                }

                // Don't retry if we've exhausted attempts
                if ($attempt >= $this->maxAttempts) {
                    throw new MaxRetriesExceededException(
                        $this->maxAttempts,
                        "Max retries ({$this->maxAttempts}) exceeded",
                        0,
                        $e
                    );
                }

                // Exponential backoff
                $delay = $this->backoffMs * pow(2, $attempt - 1);
                usleep($delay * 1000);
            }
        }

        throw new MaxRetriesExceededException($this->maxAttempts);
    }

    /**
     * Check if exception represents a client error (4xx)
     */
    private function isClientError(\Exception $exception): bool
    {
        // Check if it's a RequestException with response
        if ($exception instanceof RequestException && $exception->getResponse()) {
            $statusCode = $exception->getResponse()->getStatusCode();
            return $statusCode >= 400 && $statusCode < 500;
        }

        return false;
    }
}