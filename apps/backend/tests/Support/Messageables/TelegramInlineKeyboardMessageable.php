<?php

namespace Tests\Support\Messageables;

use App\Concerns\ProvidesDefaultChannelMessages;
use App\Interfaces\Messageable;

/**
 * Messageable whose telegram() payload matches Grafana-style acknowledge metadata.
 */
final class TelegramInlineKeyboardMessageable implements Messageable
{
    use ProvidesDefaultChannelMessages;

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

    public function baleMessage(): array
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
}
