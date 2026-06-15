<?php

namespace App\Services\AlertMessage;

use App\Concerns\ProvidesDefaultChannelMessages;
use App\Interfaces\Messageable;

/**
 * @internal Wraps a stored alert payload for legacy placeholder rendering.
 */
final class LegacyPayloadMessageable implements Messageable
{
    use ProvidesDefaultChannelMessages;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    public function defaultMessage(): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }
}
