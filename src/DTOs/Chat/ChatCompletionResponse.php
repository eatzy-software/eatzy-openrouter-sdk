<?php

declare(strict_types=1);

namespace OpenRouterSDK\DTOs\Chat;

use OpenRouterSDK\Support\DataTransferObject;

/**
 * Chat completion response DTO
 */
class ChatCompletionResponse extends DataTransferObject
{
    public readonly string $id;
    public readonly string $object;
    public readonly int $created;
    public readonly string $model;
    public readonly string $provider;
    /** @var ChatChoice[] */
    public readonly array $choices;
    public ?ChatUsage $usage = null;
    public ?array $system_fingerprint = null;

    /**
     * Create chat completion response
     */
    public function __construct(
        string $id,
        string $object,
        int $created,
        string $model,
        string $provider,
        array $choices,
        ?ChatUsage $usage = null,
        ?array $system_fingerprint = null
    ) {
        parent::__construct([
            'id' => $id,
            'object' => $object,
            'created' => $created,
            'model' => $model,
            'provider' => $provider,
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
        return is_string($message->content) ? trim($message->content) : '';
    }

    /**
     * Get usage statistics
     */
    public function getUsage(): ?ChatUsage
    {
        return $this->usage;
    }

    /**
     * Map raw API response data to ChatCompletionResponse instance
     */
    public static function map(array $data): static
    {
        $choices = array_map(
            fn(array $choiceData) => ChatChoice::map($choiceData),
            $data['choices'] ?? []
        );

        $usage = isset($data['usage']) ? ChatUsage::map($data['usage']) : null;

        return new static(
            $data['id'],
            $data['object'],
            $data['created'],
            $data['model'],
            $data['provider'] ?? 'unknown',
            $choices,
            $usage,
            $data['system_fingerprint'] ?? null
        );
    }
}