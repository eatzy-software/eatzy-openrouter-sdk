<?php

declare(strict_types=1);

namespace OpenRouterSDK\Http\Middleware;

use GuzzleHttp\Exception\RequestException;
use OpenRouterSDK\Exceptions\MaxRetriesExceededException;
use Psr\Http\Message\ResponseInterface;

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

                // Don't retry on client errors (4xx) except rate limiting
                if ($this->isClientError($e) && !$this->isRateLimited($e)) {
                    throw $e;
                }

                // Handle rate limiting with specific delay
                if ($this->isRateLimited($e)) {
                    $delaySeconds = $this->getRateLimitDelay($e);
                    if ($delaySeconds > 0) {
                        sleep($delaySeconds);
                    }
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

    /**
     * Check if exception represents rate limiting (429)
     */
    private function isRateLimited(\Exception $exception): bool
    {
        if ($exception instanceof RequestException && $exception->getResponse()) {
            $statusCode = $exception->getResponse()->getStatusCode();
            return $statusCode === 429;
        }

        return false;
    }

    /**
     * Get delay time from rate limit headers
     */
    private function getRateLimitDelay(\Exception $exception): int
    {
        if (!($exception instanceof RequestException) || !$exception->getResponse()) {
            return 1; // Default 1 second delay
        }

        $response = $exception->getResponse();
        
        // Check for Retry-After header (can be seconds or HTTP date)
        if ($response->hasHeader('Retry-After')) {
            $retryAfter = $response->getHeaderLine('Retry-After');
            if (is_numeric($retryAfter)) {
                return (int) $retryAfter;
            }
            // If it's a date, calculate difference
            $retryTime = strtotime($retryAfter);
            if ($retryTime !== false) {
                return max(1, $retryTime - time());
            }
        }

        // Check for X-RateLimit-Reset header
        if ($response->hasHeader('X-RateLimit-Reset')) {
            $resetTime = (int) $response->getHeaderLine('X-RateLimit-Reset');
            return max(1, $resetTime - time());
        }

        // Default fallback
        return 1;
    }
}