<?php

namespace App\Interfaces;

use App\Enums\EndpointType;

interface Messageable
{
    public function defaultMessage(): string;

    /**
     * Channel specific content, or null to fall back to defaultMessage().
     *
     * Arrays follow the chat shape `['message' => string, 'meta' => [...]]`,
     * where meta becomes an inline keyboard on channels that support one.
     *
     * @return array<string, mixed>|string|null
     */
    public function messageFor(EndpointType $type): array|string|null;
}
