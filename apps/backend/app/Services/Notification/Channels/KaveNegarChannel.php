<?php

namespace App\Services\Notification\Channels;

use App\Models\Endpoint;
use App\Models\EndpointOTP;
use App\Services\Notification\DeliveryResult;
use App\Support\NotifyMessagePayload;
use Illuminate\Http\Client\Response;

/**
 * Phone channels sent through KaveNegar. Each endpoint is one receptor, so
 * the provider's answer maps to exactly one delivery.
 */
abstract class KaveNegarChannel extends AbstractChannel
{
    /**
     * Path after `/v1/{token}/`, e.g. `sms/send.json`.
     */
    abstract protected function apiPath(): string;

    /**
     * The API token and optional sender line, or why sending is impossible.
     *
     * @return array{token: string, sender: mixed}|string
     */
    abstract protected function credentials(): array|string;

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
        return $this->guard(fn (): DeliveryResult => $this->request((string) $otp->value, $otp->generateOTPMessage()));
    }

    protected function deliver(Endpoint $endpoint, NotifyMessagePayload $message): DeliveryResult
    {
        return $this->request((string) $endpoint->value, $message->textFor($this->type()));
    }

    private function request(string $receptor, string $message): DeliveryResult
    {
        $credentials = $this->credentials();

        if (is_string($credentials)) {
            return DeliveryResult::failed($credentials);
        }

        $query = array_filter([
            'sender' => $credentials['sender'],
            'receptor' => $receptor,
            'message' => $message,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->http()->get(
            "https://api.kavenegar.com/v1/{$credentials['token']}/{$this->apiPath()}",
            $query,
        );

        return $this->interpret($response);
    }

    /**
     * KaveNegar reports its own status in `return.status`; only 200 means the
     * message was accepted.
     */
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

        return DeliveryResult::isRetryableStatus($response->status())
            ? DeliveryResult::retryableFailure($error, $response->status(), $json)
            : DeliveryResult::failed($error, $response->status(), $json);
    }
}
