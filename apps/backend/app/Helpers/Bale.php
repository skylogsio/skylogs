<?php

namespace App\Helpers;

use App\interfaces\Messageable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Bale
{
    private static function token(): string
    {
        return config('variables.baleBotToken', '');
    }

    private static function url(string $botToken): string
    {
        return "https://tapi.bale.ai/bot{$botToken}/send_message";
    }

    public static function sendMessageAlert($chatIds, Messageable $alert): array
    {
        $responses = [];
        if (empty($chatIds)) {
            return $responses;
        }

        $result = Http::pool(function (Pool $pool) use ($chatIds, $alert) {
            foreach ($chatIds as $chat) {
                $botToken = $chat['botToken'] ?? self::token();
                $sendData = $alert->baleMessage();

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

                if (! empty($meta)) {
                    $body['reply_markup'] = [
                        'inline_keyboard' => [$meta],
                    ];
                }

                $pool->acceptJson()->post(self::url($botToken), $body);
            }

            return [];
        });

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
