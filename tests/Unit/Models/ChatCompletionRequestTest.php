<?php

declare(strict_types=1);

namespace OpenRouterSDK\Tests\Unit\DTOs;

use OpenRouterSDK\DTOs\Chat\ChatCompletionRequest;
use OpenRouterSDK\DTOs\Chat\ChatMessage;
use OpenRouterSDK\DTOs\Chat\ResponseFormat;
use PHPUnit\Framework\TestCase;

/**
 * Test ChatCompletionRequest model functionality
 */
class ChatCompletionRequestTest extends TestCase
{
    public function test_creates_basic_request(): void
    {
        $messages = [ChatMessage::user('Hello')];
        $request = new ChatCompletionRequest($messages);

        $this->assertEquals($messages, $request->messages);
        $this->assertFalse($request->stream);
        $this->assertNull($request->model);
        $this->assertNull($request->max_tokens);
    }

    public function test_creates_request_with_all_parameters(): void
    {
        $messages = [ChatMessage::user('Hello')];
        $responseFormat = ResponseFormat::jsonObject();

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'openai/gpt-4',
            response_format: $responseFormat,
            stream: true,
            max_tokens: 1000,
            temperature: 0.7,
            top_p: 0.9,
            frequency_penalty: 0.5,
            presence_penalty: 0.5
        );

        $this->assertTrue($request->stream);
        $this->assertEquals('openai/gpt-4', $request->model);
        $this->assertEquals(1000, $request->max_tokens);
        $this->assertEquals(0.7, $request->temperature);
        $this->assertEquals(0.9, $request->top_p);
        $this->assertEquals(0.5, $request->frequency_penalty);
        $this->assertEquals(0.5, $request->presence_penalty);
    }

    public function test_validates_messages_not_empty(): void
    {
        $this->expectException(\OpenRouterSDK\Exceptions\ValidationException::class);
        $this->expectExceptionMessage("Property 'messages' cannot be empty");

        new ChatCompletionRequest([]);
    }

    public function test_validates_temperature_range(): void
    {
        $messages = [ChatMessage::user('Hello')];

        $this->expectException(\OpenRouterSDK\Exceptions\ValidationException::class);
        $this->expectExceptionMessage("Property 'temperature' must be between 0 and 2");

        new ChatCompletionRequest($messages, temperature: 2.5);
    }

    public function test_validates_max_tokens_positive(): void
    {
        $messages = [ChatMessage::user('Hello')];

        $this->expectException(\OpenRouterSDK\Exceptions\ValidationException::class);
        $this->expectExceptionMessage("Property 'max_tokens' must be between 1 and");

        new ChatCompletionRequest($messages, max_tokens: 0);
    }

    public function test_with_streaming_method(): void
    {
        $messages = [ChatMessage::user('Hello')];
        $request = new ChatCompletionRequest($messages);
        $streamingRequest = $request->withStreaming();

        $this->assertFalse($request->stream);
        $this->assertTrue($streamingRequest->stream);
        $this->assertEquals($request->messages, $streamingRequest->messages);
    }

    public function test_with_model_method(): void
    {
        $messages = [ChatMessage::user('Hello')];
        $request = new ChatCompletionRequest($messages);
        $modelRequest = $request->withModel('anthropic/claude-3');

        $this->assertNull($request->model);
        $this->assertEquals('anthropic/claude-3', $modelRequest->model);
    }

    public function test_with_temperature_method(): void
    {
        $messages = [ChatMessage::user('Hello')];
        $request = new ChatCompletionRequest($messages);
        $tempRequest = $request->withTemperature(0.8);

        $this->assertNull($request->temperature);
        $this->assertEquals(0.8, $tempRequest->temperature);
    }

    public function test_converts_to_array(): void
    {
        $messages = [ChatMessage::user('Hello')];
        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'openai/gpt-4',
            temperature: 0.7,
            max_tokens: 1000
        );

        $expected = [
            'messages' => $messages,
            'model' => 'openai/gpt-4',
            'response_format' => null,
            'stream' => false,
            'tools' => null,
            'tool_choice' => null,
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'top_p' => null,
            'top_k' => null,
            'frequency_penalty' => null,
            'presence_penalty' => null,
            'repetition_penalty' => null,
            'stop' => null,
            'logit_bias' => null,
            'seed' => null,
            'user' => null,
        ];

        $this->assertEquals($expected, $request->toArray());
    }
}