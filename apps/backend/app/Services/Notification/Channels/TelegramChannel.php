<?php

namespace App\Services\Notification\Channels;

use App\Enums\EndpointType;
use App\Models\Config\ConfigTelegram;
use App\Models\Endpoint;
use App\Services\ConfigTelegramService;
use App\Services\Notification\DeliveryResult;
use App\Services\Notification\ProfileChain;
use App\Support\NotifyMessagePayload;
use Illuminate\Http\Client\PendingRequest;

class TelegramChannel extends ChatBotChannel
{
    public function __construct(
        private readonly ConfigTelegramService $configTelegramService,
        private readonly ProfileChain $profileChain,
    ) {}

    public function type(): EndpointType
    {
        return EndpointType::TELEGRAM;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'threadId' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<string>
     */
    public function storedFields(): array
    {
        return ['chatId', 'threadId', 'botToken'];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function attributes(array $input): array
    {
        return [
            ...parent::attributes($input),
            'threadId' => $input['threadId'] ?? null,
        ];
    }

    protected function sendUrl(string $botToken): string
    {
        return "https://api.telegram.org/bot{$botToken}/sendMessage";
    }

    protected function defaultBotToken(): string
    {
        return (string) config('variables.telegramBotToken', '');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function withEndpointOptions(array $body, Endpoint $endpoint): array
    {
        if (! empty($endpoint->threadId)) {
            $body['message_thread_id'] = $endpoint->threadId;
        }

        return $body;
    }

    protected function deliver(Endpoint $endpoint, NotifyMessagePayload $message): DeliveryResult
    {
        $chain = $this->configTelegramService->chain();

        if ($chain->isEmpty()) {
            $chain = [new ConfigTelegram(['type' => 'direct'])];
        }

        return $this->profileChain->run(
            $this->type(),
            $chain,
            fn (ConfigTelegram $config): DeliveryResult => $this->post($this->client($config), $endpoint, $message),
        );
    }

    private function client(ConfigTelegram $config): PendingRequest
    {
        if ($config->type === 'direct' || $config->type === null || $config->type === '') {
            return $this->http();
        }

        return $this->http()->withOptions(['proxy' => $this->proxyUrl($config)]);
    }

    private function proxyUrl(ConfigTelegram $config): string
    {
        $auth = '';

        if (! empty($config->username) && ! empty($config->password)) {
            $auth = $config->username.':'.$config->password.'@';
        } elseif (! empty($config->username)) {
            $auth = $config->username.'@';
        }

        return "{$config->type}://{$auth}{$config->host}:{$config->port}";
    }
}
