<?php

namespace App\Services\Notification\Channels;

use App\Enums\EndpointType;

class BaleChannel extends ChatBotChannel
{
    public function type(): EndpointType
    {
        return EndpointType::BALE;
    }

    protected function sendUrl(string $botToken): string
    {
        return "https://tapi.bale.ai/bot{$botToken}/send_message";
    }

    protected function defaultBotToken(): string
    {
        return (string) config('variables.baleBotToken', '');
    }
}
