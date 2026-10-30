<?php

namespace App\Jobs;

use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationDeliveryTrigger;
use App\Models\NotificationDelivery;
use App\Services\Notification\HistoryDeliveryCounter;
use App\Services\Notification\NotificationDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Retries are scheduled by re-dispatching rather than through Laravel's
 * `tries`, so the attempt count lives on the delivery document where the API
 * can show it and a manual retry can extend it.
 */
class SendNotificationDeliveryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $deliveryId,
        public readonly NotificationDeliveryTrigger $trigger = NotificationDeliveryTrigger::AUTO,
    ) {
        $this->onQueue('sendNotifies');
    }

    public function handle(NotificationDeliveryService $deliveries): void
    {
        $delivery = $deliveries->attempt($this->deliveryId, $this->trigger);

        if ($delivery?->isFailed() && $delivery->nextRetryAt !== null) {
            self::dispatch($this->deliveryId)->delay($delivery->nextRetryAt);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = NotificationDelivery::query()->where('_id', $this->deliveryId)->first();

        if ($delivery === null || $delivery->status === NotificationDeliveryStatus::SENT) {
            return;
        }

        $delivery->status = NotificationDeliveryStatus::FAILED;
        $delivery->lastError = $exception?->getMessage();
        $delivery->nextRetryAt = null;
        $delivery->save();

        app(HistoryDeliveryCounter::class)->sync($delivery);
    }
}
