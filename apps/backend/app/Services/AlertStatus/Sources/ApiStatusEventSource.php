<?php

namespace App\Services\AlertStatus\Sources;

use App\Models\AlertRule;
use App\Models\ApiAlertStatusHistory;
use App\Services\AlertStatus\AlertStatusEvent;
use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use App\Services\AlertStatus\Sources\Concerns\QueriesHistoryModel;
use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Model;

final class ApiStatusEventSource implements AlertStatusEventSource
{
    use QueriesHistoryModel;

    protected function modelClass(): string
    {
        return ApiAlertStatusHistory::class;
    }

    /**
     * @param  ApiAlertStatusHistory  $document
     */
    protected function toEvent(Model $document): AlertStatusEvent
    {
        $status = match ((int) $document->state) {
            ApiAlertStatusHistory::FIRE => AlertRule::CRITICAL,
            ApiAlertStatusHistory::RESOLVED => AlertRule::RESOlVED,
            default => AlertRule::UNKNOWN,
        };

        $count = (int) ($document->countAlerts ?? 0);

        $summary = $status === AlertRule::CRITICAL
            ? $this->summarizeFiredInstances($document->firedInstances ?? [])
            : null;

        return new AlertStatusEvent(
            alertRuleId: (string) $document->alertRuleId,
            occurredAt: Carbon::parse($document->createdAt),
            status: $status,
            count: $count,
            summary: $summary,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $firedInstances
     */
    private function summarizeFiredInstances(array $firedInstances): ?string
    {
        $lines = collect($firedInstances)
            ->map(function (array $instance) {
                $label = $instance['instance'] ?? null;
                $description = $instance['description'] ?? $instance['summary'] ?? null;

                return trim(implode(': ', array_filter([$label, $description])));
            })
            ->filter()
            ->values();

        return $lines->isEmpty() ? null : $lines->implode('; ');
    }
}
