<?php

namespace App\Interfaces;

use App\Enums\EndpointType;
use App\Models\Config\ConfigCall;
use App\Models\Config\ConfigSms;
use App\Services\Notification\DeliveryResult;

/**
 * One provider API for SMS and call profiles (KaveNegar, or a later account).
 *
 * The channel decides who receives the message. The provider decides which
 * fields a profile stores, how to call its API, and whether a failure is
 * worth trying the next profile.
 */
interface NotificationProvider
{
    public function key(): string;

    /**
     * Validation rules for this provider's fields on a config of the given type.
     *
     * @return array<string, mixed>
     */
    public function rules(EndpointType $type): array;

    /**
     * Credential attributes stored on the config document.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function attributes(EndpointType $type, array $input): array;

    public function sendSms(ConfigSms $config, string $receptor, string $message): DeliveryResult;

    public function sendCall(ConfigCall $config, string $receptor, string $message): DeliveryResult;
}
