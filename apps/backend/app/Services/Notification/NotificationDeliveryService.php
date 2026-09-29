<?php

namespace App\Services\Notification;

use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationDeliveryTrigger;
use App\Jobs\SendNotificationDeliveryJob;
use App\Jobs\SendNotifyJob;
use App\Models\Endpoint;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class NotificationDeliveryService
{
    /**
     * Notify types whose deliveries still go out after the rule is acknowledged.
     *
     * @var list<string>
     */
    private const SENT_DESPITE_ACKNOWLEDGEMENT = [
        SendNotifyJob::ALERT_RULE_ACKNOWLEDGED,
        SendNotifyJob::INCIDENT_POLICY_PAGE,
    ];

    public function __construct(private readonly ChannelRegistry $channels) {}

    /**
     * Makes one attempt and records it. Sent and skipped deliveries are left
     * alone, so a duplicated job can never send the same message twice.
     */
    public function attempt(string $deliveryId, NotificationDeliveryTrigger $trigger): ?NotificationDelivery
    {
        $delivery = NotificationDelivery::query()->where('_id', $deliveryId)->first();

        if ($delivery === null || in_array($delivery->status, [NotificationDeliveryStatus::SENT, NotificationDeliveryStatus::SKIPPED], true)) {
            return $delivery;
        }

        $skipReason = $this->skipReason($delivery);

        if ($skipReason !== null) {
            $delivery->status = NotificationDeliveryStatus::SKIPPED;
            $delivery->lastError = $skipReason;
            $delivery->nextRetryAt = null;
            $delivery->save();

            return $delivery;
        }

        $delivery->status = NotificationDeliveryStatus::SENDING;
        $delivery->save();

        $startedAt = hrtime(true);
        $result = $this->send($delivery);
        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $this->record($delivery, $result, $trigger, $durationMs);

        return $delivery;
    }

    /**
     * Grants one more attempt on top of whatever was used and queues it.
     */
    public function retry(NotificationDelivery $delivery): NotificationDelivery
    {
        $delivery->maxAttempts = max((int) $delivery->maxAttempts, (int) $delivery->attempts) + 1;
        $delivery->status = NotificationDeliveryStatus::PENDING;
        $delivery->nextRetryAt = null;
        $delivery->save();

        SendNotificationDeliveryJob::dispatch((string) $delivery->id, NotificationDeliveryTrigger::MANUAL);

        return $delivery->refresh();
    }

    public function backoffSeconds(int $attempt): int
    {
        $backoff = array_values((array) config('notification.retry_backoff', [30, 120, 600]));

        if ($backoff === []) {
            return 60;
        }

        return (int) ($backoff[$attempt - 1] ?? $backoff[array_key_last($backoff)]);
    }

    public function canView(User $user, NotificationDelivery $delivery): bool
    {
        return $user->isAdmin() || (string) $delivery->userId === (string) $user->id;
    }

    /**
     * @param  Builder<NotificationDelivery>  $query
     */
    public function applyVisibility(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->where('userId', (string) $user->id);
        }
    }

    private function send(NotificationDelivery $delivery): DeliveryResult
    {
        $endpoint = Endpoint::query()->where('_id', $delivery->endpointId)->first();

        if ($endpoint === null) {
            return DeliveryResult::failed('Endpoint no longer exists');
        }

        if (! $this->channels->has((string) $endpoint->type)) {
            return DeliveryResult::failed("No notification channel for endpoint type {$endpoint->type}");
        }

        return $this->channels->for((string) $endpoint->type)->send($endpoint, $delivery->messagePayload());
    }

    private function record(NotificationDelivery $delivery, DeliveryResult $result, NotificationDeliveryTrigger $trigger, int $durationMs): void
    {
        $attempt = (int) $delivery->attempts + 1;

        $attemptLog = is_array($delivery->attemptLog) ? $delivery->attemptLog : [];
        $attemptLog[] = [
            'attempt' => $attempt,
            'at' => now()->toIso8601String(),
            ...$result->toArray(),
            'durationMs' => $durationMs,
            'trigger' => $trigger->value,
        ];

        $delivery->attempts = $attempt;
        $delivery->attemptLog = $attemptLog;
        $delivery->status = $result->status;
        $delivery->lastError = $result->error;
        $delivery->nextRetryAt = ! $result->isSent() && $result->retryable && $delivery->hasAttemptsLeft()
            ? now()->addSeconds($this->backoffSeconds($attempt))
            : null;
        $delivery->save();
    }

    private function skipReason(NotificationDelivery $delivery): ?string
    {
        $notify = $delivery->notify;

        if ($notify === null || in_array($notify->type, self::SENT_DESPITE_ACKNOWLEDGEMENT, true)) {
            return null;
        }

        return $notify->alertRule?->isAcknowledged() ? 'Alert rule was acknowledged' : null;
    }
}
