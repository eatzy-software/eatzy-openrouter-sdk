<?php

declare(strict_types=1);

namespace OpenRouterSDK\DTOs\Chat;

use OpenRouterSDK\Support\DataTransferObject;

/**
 * Chat message DTO matching OpenRouter API schema exactly
 */
class ChatMessage extends DataTransferObject
{
    public const ROLE_SYSTEM = 'system';
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_TOOL = 'tool';

    public readonly string $role;
    public readonly string|array $content;
    public ?string $name = null;
    public ?string $tool_call_id = null;

    /**
     * Create chat message
     *
     * @param string $role Message role (system, user, assistant, tool)
     * @param string|array $content Message content (string or array of content parts)
     * @param string|null $name Optional name for the participant
     * @param string|null $tool_call_id Tool call ID for tool responses
     */
    public function __construct(
        string $role,
        string|array $content,
        ?string $name = null,
        ?string $tool_call_id = null
    ) {
        parent::__construct([
            'role' => $role,
            'content' => $content,
            'name' => $name,
            'tool_call_id' => $tool_call_id,
        ]);
    }

    /**
     * Validate message properties
     */
    protected function validate(): void
    {
        $this->assertInArray($this->role, [
            self::ROLE_SYSTEM,
            self::ROLE_USER,
            self::ROLE_ASSISTANT,
            self::ROLE_TOOL,
        ], 'role');

        $this->assertNotEmpty($this->content, 'content');
    }

    /**
     * Create system message
     */
    public static function system(string $content, ?string $name = null): self
    {
        return new self(self::ROLE_SYSTEM, $content, $name);
    }

    /**
     * Create user message
     */
    public static function user(string|array $content, ?string $name = null): self
    {
        return new self(self::ROLE_USER, $content, $name);
    }

    /**
     * Create assistant message
     */
    public static function assistant(string $content, ?string $name = null): self
    {
        return new self(self::ROLE_ASSISTANT, $content, $name);
    }

    /**
     * Create tool message
     */
    public static function tool(string $content, string $tool_call_id, ?string $name = null): self
    {
        return new self(self::ROLE_TOOL, $content, $name, $tool_call_id);
    }

    /**
     * Map raw API response data to ChatMessage instance
     */
    public static function map(array $data): static
    {
        return new static(
            $data['role'],
            $data['content'],
            $data['name'] ?? null,
            $data['tool_call_id'] ?? null
        );
    }
}