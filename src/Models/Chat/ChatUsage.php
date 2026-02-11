<?php

declare(strict_types=1);

namespace OpenRouterSDK\Models\Chat;

use OpenRouterSDK\Support\DataTransferObject;

/**
 * Chat completion usage statistics
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
}