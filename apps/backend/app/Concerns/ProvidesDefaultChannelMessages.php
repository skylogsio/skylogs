<?php

namespace App\Concerns;

trait ProvidesDefaultChannelMessages
{
    public function telegram(): array|string
    {
        return $this->defaultMessage();
    }

    public function baleMessage(): array|string
    {
        return $this->defaultMessage();
    }

    public function matterMostMessage(): string
    {
        return $this->defaultMessage();
    }

    public function teamsMessage(): string
    {
        return $this->defaultMessage();
    }

    public function emailMessage(): string
    {
        return $this->defaultMessage();
    }

    public function smsMessage(): string
    {
        return $this->defaultMessage();
    }

    public function discordMessage(): string
    {
        return $this->defaultMessage();
    }

    public function callMessage(): string
    {
        return $this->defaultMessage();
    }
}
