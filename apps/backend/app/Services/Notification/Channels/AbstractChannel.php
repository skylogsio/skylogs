<?php

namespace App\Services\Notification\Channels;

use App\Interfaces\NotificationChannel;
use App\Models\Endpoint;
use App\Models\EndpointOTP;
use App\Services\Notification\DeliveryResult;
use App\Support\NotifyMessagePayload;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;
use Throwable;

/**
 * Defaults for channels whose endpoint is a single `value` (a phone number,
 * an address or a webhook URL). Exceptions never escape send(); they become a
 * failed DeliveryResult so one broken endpoint cannot stop the others.
 */
abstract class AbstractChannel implements NotificationChannel
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:2048'],
        ];
    }

    /**
     * @return list<string>
     */
    public function storedFields(): array
    {
        return ['value'];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function attributes(array $input): array
    {
        return [
            'value' => trim((string) ($input['value'] ?? '')),
        ];
    }

    public function requiresVerification(): bool
    {
        return false;
    }

    public function sendVerification(EndpointOTP $otp): DeliveryResult
    {
        throw new LogicException(static::class.' does not verify endpoints.');
    }

    public function send(Endpoint $endpoint, NotifyMessagePayload $message): DeliveryResult
    {
        return $this->guard(fn (): DeliveryResult => $this->deliver($endpoint, $message));
    }

    abstract protected function deliver(Endpoint $endpoint, NotifyMessagePayload $message): DeliveryResult;

    /**
     * @param  Closure(): DeliveryResult  $callback
     */
    protected function guard(Closure $callback): DeliveryResult
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            return DeliveryResult::fromException($exception);
        }
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('notification.http_timeout', 15))
            ->connectTimeout((int) config('notification.http_connect_timeout', 5));
    }
}
