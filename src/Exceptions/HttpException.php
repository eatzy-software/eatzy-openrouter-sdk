<?php

declare(strict_types=1);

namespace OpenRouterSDK\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * Exception for HTTP-related errors
 */
class HttpException extends OpenRouterException
{
    private ResponseInterface $response;

    /**
     * Create HTTP exception with response details
     */
    public function __construct(
        string $message,
        ResponseInterface $response,
        int $code = 0,
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * Get the HTTP response that caused this exception
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * Get response body as string
     */
    public function getResponseBody(): string
    {
        return (string) $this->response->getBody();
    }
}