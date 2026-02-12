<?php

declare(strict_types=1);

namespace OpenRouterSDK\Http\Streaming;

use OpenRouterSDK\Exceptions\ValidationException;
use Psr\Http\Message\StreamInterface;

/**
 * Server-Sent Events parser for streaming responses
 * 
 * Implements SSE specification compliant parsing:
 * - Reads stream line-by-line until blank line (event boundary)
 * - Combines multiple data: lines within single event
 * - Properly handles [DONE] marker and usage chunks
 * - Ignores SSE comments (lines starting with :)
 * - Tolerant of malformed JSON without breaking stream
 * - Handles mid-stream errors per OpenRouter specification
 */
class SSEParser
{
    /**
     * Parse raw SSE stream according to SSE specification
     *
     * @param StreamInterface $stream PSR-7 stream to parse
     * @param callable $onChunk Callback for each parsed chunk
     * @param callable|null $onComplete Callback when stream completes
     * @throws ValidationException When stream errors occur
     */
    public function parseStream(
        StreamInterface $stream,
        callable $onChunk,
        ?callable $onComplete = null
    ): void {
        $eventLines = [];
        $completed = false;

        while (!$stream->eof()) {
            $line = $this->readLine($stream);

            // Event boundary (empty line)
            if ($line === '') {
                if (!empty($eventLines)) {
                    $completed = $this->dispatchEvent($eventLines, $onChunk, $onComplete) || $completed;
                    $eventLines = [];
                }
                continue;
            }

            $eventLines[] = $line;
        }

        // Flush trailing event
        if (!empty($eventLines)) {
            $completed = $this->dispatchEvent($eventLines, $onChunk, $onComplete) || $completed;
        }

        if (!$completed && $onComplete !== null) {
            $onComplete();
        }
    }

    private function dispatchEvent(
        array $lines,
        callable $onChunk,
        ?callable $onComplete
    ): bool {
        $dataBuffer = [];

        foreach ($lines as $line) {
            // Ignore comments
            if (str_starts_with($line, ':')) {
                continue;
            }

            if (str_starts_with($line, 'data:')) {
                $dataBuffer[] = ltrim(substr($line, 5));
            }
        }

        if (empty($dataBuffer)) {
            return false;
        }

        // [DONE] marker
        if (count($dataBuffer) === 1 && trim($dataBuffer[0]) === '[DONE]') {
            if ($onComplete !== null) {
                $onComplete();
            }
            return true;
        }

        // IMPORTANT: SSE spec requires newline join
        $payload = implode("\n", $dataBuffer);

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Ignore partial JSON fragments
            return false;
        }

        // Mid-stream error per OpenRouter
        if (isset($decoded['error'])) {
            $message = $decoded['error']['message'] ?? 'Unknown stream error';
            throw new ValidationException("Stream error: {$message}");
        }

        if (($decoded['choices'][0]['finish_reason'] ?? null) === 'error') {
            throw new ValidationException('Stream terminated due to finish_reason=error');
        }

        $onChunk($decoded);
        return false;
    }

    /**
     * Proper CRLF-safe line reader
     */
    private function readLine(StreamInterface $stream): string
    {
        $buffer = '';

        while (!$stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            if ($char !== "\r") {
                $buffer .= $char;
            }
        }

        return $buffer;
    }
}
