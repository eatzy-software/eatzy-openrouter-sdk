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
    public readonly int $cost;
    public readonly bool $is_byok;
    public ?array $prompt_tokens_details = null;
    public ?array $cost_details = null;
    public ?array $completion_tokens_details = null;

    /**
     * Create usage statistics
     */
    public function __construct(
        int $prompt_tokens,
        int $completion_tokens,
        int $total_tokens,
        int $cost,
        bool $is_byok,
        ?array $prompt_tokens_details = null,
        ?array $cost_details = null,
        ?array $completion_tokens_details = null
    ) {
        parent::__construct([
            'prompt_tokens' => $prompt_tokens,
            'completion_tokens' => $completion_tokens,
            'total_tokens' => $total_tokens,
            'cost' => $cost,
            'is_byok' => $is_byok,
            'prompt_tokens_details' => $prompt_tokens_details,
            'cost_details' => $cost_details,
            'completion_tokens_details' => $completion_tokens_details,
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
            $data['total_tokens'],
            $data['cost'] ?? 0,
            $data['is_byok'] ?? false,
            $data['prompt_tokens_details'] ?? null,
            $data['cost_details'] ?? null,
            $data['completion_tokens_details'] ?? null
        );
    }
}