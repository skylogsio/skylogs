<?php

use App\Helpers\Bale;
use Illuminate\Support\Facades\Http;
use Tests\Support\Messageables\PlainTextMessageable;
use Tests\Support\Messageables\TelegramInlineKeyboardMessageable;

describe('Bale helper', function () {
    it('posts alert messages to the bale bot api without proxy', function () {
        config(['variables.baleBotToken' => 'default-token']);

        Http::fake([
            'tapi.bale.ai/*' => Http::response(['ok' => true], 200),
        ]);

        $result = Bale::sendMessageAlert([
            [
                'chatId' => '12345',
                'botToken' => 'my-bot-token',
                'threadId' => '99',
            ],
        ], new PlainTextMessageable('CPU High'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://tapi.bale.ai/botmy-bot-token/send_message'
                && $request['chat_id'] === '12345'
                && $request['text'] === 'CPU High'
                && ! array_key_exists('message_thread_id', $request->data());
        });

        expect($result)->toBe([['ok' => true]]);
    });

    it('supports telegram-style inline keyboard payloads', function () {
        config(['variables.baleBotToken' => 'default-token']);

        Http::fake([
            'tapi.bale.ai/*' => Http::response(['ok' => true], 200),
        ]);

        Bale::sendMessageAlert([
            ['chatId' => '777', 'botToken' => 'token-1'],
        ], new TelegramInlineKeyboardMessageable('Firing alert'));

        Http::assertSent(function ($request) {
            return $request['text'] === 'Firing alert'
                && $request['reply_markup']['inline_keyboard'][0][0]['text'] === 'Acknowledge';
        });
    });
});
