<?php

use App\Enums\EndpointType;
use App\Models\Endpoint;
use App\Services\Notification\Channels\BaleChannel;
use App\Support\NotifyMessagePayload;
use Illuminate\Support\Facades\Http;
use Tests\Support\Messageables\TelegramInlineKeyboardMessageable;

describe('BaleChannel', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
        config(['variables.baleBotToken' => 'default-token']);
    });

    it('posts alert messages to the bale bot api without a thread id', function () {
        Http::fake([
            'tapi.bale.ai/*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]], 200),
        ]);

        $endpoint = new Endpoint([
            'type' => EndpointType::BALE->value,
            'chatId' => '12345',
            'botToken' => 'my-bot-token',
            'threadId' => '99',
        ]);

        $result = (new BaleChannel)->send($endpoint, NotifyMessagePayload::fromBody('CPU High'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://tapi.bale.ai/botmy-bot-token/send_message'
                && $request['chat_id'] === '12345'
                && $request['text'] === 'CPU High'
                && ! array_key_exists('message_thread_id', $request->data());
        });

        expect($result->isSent())->toBeTrue()
            ->and($result->providerMessageId)->toBe('9');
    });

    it('supports telegram-style inline keyboard payloads', function () {
        Http::fake([
            'tapi.bale.ai/*' => Http::response(['ok' => true], 200),
        ]);

        $endpoint = new Endpoint(['type' => EndpointType::BALE->value, 'chatId' => '777', 'botToken' => 'token-1']);
        $payload = NotifyMessagePayload::fromMessageable(new TelegramInlineKeyboardMessageable('Firing alert'));

        (new BaleChannel)->send($endpoint, $payload);

        Http::assertSent(function ($request) {
            return $request['text'] === 'Firing alert'
                && $request['reply_markup']['inline_keyboard'][0][0]['text'] === 'Acknowledge';
        });
    });

    it('uses the default bot token when the endpoint has none', function () {
        Http::fake([
            'tapi.bale.ai/*' => Http::response(['ok' => true], 200),
        ]);

        (new BaleChannel)->send(
            new Endpoint(['type' => EndpointType::BALE->value, 'chatId' => '1']),
            NotifyMessagePayload::fromBody('x'),
        );

        Http::assertSent(fn ($request) => $request->url() === 'https://tapi.bale.ai/botdefault-token/send_message');
    });

    it('fails when bale answers ok false', function () {
        Http::fake([
            'tapi.bale.ai/*' => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked'], 403),
        ]);

        $result = (new BaleChannel)->send(
            new Endpoint(['type' => EndpointType::BALE->value, 'chatId' => '1']),
            NotifyMessagePayload::fromBody('x'),
        );

        expect($result->isSent())->toBeFalse()
            ->and($result->retryable)->toBeFalse()
            ->and($result->error)->toBe('Forbidden: bot was blocked');
    });
});
