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
        $eventBuffer = '';
        
        while (!$stream->eof()) {
            $line = $stream->read(1);
            
            // Append raw byte
            $eventBuffer .= $line;
            
            // Detect end of event (two newlines)
            if (str_ends_with($eventBuffer, "\n\n") || str_ends_with($eventBuffer, "\r\n\r\n")) {
                $this->handleEvent(trim($eventBuffer), $onChunk, $onComplete);
                $eventBuffer = '';
            }
        }
        
        // Remaining event
        if (trim($eventBuffer) !== '') {
            $this->handleEvent(trim($eventBuffer), $onChunk, $onComplete);
        }
        
        // Signal completion if not already called
        if ($onComplete) {
            ($onComplete)();
        }
    }
    
    /**
     * Handle individual SSE event according to specification
     */
    private function handleEvent(string $event, callable $onChunk, ?callable $onComplete): void
    {
        // Handle empty events
        if ($event === '') {
            return;
        }

        // Ignore SSE comments per spec
        if (str_starts_with($event, ':')) {
            return;
        }
        
        // Gather all 'data:' lines
        $dataLines = [];
        foreach (explode("\n", $event) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'data:')) {
                $data = substr($line, 5); // after "data:"
                $dataLines[] = trim($data);
            }
        }
        
        if (empty($dataLines)) {
            return;
        }
        
        // If this is the done marker
        if (count($dataLines) === 1 && $dataLines[0] === '[DONE]') {
            if ($onComplete) {
                ($onComplete)();
            }
            return;
        }
        
        // Combine multi data lines
        $combinedJson = implode('', $dataLines);
        
        // Attempt JSON parse
        try {
            $parsed = json_decode($combinedJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Ignore incomplete / non JSON event
            return;
        }
        
        // Handle usage chunks (final chunks with usage stats)
        if (isset($parsed['usage'])) {
            ($onChunk)($parsed);
            return;
        }
        
        // Mid-stream error handling per OpenRouter spec
        if (isset($parsed['error'])) {
            $errorMessage = $parsed['error']['message'] ?? 'Unknown stream error';
            throw new ValidationException(sprintf('Stream error: %s', $errorMessage));
        }
        
        if (isset($parsed['choices'][0]['finish_reason']) &&
            $parsed['choices'][0]['finish_reason'] === 'error') {
            throw new ValidationException('Stream terminated due to error');
        }
        
        // Process normal content chunks
        ($onChunk)($parsed);
    }
}
