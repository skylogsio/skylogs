<?php

namespace App\Services\Notification\Channels;

use App\Enums\EndpointType;

class DiscordChannel extends WebhookChannel
{
    public function type(): EndpointType
    {
        return EndpointType::DISCORD;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(string $text): array
    {
        return [
            'content' => $text,
        ];
    }
}
