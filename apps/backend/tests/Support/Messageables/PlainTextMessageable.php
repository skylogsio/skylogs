<?php

namespace Tests\Support\Messageables;

use App\Interfaces\Messageable;

/**
 * Minimal Messageable used in unit tests (no DB, no Laravel models).
 */
final class PlainTextMessageable implements Messageable
{
    public function __construct(
        private readonly string $text = 'plain-body',
    ) {}

    public function defaultMessage(): string
    {
        return $this->text;
    }

    public function telegram(): array|string
    {
        return $this->text;
    }

    public function matterMostMessage(): string
    {
        return $this->text;
    }

    public function teamsMessage(): string
    {
        return $this->text;
    }

    public function smsMessage(): string
    {
        return $this->text;
    }

    public function discordMessage(): string
    {
        return $this->text;
    }

    public function callMessage(): string
    {
        return $this->text;
    }

    public function emailMessage(): string
    {
        return $this->text;
    }
}
