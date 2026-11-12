<?php

namespace App\Concerns;

use App\Enums\EndpointType;

trait ProvidesChannelMessages
{
    /**
     * @return array<string, mixed>|string|null
     */
    public function messageFor(EndpointType $type): array|string|null
    {
        return null;
    }

    /**
     * The default message with an Acknowledge inline button when requested.
     *
     * @return array{message: string, meta?: list<array{text: string, url: string}>}
     */
    protected function messageWithAcknowledgeButton(bool $showButton, mixed $alertRuleId): array
    {
        $result = [
            'message' => $this->defaultMessage(),
        ];

        if ($showButton) {
            $result['meta'] = [
                [
                    'text' => 'Acknowledge',
                    'url' => config('app.url').route('acknowledgeLink', ['id' => $alertRuleId], false),
                ],
            ];
        }

        return $result;
    }
}
