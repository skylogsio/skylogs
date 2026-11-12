<?php

namespace App\Services\Notification;

use App\Models\Endpoint;
use App\Support\NotifyMessagePayload;

/**
 * An endpoint together with the exact message it should receive.
 */
final class DeliveryTarget
{
    public function __construct(
        public readonly Endpoint $endpoint,
        public readonly NotifyMessagePayload $message,
        public readonly bool $templateApplied = false,
    ) {}
}
