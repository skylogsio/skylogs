<?php

namespace Tests\Support\Messageables;

use App\Concerns\ProvidesDefaultChannelMessages;
use App\Interfaces\Messageable;

/**
 * Non-Eloquent payload with public fields visible to Arr::dot((array) $alert).
 */
final class StructuredPayloadMessageable implements Messageable
{
    use ProvidesDefaultChannelMessages;

    public function __construct(
        public string $instance = 'worker-1',
    ) {}

    public function defaultMessage(): string
    {
        return 'd';
    }
}
