<?php

namespace Tests\Support\Messageables;

use App\Concerns\ProvidesChannelMessages;
use App\Enums\EndpointType;
use App\Interfaces\Messageable;

/**
 * Messageable whose Telegram and Bale payloads match Grafana-style acknowledge metadata.
 */
final class TelegramInlineKeyboardMessageable implements Messageable
{
    use ProvidesChannelMessages;

    public function __construct(
        private readonly string $baseMessage = 'original-telegram-body',
    ) {}

    public function defaultMessage(): string
    {
        return 'default';
    }

    /**
     * @return array<string, mixed>|string|null
     */
    public function messageFor(EndpointType $type): array|string|null
    {
        return match ($type) {
            EndpointType::TELEGRAM, EndpointType::BALE => [
                'message' => $this->baseMessage,
                'meta' => [
                    [
                        'text' => 'Acknowledge',
                        'url' => 'https://example.test/ack/1',
                    ],
                ],
            ],
            default => null,
        };
    }
}
