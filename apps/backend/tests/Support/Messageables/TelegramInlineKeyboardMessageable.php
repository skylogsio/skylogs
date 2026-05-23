<?php

namespace Tests\Support\Messageables;

use App\Interfaces\Messageable;

/**
 * Messageable whose telegram() payload matches Grafana-style acknowledge metadata.
 */
final class TelegramInlineKeyboardMessageable implements Messageable
{
    public function __construct(
        private readonly string $baseMessage = 'original-telegram-body',
    ) {}

    public function defaultMessage(): string
    {
        return 'default';
    }

    public function telegram(): array
    {
        return [
            'message' => $this->baseMessage,
            'meta' => [
                [
                    'text' => 'Acknowledge',
                    'url' => 'https://example.test/ack/1',
                ],
            ],
        ];
    }

    public function matterMostMessage(): string
    {
        return 'mm';
    }

    public function teamsMessage(): string
    {
        return 'teams';
    }

    public function smsMessage(): string
    {
        return 'sms';
    }

    public function discordMessage(): string
    {
        return 'discord';
    }

    public function callMessage(): string
    {
        return 'call';
    }

    public function emailMessage(): string
    {
        return 'email';
    }
}
