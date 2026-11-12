<?php

namespace App\Services\Notification\Channels;

use App\Enums\CallProviderType;
use App\Enums\EndpointType;
use App\Models\Config\ConfigCall;
use App\Models\Endpoint;
use App\Models\EndpointOTP;
use App\Services\ConfigCallService;
use App\Services\Notification\DeliveryResult;
use App\Services\Notification\NotificationProviderRegistry;
use App\Services\Notification\ProfileChain;
use App\Support\NotifyMessagePayload;

class CallChannel extends AbstractChannel
{
    public function __construct(
        private readonly ConfigCallService $configCallService,
        private readonly NotificationProviderRegistry $providers,
        private readonly ProfileChain $profileChain,
        private readonly SmsChannel $smsChannel,
    ) {}

    public function type(): EndpointType
    {
        return EndpointType::CALL;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:32'],
        ];
    }

    public function requiresVerification(): bool
    {
        return true;
    }

    /**
     * The verification code is sent by SMS to the same number; only alerts
     * are delivered as voice calls.
     */
    public function sendVerification(EndpointOTP $otp): DeliveryResult
    {
        return $this->smsChannel->sendVerification($otp);
    }

    protected function deliver(Endpoint $endpoint, NotifyMessagePayload $message): DeliveryResult
    {
        return $this->sendText((string) $endpoint->value, $message->textFor($this->type()));
    }

    private function sendText(string $receptor, string $message): DeliveryResult
    {
        $chain = $this->configCallService->chain();

        if ($chain->isEmpty()) {
            return $this->legacy($receptor, $message);
        }

        return $this->profileChain->run(
            $this->type(),
            $chain,
            fn (ConfigCall $config): DeliveryResult => $this->sendWith($config, $receptor, $message),
        );
    }

    private function sendWith(ConfigCall $config, string $receptor, string $message): DeliveryResult
    {
        $provider = (string) $config->provider;

        if (! $this->providers->has($provider)) {
            return DeliveryResult::failed("Call provider {$provider} is not supported", failover: true);
        }

        return $this->providers->for($provider)->sendCall($config, $receptor, $message);
    }

    private function legacy(string $receptor, string $message): DeliveryResult
    {
        $token = (string) config('variables.kavenegarToken');

        if ($token === '') {
            return DeliveryResult::failed('Call is not configured');
        }

        $config = new ConfigCall([
            'provider' => CallProviderType::KAVE_NEGAR->value,
            'apiToken' => $token,
        ]);

        return $this->profileChain->run(
            $this->type(),
            [$config],
            fn (ConfigCall $config): DeliveryResult => $this->sendWith($config, $receptor, $message),
        );
    }
}
