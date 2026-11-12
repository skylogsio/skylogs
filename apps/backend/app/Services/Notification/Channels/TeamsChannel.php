<?php

namespace App\Services\Notification\Channels;

use App\Enums\EndpointType;

class TeamsChannel extends WebhookChannel
{
    public function type(): EndpointType
    {
        return EndpointType::TEAMS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(string $text): array
    {
        return [
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
                                        'text' => $text,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
