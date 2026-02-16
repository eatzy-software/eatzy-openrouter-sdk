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
        $lastException = null;

        while ($attempt < $this->maxAttempts) {
            try {
                return $handler($method, $uri, $options);
            } catch (\Exception $e) {
                $attempt++;
                $lastException = $e;

                // Determine if this error is retryable
                if (!$this->isRetryableError($e)) {
                    throw $e;
                }

                // Handle rate limiting with specific delay
                if ($this->isRateLimited($e)) {
                    $delaySeconds = $this->getRateLimitDelay($e);
                    if ($delaySeconds > 0) {
                        sleep($delaySeconds);
                    }
                }

                // If we've exhausted attempts, throw the final exception
                if ($attempt >= $this->maxAttempts) {
                    $errorMessage = "Max retries ({$this->maxAttempts}) exceeded. Last error: " . $e->getMessage();
                    throw new MaxRetriesExceededException(
                        $this->maxAttempts,
                        $errorMessage,
                        0,
                        $e
                    );
                }

                // Exponential backoff with jitter
                $delay = $this->calculateBackoff($attempt);
                usleep($delay * 1000);
            }
        }

        // This should never be reached due to the loop condition, but included for safety
        if ($lastException) {
            throw new MaxRetriesExceededException(
                $this->maxAttempts,
                "Max retries ({$this->maxAttempts}) exceeded after exhausting all attempts",
                0,
                $lastException
            );
        }

        throw new MaxRetriesExceededException($this->maxAttempts);
    }

    /**
     * Determine if an error is retryable
     * Only retry on network errors, server errors (5xx), and rate limiting (429)
     */
    private function isRetryableError(\Exception $exception): bool
    {
        // Always retry network errors and connection issues
        if ($exception instanceof \GuzzleHttp\Exception\ConnectException ||
            $exception instanceof \GuzzleHttp\Exception\TransferException) {
            return true;
        }

        // Check if it's a RequestException with response
        if ($exception instanceof RequestException && $exception->getResponse()) {
            $statusCode = $exception->getResponse()->getStatusCode();
            
            // Retry on server errors (5xx)
            if ($statusCode >= 500) {
                return true;
            }
            
            // Retry on rate limiting (429)
            if ($statusCode === 429) {
                return true;
            }
            
            // Don't retry on client errors (4xx) except 429
            return false;
        }

        // If no response, likely a network or connection issue - retry
        return true;
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
     * Calculate exponential backoff with jitter
     */
    private function calculateBackoff(int $attempt): int
    {
        // Exponential backoff: base_delay * 2^(attempt-1)
        $baseDelay = $this->backoffMs;
        $exponentialDelay = $baseDelay * pow(2, $attempt - 1);
        
        // Add jitter to prevent thundering herd
        $jitter = rand(0, $baseDelay);
        
        return (int) ($exponentialDelay + $jitter);
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