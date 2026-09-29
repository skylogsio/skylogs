<?php

namespace App\Services\Notification;

use App\Enums\NotificationDeliveryStatus;
use App\Jobs\SendNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use App\Services\Ha\HaReplicationContext;
use Illuminate\Support\Collection;

/**
 * Entry point for sending anything to endpoints. It knows nothing about alert
 * rules: callers decide who gets which message, the dispatcher records one
 * delivery per endpoint and queues it.
 */
class NotificationDispatcher
{
    public function __construct(private readonly ChannelRegistry $channels) {}

    /**
     * Endpoints without a channel (flows) are skipped; flows orchestrate
     * their own steps and dispatch those endpoints individually.
     *
     * @param  iterable<DeliveryTarget>  $targets
     * @param  array{source: string, notifyId?: string|null, sourceId?: string|null, flowEndpointId?: string|null, flowStepIndex?: int|null}  $context
     * @return Collection<int, NotificationDelivery>
     */
    public function dispatch(iterable $targets, array $context): Collection
    {
        if (HaReplicationContext::isApplying()) {
            return collect();
        }

        $deliveries = collect();

        foreach ($targets as $target) {
            if (! $this->channels->has((string) $target->endpoint->type)) {
                continue;
            }

            $delivery = NotificationDelivery::create([
                'notifyId' => $context['notifyId'] ?? null,
                'source' => $context['source'],
                'sourceId' => $context['sourceId'] ?? null,
                'flowEndpointId' => $context['flowEndpointId'] ?? null,
                'flowStepIndex' => $context['flowStepIndex'] ?? null,
                'endpointId' => (string) $target->endpoint->id,
                'endpointType' => $target->endpoint->type,
                'endpointName' => $target->endpoint->name,
                'userId' => $target->endpoint->userId === null ? null : (string) $target->endpoint->userId,
                'message' => $target->message->toArray(),
                'templateApplied' => $target->templateApplied,
                'status' => NotificationDeliveryStatus::PENDING,
                'attempts' => 0,
                'maxAttempts' => max(1, (int) config('notification.max_attempts', 3)),
                'nextRetryAt' => null,
                'lastError' => null,
                'attemptLog' => [],
            ]);

            SendNotificationDeliveryJob::dispatch((string) $delivery->id);

            $deliveries->push($delivery);
        }

        return $deliveries;
    }
}
