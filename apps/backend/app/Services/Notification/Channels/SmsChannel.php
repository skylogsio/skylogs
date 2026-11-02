<?php

namespace App\Services\Notification\Channels;

use App\Enums\EndpointType;
use App\Enums\SmsProviderType;
use App\Services\ConfigSmsService;

class SmsChannel extends KaveNegarChannel
{
    public function __construct(private readonly ConfigSmsService $configSmsService) {}

    public function type(): EndpointType
    {
        return EndpointType::SMS;
    }

    protected function apiPath(): string
    {
        return 'sms/send.json';
    }

    /**
     * @return array{token: string, sender: mixed}|string
     */
    protected function credentials(): array|string
    {
        $config = $this->configSmsService->getDefault();

        if (! empty($config)) {
            if ($config->provider !== SmsProviderType::KAVE_NEGAR->value) {
                return "SMS provider {$config->provider} is not supported";
            }

            return ['token' => (string) $config->apiToken, 'sender' => $config->senderNumber];
        }

        $token = (string) config('variables.kavenegarToken');

        if ($token === '') {
            return 'SMS is not configured';
        }

        return ['token' => $token, 'sender' => config('variables.kavenegarSenderNumber')];
    }
}
