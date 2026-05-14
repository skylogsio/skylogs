<?php

namespace Tests\Support\Messageables;

use App\Interfaces\Messageable;

/**
 * Non-Eloquent payload with public fields visible to Arr::dot((array) $alert).
 */
final class StructuredPayloadMessageable implements Messageable
{
    public function __construct(
        public string $instance = 'worker-1',
    ) {}

    public function defaultMessage(): string
    {
        return 'd';
    }

    public function telegram(): array|string
    {
        return 't';
    }

    public function matterMostMessage(): string
    {
        return 'm';
    }

    public function teamsMessage(): string
    {
        return 'tm';
    }

    public function smsMessage(): string
    {
        return 's';
    }

    public function discordMessage(): string
    {
        return 'di';
    }

    public function callMessage(): string
    {
        return 'c';
    }

    public function emailMessage(): string
    {
        return 'e';
    }
}
