<?php

declare(strict_types=1);

namespace OpenRouterSDK\DTOs\Chat;

use OpenRouterSDK\Support\DataTransferObject;

/**
 * Response format configuration for structured outputs
 */
class ResponseFormat extends DataTransferObject
{
    public const TYPE_JSON_OBJECT = 'json_object';
    public const TYPE_JSON_SCHEMA = 'json_schema';

    public readonly string $type;
    public ?array $json_schema = null;

    /**
     * Create response format configuration
     *
     * @param string $type Response format type (json_object or json_schema)
     * @param array|null $json_schema JSON schema definition (required for json_schema type)
     */
    public function __construct(string $type, ?array $json_schema = null)
    {
        parent::__construct([
            'type' => $type,
            'json_schema' => $json_schema,
        ]);
    }

    /**
     * Validate response format properties
     */
    protected function validate(): void
    {
        $this->assertInArray($this->type, [
            self::TYPE_JSON_OBJECT,
            self::TYPE_JSON_SCHEMA,
        ], 'type');

        if ($this->type === self::TYPE_JSON_SCHEMA) {
            $this->assertNotEmpty($this->json_schema, 'json_schema');
        }
    }

    /**
     * Create JSON object response format
     */
    public static function jsonObject(): self
    {
        return new self(self::TYPE_JSON_OBJECT);
    }

    /**
     * Create JSON schema response format
     */
    public static function jsonSchema(array $schema): self
    {
        return new self(self::TYPE_JSON_SCHEMA, $schema);
    }

    /**
     * Map raw API response data to ResponseFormat instance
     */
    public static function map(array $data): static
    {
        return new static(
            $data['type'],
            $data['json_schema'] ?? null
        );
    }
}