<?php

namespace App\Services\Notification\Channels;

use App\Models\Endpoint;
use App\Services\Notification\DeliveryResult;
use App\Support\NotifyMessagePayload;

/**
 * Incoming-webhook chat integrations. They answer with an empty body, `1`,
 * or a 202/204, so success can only be judged by the HTTP status.
 */
abstract class WebhookChannel extends AbstractChannel
{
    /**
     * @return array<string, mixed>
     */
    abstract protected function payload(string $text): array;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'url', 'max:2048'],
        ];
    }

    protected function deliver(Endpoint $endpoint, NotifyMessagePayload $message): DeliveryResult
    {
        $response = $this->http()->post(
            (string) $endpoint->value,
            $this->payload($message->textFor($this->type())),
        );

        return DeliveryResult::fromHttpStatus($response);
    }
}
