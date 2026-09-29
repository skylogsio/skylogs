<?php

namespace App\Services\Notification\Channels;

use App\Enums\CallProviderType;
use App\Enums\EndpointType;
use App\Services\ConfigCallService;

class CallChannel extends KaveNegarChannel
{
    public function __construct(private readonly ConfigCallService $configCallService) {}

    public function type(): EndpointType
    {
        return EndpointType::CALL;
    }

    protected function apiPath(): string
    {
        return 'call/maketts.json';
    }

    /**
     * @return array{token: string, sender: mixed}|string
     */
    protected function credentials(): array|string
    {
        $config = $this->configCallService->getDefault();

        if (! empty($config)) {
            if ($config->provider !== CallProviderType::KAVE_NEGAR->value) {
                return "Call provider {$config->provider} is not supported";
            }

            return ['token' => (string) $config->apiToken, 'sender' => null];
        }

        $token = (string) config('variables.kavenegarToken');

        if ($token === '') {
            return 'Call is not configured';
        }

        return ['token' => $token, 'sender' => null];
    }
}
