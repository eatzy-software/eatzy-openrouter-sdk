<?php

declare(strict_types=1);

namespace OpenRouterSDK\DTOs\Chat;

use OpenRouterSDK\Support\DataTransferObject;

/**
 * Chat completion request DTO matching OpenRouter API schema exactly
 */
class ChatCompletionRequest extends DataTransferObject
{
    /** @var ChatMessage[] */
    public readonly array $messages;
    public ?string $model = null;
    public mixed $response_format = null;
    public bool $stream = false;
    public mixed $tools = null;
    public mixed $tool_choice = null;
    public ?int $max_tokens = null;
    public ?float $temperature = null;
    public ?float $top_p = null;
    public ?int $top_k = null;
    public ?float $frequency_penalty = null;
    public ?float $presence_penalty = null;
    public ?float $repetition_penalty = null;
    public mixed $stop = null;
    public ?array $logit_bias = null;
    public ?int $seed = null;
    public ?string $user = null;

    /**
     * Create chat completion request
     *
     * @param ChatMessage[] $messages Array of chat messages
     * @param string|null $model Model to use
     * @param mixed $response_format Response format configuration
     * @param bool $stream Enable streaming
     * @param mixed $tools Tools configuration
     * @param mixed $tool_choice Tool choice configuration
     * @param int|null $max_tokens Maximum tokens to generate
     * @param float|null $temperature Temperature for sampling
     * @param float|null $top_p Top-p sampling parameter
     * @param int|null $top_k Top-k sampling parameter
     * @param float|null $frequency_penalty Frequency penalty
     * @param float|null $presence_penalty Presence penalty
     * @param float|null $repetition_penalty Repetition penalty
     * @param mixed $stop Stop sequences
     * @param array|null $logit_bias Logit bias
     * @param int|null $seed Random seed
     * @param string|null $user User identifier
     */
    public function __construct(
        array $messages,
        ?string $model = null,
        mixed $response_format = null,
        bool $stream = false,
        mixed $tools = null,
        mixed $tool_choice = null,
        ?int $max_tokens = null,
        ?float $temperature = null,
        ?float $top_p = null,
        ?int $top_k = null,
        ?float $frequency_penalty = null,
        ?float $presence_penalty = null,
        ?float $repetition_penalty = null,
        mixed $stop = null,
        ?array $logit_bias = null,
        ?int $seed = null,
        ?string $user = null
    ) {
        parent::__construct([
            'messages' => $messages,
            'model' => $model,
            'response_format' => $response_format,
            'stream' => $stream,
            'tools' => $tools,
            'tool_choice' => $tool_choice,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'top_p' => $top_p,
            'top_k' => $top_k,
            'frequency_penalty' => $frequency_penalty,
            'presence_penalty' => $presence_penalty,
            'repetition_penalty' => $repetition_penalty,
            'stop' => $stop,
            'logit_bias' => $logit_bias,
            'seed' => $seed,
            'user' => $user,
        ]);
    }

    /**
     * Validate request properties
     */
    protected function validate(): void
    {
        $this->assertNotEmpty($this->messages, 'messages');

        foreach ($this->messages as $message) {
            if (!$message instanceof ChatMessage) {
                throw new \InvalidArgumentException('All messages must be ChatMessage instances');
            }
        }

        if ($this->max_tokens !== null) {
            $this->assertInRange($this->max_tokens, 1, PHP_INT_MAX, 'max_tokens');
        }

        if ($this->temperature !== null) {
            $this->assertInRange($this->temperature, 0, 2, 'temperature');
        }

        if ($this->top_p !== null) {
            $this->assertInRange($this->top_p, 0, 1, 'top_p');
        }

        if ($this->top_k !== null) {
            $this->assertInRange($this->top_k, 1, PHP_INT_MAX, 'top_k');
        }

        if ($this->frequency_penalty !== null) {
            $this->assertInRange($this->frequency_penalty, -2, 2, 'frequency_penalty');
        }

        if ($this->presence_penalty !== null) {
            $this->assertInRange($this->presence_penalty, -2, 2, 'presence_penalty');
        }

        if ($this->repetition_penalty !== null) {
            $this->assertInRange($this->repetition_penalty, 0, 2, 'repetition_penalty');
        }
    }

    /**
     * Enable streaming for this request
     */
    public function withStreaming(): self
    {
        $data = $this->toArray();
        $data['stream'] = true;
        return self::fromArray($data);
    }

    /**
     * Set model for this request
     */
    public function withModel(string $model): self
    {
        $data = $this->toArray();
        $data['model'] = $model;
        return self::fromArray($data);
    }

    /**
     * Set temperature for this request
     */
    public function withTemperature(float $temperature): self
    {
        $data = $this->toArray();
        $data['temperature'] = $temperature;
        return self::fromArray($data);
    }

    /**
     * Set max tokens for this request
     */
    public function withMaxTokens(int $maxTokens): self
    {
        $data = $this->toArray();
        $data['max_tokens'] = $maxTokens;
        return self::fromArray($data);
    }

    /**
     * Map raw API response data to ChatCompletionRequest instance
     */
    public static function map(array $data): static
    {
        $messages = array_map(
            fn(array $messageData) => ChatMessage::map($messageData),
            $data['messages'] ?? []
        );

        $responseFormat = null;
        if (isset($data['response_format'])) {
            $responseFormat = is_array($data['response_format']) 
                ? ResponseFormat::map($data['response_format'])
                : $data['response_format'];
        }

        return new static(
            $messages,
            $data['model'] ?? null,
            $responseFormat,
            $data['stream'] ?? false,
            $data['tools'] ?? null,
            $data['tool_choice'] ?? null,
            $data['max_tokens'] ?? null,
            $data['temperature'] ?? null,
            $data['top_p'] ?? null,
            $data['top_k'] ?? null,
            $data['frequency_penalty'] ?? null,
            $data['presence_penalty'] ?? null,
            $data['repetition_penalty'] ?? null,
            $data['stop'] ?? null,
            $data['logit_bias'] ?? null,
            $data['seed'] ?? null,
            $data['user'] ?? null
        );
    }
}