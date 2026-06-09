<?php

namespace App\Services;

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

    public function defaultMessage(): mixed
    {
        return $this->payload->defaultMessage();
    }

    public function telegram(): mixed
    {
        return $this->payload->telegram();
    }

    public function matterMostMessage(): mixed
    {
        return $this->payload->matterMostMessage();
    }

    public function teamsMessage(): mixed
    {
        return $this->payload->teamsMessage();
    }

    public function smsMessage(): mixed
    {
        return $this->payload->smsMessage();
    }

    public function discordMessage(): mixed
    {
        return $this->payload->discordMessage();
    }

    public function callMessage(): mixed
    {
        return $this->payload->callMessage();
    }

    public function emailMessage(): mixed
    {
        return $this->payload->emailMessage();
    }
}
