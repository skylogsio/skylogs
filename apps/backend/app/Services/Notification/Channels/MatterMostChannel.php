<?php

namespace App\Services\Notification\Channels;

use App\Enums\EndpointType;

class MatterMostChannel extends WebhookChannel
{
    public function type(): EndpointType
    {
        return EndpointType::MATTER_MOST;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(string $text): array
    {
        return [
            'text' => $text,
        ];
    }
}
