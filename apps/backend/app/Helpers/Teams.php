<?php

namespace App\Helpers;

use App\interfaces\Messageable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Teams
{
    public static function createText($message): array
    {
        $formatted_Card_Payload = [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.2',
                        'body' => [
                            [
                                'type' => 'RichTextBlock',
                                'inlines' => [
                                    [
                                        'type' => 'TextRun',
                                        //                                        "text" => "A\nA"
                                        'text' => $message,
                                    ],
                                ],
                                //                                "wrap" => true
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $formatted_Card_Payload;
    }

    public static function sendMessageAlert($urls, Messageable $alert): array
    {
        $responses = [];
        if (empty($urls)) {
            return $responses;
        }

        $result = Http::pool(function (Pool $pool) use ($urls, $alert, $responses) {
            foreach ($urls as $url) {

                $responses[] = $pool->acceptJson()->post($url, self::createText($alert->teamsMessage()));
            }

            return $responses;
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
