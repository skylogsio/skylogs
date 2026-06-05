<?php

namespace App\Services;

use App\Interfaces\Messageable;

/**
 * Messageable wrapper around a pre-composed notify messages array.
 */
class NotifyMessagesAdapter implements Messageable
{
    /**
     * @param  array<string, mixed>  $messages
     */
    public function __construct(private array $messages) {}

    public function defaultMessage(): mixed
    {
        return $this->messages['defaultMessage'] ?? '';
    }

    public function telegram(): mixed
    {
        return $this->messages['telegram'] ?? $this->defaultMessage();
    }

    public function matterMostMessage(): mixed
    {
        return $this->messages['matterMostMessage'] ?? $this->defaultMessage();
    }

    public function teamsMessage(): mixed
    {
        return $this->messages['teamsMessage'] ?? $this->defaultMessage();
    }

    public function smsMessage(): mixed
    {
        return $this->messages['smsMessage'] ?? $this->defaultMessage();
    }

    public function discordMessage(): mixed
    {
        return $this->messages['discordMessage'] ?? $this->defaultMessage();
    }

    public function callMessage(): mixed
    {
        return $this->messages['callMessage'] ?? $this->defaultMessage();
    }

    public function emailMessage(): mixed
    {
        return $this->messages['emailMessage'] ?? $this->defaultMessage();
    }
}
