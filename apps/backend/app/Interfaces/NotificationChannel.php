<?php

namespace App\Interfaces;

use App\Enums\EndpointType;
use App\Models\Endpoint;
use App\Models\EndpointOTP;
use App\Services\Notification\DeliveryResult;
use App\Support\NotifyMessagePayload;

/**
 * One way of reaching an endpoint (Telegram, SMS, a webhook, ...).
 *
 * A channel owns everything specific to its endpoint type: which endpoint
 * fields it stores, how they are validated, how the message is formatted for
 * the provider, and how the provider's answer is judged.
 */
interface NotificationChannel
{
    public function type(): EndpointType;

    /**
     * Validation rules for the channel specific fields on endpoint create/update.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Endpoint document fields this channel reads when sending.
     *
     * @return list<string>
     */
    public function storedFields(): array;

    /**
     * Maps validated endpoint input to the attributes stored on the endpoint.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function attributes(array $input): array;

    public function requiresVerification(): bool;

    public function sendVerification(EndpointOTP $otp): DeliveryResult;

    public function send(Endpoint $endpoint, NotifyMessagePayload $message): DeliveryResult;
}
