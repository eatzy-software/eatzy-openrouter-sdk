<?php

declare(strict_types=1);

namespace OpenRouterSDK\DTOs\Chat;

use OpenRouterSDK\Support\DataTransferObject;

/**
 * Chat completion usage statistics DTO
 */
class ChatUsage extends DataTransferObject
{
    public readonly int $prompt_tokens;
    public readonly int $completion_tokens;
    public readonly int $total_tokens;

    /**
     * Create usage statistics
     */
    public function __construct(
        int $prompt_tokens,
        int $completion_tokens,
        int $total_tokens
    ) {
        parent::__construct([
            'prompt_tokens' => $prompt_tokens,
            'completion_tokens' => $completion_tokens,
            'total_tokens' => $total_tokens,
        ]);
    }

    /**
     * Map raw API response data to ChatUsage instance
     */
    public static function map(array $data): static
    {
        return new static(
            $data['prompt_tokens'],
            $data['completion_tokens'],
            $data['total_tokens']
        );
    }
}