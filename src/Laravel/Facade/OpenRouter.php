<?php

declare(strict_types=1);

namespace OpenRouterSDK\Laravel\Facade;

use Illuminate\Support\Facades\Facade;
use OpenRouterSDK\Contracts\ChatServiceInterface;

/**
 * OpenRouter Facade for Laravel
 */
class OpenRouter extends Facade
{
    /**
     * Get the registered name of the component
     */
    protected static function getFacadeAccessor(): string
    {
        return ChatServiceInterface::class;
    }
}