<?php

declare(strict_types=1);

namespace OpenRouterSDK\Tests\Unit\Models;

use OpenRouterSDK\Models\Chat\ChatMessage;
use OpenRouterSDK\Models\Chat\ChatCompletionRequest;
use OpenRouterSDK\Models\Chat\ResponseFormat;
use PHPUnit\Framework\TestCase;

/**
 * Test ChatMessage model functionality
 */
class ChatMessageTest extends TestCase
{
    public function test_creates_system_message(): void
    {
        $message = ChatMessage::system('You are a helpful assistant.');

        $this->assertEquals('system', $message->role);
        $this->assertEquals('You are a helpful assistant.', $message->content);
        $this->assertNull($message->name);
        $this->assertNull($message->tool_call_id);
    }

    public function test_creates_user_message(): void
    {
        $message = ChatMessage::user('Hello, how are you?');

        $this->assertEquals('user', $message->role);
        $this->assertEquals('Hello, how are you?', $message->content);
    }

    public function test_creates_assistant_message(): void
    {
        $message = ChatMessage::assistant('I am doing well, thank you!');

        $this->assertEquals('assistant', $message->role);
        $this->assertEquals('I am doing well, thank you!', $message->content);
    }

    public function test_creates_tool_message(): void
    {
        $message = ChatMessage::tool('Tool response data', 'call_123');

        $this->assertEquals('tool', $message->role);
        $this->assertEquals('Tool response data', $message->content);
        $this->assertEquals('call_123', $message->tool_call_id);
    }

    public function test_validates_role_values(): void
    {
        $this->expectException(\OpenRouterSDK\Exceptions\ValidationException::class);
        $this->expectExceptionMessage("Property 'role' must be one of: system, user, assistant, tool");

        new ChatMessage('invalid_role', 'content');
    }

    public function test_validates_empty_content(): void
    {
        $this->expectException(\OpenRouterSDK\Exceptions\ValidationException::class);
        $this->expectExceptionMessage("Property 'content' cannot be empty");

        new ChatMessage('user', '');
    }

    public function test_converts_to_array(): void
    {
        $message = ChatMessage::user('Hello world', 'John');

        $expected = [
            'role' => 'user',
            'content' => 'Hello world',
            'name' => 'John',
            'tool_call_id' => null,
        ];

        $this->assertEquals($expected, $message->toArray());
    }
}