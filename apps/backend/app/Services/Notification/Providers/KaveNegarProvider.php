<?php

namespace App\Services\Notification\Providers;

use App\Enums\EndpointType;
use App\Enums\SmsProviderType;
use App\Interfaces\NotificationProvider;
use App\Models\Config\ConfigCall;
use App\Models\Config\ConfigSms;
use App\Services\Notification\DeliveryResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * KaveNegar SMS and voice calls. `return.status` 200 means the message was accepted.
 *
 * Account and route statuses fail over to the next profile. Recipient and message
 * statuses (invalid receptor, empty text, invalid characters) do not, because the
 * next account would reject the same message.
 */
class KaveNegarProvider implements NotificationProvider
{
    /**
     * Statuses from KaveNegar's return list that describe this account or the
     * route to it, not the recipient or the text.
     *
     * @var list<int>
     */
    private const FAILOVER_STATUSES = [
        401, // account disabled
        403, // API key is not valid
        407, // access to this information is denied
        409, // server could not respond
        412, // sender line is not valid
        416, // source IP does not match the account
        418, // insufficient credit
        420, // links in the text are restricted for this account
        426, // method requires the advanced service
        427, // sender line is not permitted
        429, // source IP is limited
    ];

    public function key(): string
    {
        return SmsProviderType::KAVE_NEGAR->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(EndpointType $type): array
    {
        $rules = [
            'apiToken' => ['required', 'string', 'max:255'],
        ];

        if ($type === EndpointType::SMS) {
            $rules['senderNumber'] = ['required', 'string', 'max:64'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function attributes(EndpointType $type, array $input): array
    {
        $attributes = [
            'apiToken' => $input['apiToken'],
        ];

        if ($type === EndpointType::SMS) {
            $attributes['senderNumber'] = $input['senderNumber'];
        }

        return $attributes;
    }

    public function sendSms(ConfigSms $config, string $receptor, string $message): DeliveryResult
    {
        return $this->request((string) $config->apiToken, 'sms/send.json', [
            'sender' => $config->senderNumber,
            'receptor' => $receptor,
            'message' => $message,
        ]);
    }

    public function sendCall(ConfigCall $config, string $receptor, string $message): DeliveryResult
    {
        return $this->request((string) $config->apiToken, 'call/maketts.json', [
            'receptor' => $receptor,
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(string $token, string $path, array $query): DeliveryResult
    {
        $query = array_filter(
            $query,
            fn (mixed $value): bool => $value !== null && $value !== '',
        );

        $response = Http::acceptJson()
            ->timeout((int) config('notification.http_timeout', 15))
            ->connectTimeout((int) config('notification.http_connect_timeout', 5))
            ->get("https://api.kavenegar.com/v1/{$token}/{$path}", $query);

        return $this->interpret($response);
    }

    private function interpret(Response $response): DeliveryResult
    {
        $json = DeliveryResult::responseBody($response);
        $providerStatus = is_array($json) ? (int) ($json['return']['status'] ?? 0) : 0;

        if ($response->successful() && $providerStatus === 200) {
            $messageId = $json['entries'][0]['messageid'] ?? null;

            return DeliveryResult::sent($response->status(), $json, $messageId === null ? null : (string) $messageId);
        }

        $error = is_array($json) && isset($json['return']['message'])
            ? 'KaveNegar '.$providerStatus.': '.$json['return']['message']
            : 'HTTP '.$response->status();

        $failover = DeliveryResult::isRetryableStatus($response->status())
            || in_array($providerStatus, self::FAILOVER_STATUSES, true);

        return DeliveryResult::isRetryableStatus($response->status())
            ? DeliveryResult::retryableFailure($error, $response->status(), $json, $failover)
            : DeliveryResult::failed($error, $response->status(), $json, $failover);
    }
}
