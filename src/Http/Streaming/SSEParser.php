<?php

declare(strict_types=1);

namespace OpenRouterSDK\Http\Streaming;

use Psr\Http\Message\StreamInterface;

/**
 * Server-Sent Events parser for streaming responses
 */
class SSEParser
{
    /**
     * Parse SSE stream and call callbacks for each chunk
     */
    public function parseStream(
        StreamInterface $stream,
        callable $onChunk,
        ?callable $onComplete = null
    ): void {
        $buffer = '';

        while (!$stream->eof()) {
            $buffer .= $stream->read(1024);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $chunk = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                if (trim($chunk) === '') {
                    continue;
                }

                // Parse SSE format: data: {"key": "value"}
                if (preg_match('/^data:\s*(.+)$/m', $chunk, $matches)) {
                    $data = trim($matches[1]);

                    if ($data === '[DONE]') {
                        if ($onComplete) {
                            $onComplete();
                        }
                        return;
                    }

                    $jsonData = json_decode($data, true);
                    if ($jsonData === null && json_last_error() !== JSON_ERROR_NONE) {
                        // Handle JSON parsing error
                        throw new \OpenRouterSDK\Exceptions\ValidationException(
                            'Failed to parse JSON in SSE stream: ' . json_last_error_msg()
                        );
                    }
                    if ($jsonData) {
                        $onChunk($jsonData);
                    }
                }
            }
        }

        // Handle any remaining data in buffer
        if (!empty($buffer) && preg_match('/^data:\s*(.+)$/m', $buffer, $matches)) {
            $data = trim($matches[1]);
            if ($data !== '[DONE]') {
                $jsonData = json_decode($data, true);
                if ($jsonData === null && json_last_error() !== JSON_ERROR_NONE) {
                    // Handle JSON parsing error
                    throw new \OpenRouterSDK\Exceptions\ValidationException(
                        'Failed to parse JSON in SSE stream buffer: ' . json_last_error_msg()
                    );
                }
                if ($jsonData) {
                    $onChunk($jsonData);
                }
            }
        }

        if ($onComplete) {
            $onComplete();
        }
    }
}