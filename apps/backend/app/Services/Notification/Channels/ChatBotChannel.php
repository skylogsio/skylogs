<?php

namespace App\Services\Notification\Channels;

use App\Models\Endpoint;
use App\Services\Notification\DeliveryResult;
use App\Support\NotifyMessagePayload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Telegram-compatible bot APIs: the endpoint stores a chatId and an optional
 * botToken, and the provider answers `{"ok": bool, "result": {...}}`.
 */
abstract class ChatBotChannel extends AbstractChannel
{
    abstract protected function sendUrl(string $botToken): string;

    abstract protected function defaultBotToken(): string;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:255'],
            'botToken' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<string>
     */
    public function storedFields(): array
    {
        return ['chatId', 'botToken'];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function attributes(array $input): array
    {
        return [
            'chatId' => trim((string) ($input['value'] ?? '')),
            'botToken' => $input['botToken'] ?? null,
        ];
    }

    protected function deliver(Endpoint $endpoint, NotifyMessagePayload $message): DeliveryResult
    {
        $content = $message->forChannel($this->type());
        $meta = is_array($content) ? ($content['meta'] ?? []) : [];

        $body = [
            'chat_id' => $endpoint->chatId,
            'text' => $message->textFor($this->type()),
        ];

        if (! empty($meta)) {
            $body['reply_markup'] = [
                'inline_keyboard' => [$meta],
            ];
        }

        $response = $this->request()->post(
            $this->sendUrl($endpoint->botToken ?: $this->defaultBotToken()),
            $this->withEndpointOptions($body, $endpoint),
        );

        return $this->interpret($response);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function withEndpointOptions(array $body, Endpoint $endpoint): array
    {
        return $body;
    }

    protected function request(): PendingRequest
    {
        return $this->http();
    }

    private function interpret(Response $response): DeliveryResult
    {
        $json = DeliveryResult::responseBody($response);

        if ($response->successful() && is_array($json) && ($json['ok'] ?? false) === true) {
            $messageId = $json['result']['message_id'] ?? null;

            return DeliveryResult::sent($response->status(), $json, $messageId === null ? null : (string) $messageId);
        }

        $error = is_array($json) && isset($json['description'])
            ? (string) $json['description']
            : 'HTTP '.$response->status();

        return DeliveryResult::isRetryableStatus($response->status())
            ? DeliveryResult::retryableFailure($error, $response->status(), $json)
            : DeliveryResult::failed($error, $response->status(), $json);
    }
}
