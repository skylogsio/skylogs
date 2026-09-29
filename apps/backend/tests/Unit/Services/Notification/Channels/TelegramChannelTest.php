<?php

use App\Enums\NotificationDeliveryStatus;
use App\Models\Config\ConfigTelegram;
use App\Models\Endpoint;
use App\Services\ConfigTelegramService;
use App\Services\Notification\Channels\TelegramChannel;
use App\Support\NotifyMessagePayload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function telegramChannel(?ConfigTelegram $activeConfig = null): TelegramChannel
{
    $configService = Mockery::mock(ConfigTelegramService::class);
    $configService->shouldReceive('getActive')->andReturn($activeConfig);

    return new TelegramChannel($configService);
}

function telegramEndpoint(array $attributes = []): Endpoint
{
    return new Endpoint([
        'type' => 'telegram',
        'chatId' => '-100123',
        'botToken' => 'bot-token',
        ...$attributes,
    ]);
}

describe('TelegramChannel', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
    });

    it('sends to the chat with thread id and inline keyboard, and records the message id', function () {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]]),
        ]);

        $message = NotifyMessagePayload::fromBody('body', [
            'telegram' => [
                'message' => 'Firing',
                'meta' => [['text' => 'Acknowledge', 'url' => 'https://example.test/ack']],
            ],
        ]);

        $result = telegramChannel()->send(telegramEndpoint(['threadId' => '7']), $message);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/botbot-token/sendMessage'
            && $request['chat_id'] === '-100123'
            && $request['text'] === 'Firing'
            && $request['message_thread_id'] === '7'
            && $request['reply_markup']['inline_keyboard'][0][0]['text'] === 'Acknowledge');

        expect($result->status)->toBe(NotificationDeliveryStatus::SENT)
            ->and($result->providerMessageId)->toBe('42')
            ->and($result->httpStatus)->toBe(200);
    });

    it('falls back to the configured bot token and omits empty thread ids', function () {
        config(['variables.telegramBotToken' => 'default-token']);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);

        telegramChannel()->send(
            telegramEndpoint(['botToken' => '', 'threadId' => '']),
            NotifyMessagePayload::fromBody('plain'),
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), '/botdefault-token/')
            && $request['text'] === 'plain'
            && ! array_key_exists('message_thread_id', $request->data())
            && ! array_key_exists('reply_markup', $request->data()));
    });

    it('fails without retry when the chat is not found', function () {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'], 400),
        ]);

        $result = telegramChannel()->send(telegramEndpoint(), NotifyMessagePayload::fromBody('x'));

        expect($result->status)->toBe(NotificationDeliveryStatus::FAILED)
            ->and($result->retryable)->toBeFalse()
            ->and($result->error)->toBe('Bad Request: chat not found')
            ->and($result->httpStatus)->toBe(400);
    });

    it('marks rate limits and server errors as retryable', function (int $status) {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Too Many Requests'], $status),
        ]);

        $result = telegramChannel()->send(telegramEndpoint(), NotifyMessagePayload::fromBody('x'));

        expect($result->status)->toBe(NotificationDeliveryStatus::FAILED)
            ->and($result->retryable)->toBeTrue();
    })->with([429, 502]);

    it('marks connection errors as retryable', function () {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $result = telegramChannel()->send(telegramEndpoint(), NotifyMessagePayload::fromBody('x'));

        expect($result->retryable)->toBeTrue()
            ->and($result->error)->toContain('timed out');
    });

    it('routes through the active telegram proxy config', function () {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);

        $config = new ConfigTelegram([
            'type' => 'socks5',
            'host' => 'proxy.local',
            'port' => 1080,
            'username' => 'u',
            'password' => 'p',
        ]);

        $result = telegramChannel($config)->send(telegramEndpoint(), NotifyMessagePayload::fromBody('x'));

        expect($result->isSent())->toBeTrue();
    });
});
