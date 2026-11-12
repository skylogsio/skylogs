<?php

namespace Tests\Support\Messageables;

use App\Concerns\ProvidesChannelMessages;
use App\Interfaces\Messageable;

/**
 * Non-Eloquent payload with public fields visible to Arr::dot((array) $alert).
 */
final class StructuredPayloadMessageable implements Messageable
{
    use ProvidesChannelMessages;

    public function __construct(
        public string $instance = 'worker-1',
    ) {}

    public function defaultMessage(): string
    {
        return 'd';
    }
}
