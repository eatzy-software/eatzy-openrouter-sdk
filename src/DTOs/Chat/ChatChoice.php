<?php

declare(strict_types=1);

namespace OpenRouterSDK\DTOs\Chat;

use OpenRouterSDK\Support\DataTransferObject;

/**
 * Chat completion choice DTO
 */
class ChatChoice extends DataTransferObject
{
    public readonly int $index;
    public readonly ChatMessage $message;
    public ?string $finish_reason = null;
    public ?array $logprobs = null;
    public ?string $native_finish_reason = null;

    /**
     * Create chat choice
     */
    public function __construct(
        int $index,
        ChatMessage $message,
        ?string $finish_reason = null,
        ?array $logprobs = null,
        ?string $native_finish_reason = null
    ) {
        parent::__construct([
            'index' => $index,
            'message' => $message,
            'finish_reason' => $finish_reason,
            'logprobs' => $logprobs,
            'native_finish_reason' => $native_finish_reason,
        ]);
    }

    /**
     * Map raw API response data to ChatChoice instance
     */
    public static function map(array $data): static
    {
        $message = ChatMessage::map($data['message']);

        return new static(
            $data['index'],
            $message,
            $data['finish_reason'] ?? null,
            $data['logprobs'] ?? null,
            $data['native_finish_reason'] ?? null
        );
    }
}