<?php

declare(strict_types=1);

namespace OpenRouterSDK\Models\Chat;

use OpenRouterSDK\Support\DataTransferObject;

/**
 * Chat completion response model
 */
class ChatCompletionResponse extends DataTransferObject
{
    public readonly string $id;
    public readonly string $object;
    public readonly int $created;
    public readonly string $model;
    /** @var ChatChoice[] */
    public readonly array $choices;
    public ?\OpenRouterSDK\Models\Chat\ChatUsage $usage = null;
    public ?array $system_fingerprint = null;

    /**
     * Create chat completion response
     */
    public function __construct(
        string $id,
        string $object,
        int $created,
        string $model,
        array $choices,
        ?\OpenRouterSDK\Models\Chat\ChatUsage $usage = null,
        ?array $system_fingerprint = null
    ) {
        parent::__construct([
            'id' => $id,
            'object' => $object,
            'created' => $created,
            'model' => $model,
            'choices' => $choices,
            'usage' => $usage,
            'system_fingerprint' => $system_fingerprint,
        ]);
    }

    /**
     * Get the first choice's message content
     */
    public function getContent(): string
    {
        if (empty($this->choices)) {
            return '';
        }

        $message = $this->choices[0]->message;
        return is_string($message->content) ? $message->content : '';
    }

    /**
     * Get usage statistics
     */
    public function getUsage(): ?\OpenRouterSDK\Models\Chat\ChatUsage
    {
        return $this->usage;
    }
}