<?php

namespace App\Services\Notification;

use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use App\Models\Notify;
use App\Services\Ha\HaHistoryCatalog;
use Illuminate\Database\Eloquent\Model;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

/**
 * Keeps two integers on a history row and leaves error text on the delivery.
 */
class HistoryDeliveryCounter
{
    /**
     * Timeline collections that are not the row returned by alert history.
     *
     * @var list<string>
     */
    private const EXCLUDED_ALIASES = [
        'notifies',
        'notificationDeliveries',
        'apiAlertStatusHistories',
    ];

    /**
     * The explicit history row wins. Otherwise a webhook alert is its own history row.
     */
    public function resolve(?Model $history, mixed $alert): ?Model
    {
        return $this->countableHistory($history) ?? $this->countableHistory($alert);
    }

    public function attach(Notify $notify, Model $history): void
    {
        $alias = HaHistoryCatalog::aliasFor($history);

        if ($alias === null) {
            return;
        }

        $notify->historyId = (string) $history->getKey();
        $notify->historyAlias = $alias;

        $history->newQuery()
            ->whereKey($history->getKey())
            ->whereNull('deliveryCounts')
            ->update([
                'deliveryCounts' => ['sent' => 0, 'failed' => 0],
            ]);
    }

    /**
     * Move sent and failed when this delivery enters or leaves those states.
     * A delivery with no history link is left untouched.
     */
    public function sync(NotificationDelivery $delivery): void
    {
        if (! is_string($delivery->historyId) || $delivery->historyId === '' || ! is_string($delivery->historyAlias) || $delivery->historyAlias === '') {
            return;
        }

        $modelClass = HaHistoryCatalog::model($delivery->historyAlias);

        if ($modelClass === null) {
            return;
        }

        $next = $this->countedStatus($delivery->status);
        $previous = is_string($delivery->countedStatus) ? $delivery->countedStatus : null;

        if ($previous === $next) {
            return;
        }

        $inc = $this->increments($previous, $next);

        if ($inc !== []) {
            $historyId = $delivery->historyId;

            $modelClass::raw(fn (Collection $collection) => $collection->updateOne(
                ['_id' => new ObjectId($historyId)],
                [
                    '$inc' => $inc,
                    '$set' => ['updatedAt' => new UTCDateTime],
                ],
            ));
        }

        $delivery->countedStatus = $next;
        $delivery->save();
    }

    private function countableHistory(mixed $candidate): ?Model
    {
        if (! $candidate instanceof Model || $candidate->getKey() === null) {
            return null;
        }

        $alias = HaHistoryCatalog::aliasFor($candidate);

        if ($alias === null || in_array($alias, self::EXCLUDED_ALIASES, true)) {
            return null;
        }

        return $candidate;
    }

    private function countedStatus(mixed $status): ?string
    {
        return match ($status) {
            NotificationDeliveryStatus::SENT => 'sent',
            NotificationDeliveryStatus::FAILED => 'failed',
            default => null,
        };
    }

    /**
     * @return array<string, int>
     */
    private function increments(?string $previous, ?string $next): array
    {
        $inc = [];

        if ($previous === 'sent') {
            $inc['deliveryCounts.sent'] = -1;
        }

        if ($previous === 'failed') {
            $inc['deliveryCounts.failed'] = -1;
        }

        if ($next === 'sent') {
            $inc['deliveryCounts.sent'] = ($inc['deliveryCounts.sent'] ?? 0) + 1;
        }

        if ($next === 'failed') {
            $inc['deliveryCounts.failed'] = ($inc['deliveryCounts.failed'] ?? 0) + 1;
        }

        return $inc;
    }
}
