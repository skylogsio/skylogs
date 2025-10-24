<?php

namespace App\Helpers;

use App\interfaces\Messageable;
use App\Models\Config\ConfigTelegram;
use App\Services\ConfigTelegramService;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Telegram
{
    private static function Url(ConfigTelegram $configTelegram): string
    {
        return $configTelegram->url.'/send_message';
    }

    private static function token(): string
    {
        return config('variables.telegramBotToken', '');
    }

    public static function sendMessageAlert($chatIds, Messageable $alert): array
    {
        $responses = [];
        if (empty($chatIds)) {
            return $responses;
        }

        $configTelegram = app(ConfigTelegramService::class)->getActive();

        if ($configTelegram) {

            $result = Http::pool(function (Pool $pool) use ($chatIds, $alert, $configTelegram) {
                foreach ($chatIds as $chat) {
                    $pool->acceptJson()->post(self::Url($configTelegram), [
                        'apiToken' => $chat['botToken'] ?? self::token(),
                        'chatID' => $chat['chatId'],
                        'message' => $alert->telegram(),
                        'thread_id' => $chat['threadId'],
                    ]);
                }

                return [];
            });

        } else {

            $result = Http::pool(function (Pool $pool) use ($chatIds, $alert) {
                foreach ($chatIds as $chat) {
                    $botToken = $chat['botToken'] ?? self::token();
                    $sendData = $alert->telegram();

                    if (is_string($sendData)) {
                        $message = $sendData;
                        $meta = [];
                    } else {
                        $message = $sendData['message'];
                        $meta = $sendData['meta'] ?? [];
                    }

                    $body = [
                        'chat_id' => $chat['chatId'],
                        'text' => $message,
                    ];

                    if (! empty($chat['threadId'])) {
                        $body['message_thread_id'] = $chat['threadId'];
                    }

                    if (! empty($meta)) {
                        $body['reply_markup'] = [
                            'inline_keyboard' => [$meta],
                        ];
                    }

                    $pool->acceptJson()->post("https://api.telegram.org/bot{$botToken}/sendMessage", $body);
                }

                return [];
            });

        }

        $resultJson = [];
        foreach ($result as $item) {

            try {
                if ($item instanceof Response) {
                    $resultJson[] = $item->json();
                } else {
                    $resultJson[] = $item->getMessage();
                }
            } catch (\Exception $e) {
                $resultJson[] = $e->getMessage();
            }

        }

        return $resultJson;

    }
}
