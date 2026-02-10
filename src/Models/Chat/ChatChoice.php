<?php

declare(strict_types=1);

namespace OpenRouterSDK\Models\Chat;

use OpenRouterSDK\Support\DataTransferObject;

/**
 * Chat completion choice model
 */
class ChatChoice extends DataTransferObject
{
    public readonly int $index;
    public readonly \OpenRouterSDK\Models\Chat\ChatMessage $message;
    public ?string $finish_reason = null;
    public ?array $logprobs = null;

    /**
     * Create chat choice
     */
    public function __construct(
        int $index,
        \OpenRouterSDK\Models\Chat\ChatMessage $message,
        ?string $finish_reason = null,
        ?array $logprobs = null
    ) {
        parent::__construct([
            'index' => $index,
            'message' => $message,
            'finish_reason' => $finish_reason,
            'logprobs' => $logprobs,
        ]);
    }
}