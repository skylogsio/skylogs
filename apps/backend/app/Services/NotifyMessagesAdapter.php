<?php

namespace App\Services;

use App\Enums\EndpointType;
use App\Interfaces\Messageable;
use App\Support\NotifyMessagePayload;

/**
 * Messageable wrapper around a stored notify messages payload.
 */
class NotifyMessagesAdapter implements Messageable
{
    private NotifyMessagePayload $payload;

    /**
     * @param  array<string, mixed>  $messages
     */
    public function __construct(array $messages)
    {
        $this->payload = NotifyMessagePayload::fromStored($messages);
    }

    public function defaultMessage(): string
    {
        return $this->payload->defaultMessage();
    }

    /**
     * @return array<string, mixed>|string|null
     */
    public function messageFor(EndpointType $type): array|string|null
    {
        return $this->payload->messageFor($type);
    }
}
