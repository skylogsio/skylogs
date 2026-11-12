<?php

namespace App\Services\Notification\Channels;

use App\Enums\EndpointType;
use App\Enums\SmsProviderType;
use App\Models\Config\ConfigSms;
use App\Models\Endpoint;
use App\Models\EndpointOTP;
use App\Services\ConfigSmsService;
use App\Services\Notification\DeliveryResult;
use App\Services\Notification\NotificationProviderRegistry;
use App\Services\Notification\ProfileChain;
use App\Support\NotifyMessagePayload;

class SmsChannel extends AbstractChannel
{
    public function __construct(
        private readonly ConfigSmsService $configSmsService,
        private readonly NotificationProviderRegistry $providers,
        private readonly ProfileChain $profileChain,
    ) {}

    public function type(): EndpointType
    {
        return EndpointType::SMS;
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

    public function sendVerification(EndpointOTP $otp): DeliveryResult
    {
        return $this->guard(fn (): DeliveryResult => $this->sendText((string) $otp->value, $otp->generateOTPMessage()));
    }

    protected function deliver(Endpoint $endpoint, NotifyMessagePayload $message): DeliveryResult
    {
        return $this->sendText((string) $endpoint->value, $message->textFor($this->type()));
    }

    private function sendText(string $receptor, string $message): DeliveryResult
    {
        $chain = $this->configSmsService->chain();

        if ($chain->isEmpty()) {
            return $this->legacy($receptor, $message);
        }

        return $this->profileChain->run(
            $chain,
            fn (ConfigSms $config): DeliveryResult => $this->sendWith($config, $receptor, $message),
        );
    }

    private function sendWith(ConfigSms $config, string $receptor, string $message): DeliveryResult
    {
        $provider = (string) $config->provider;

        if (! $this->providers->has($provider)) {
            return DeliveryResult::failed("SMS provider {$provider} is not supported", failover: true);
        }

        return $this->providers->for($provider)->sendSms($config, $receptor, $message);
    }

    /**
     * Configurations written before profiles existed, or none at all, keep the env token path.
     */
    private function legacy(string $receptor, string $message): DeliveryResult
    {
        $token = (string) config('variables.kavenegarToken');

        if ($token === '') {
            return DeliveryResult::failed('SMS is not configured');
        }

        $config = new ConfigSms([
            'provider' => SmsProviderType::KAVE_NEGAR->value,
            'apiToken' => $token,
            'senderNumber' => config('variables.kavenegarSenderNumber'),
        ]);

        return $this->profileChain->run(
            [$config],
            fn (ConfigSms $config): DeliveryResult => $this->sendWith($config, $receptor, $message),
        );
    }
}
