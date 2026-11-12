<?php

namespace App\Services\AlertStatus\Sources;

use App\Models\AlertRule;
use App\Models\ApiAlertStatusHistory;
use App\Services\AlertStatus\AlertStatusEvent;
use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use App\Services\AlertStatus\Sources\Concerns\QueriesHistoryModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use MongoDB\Laravel\Eloquent\Model;

final class ApiStatusEventSource implements AlertStatusEventSource
{
    use QueriesHistoryModel;

    protected function modelClass(): string
    {
        return ApiAlertStatusHistory::class;
    }

    /**
     * API alerts with no history before the window are assumed resolved at fromTime.
     *
     * @param  Collection<string, AlertRule>  $alertRules
     * @return Collection<string, AlertStatusEvent>
     */
    public function fetchBaseline(Collection $alertRules, Carbon $before): Collection
    {
        $result = collect();

        foreach ($alertRules as $alertRuleId => $alertRule) {
            $document = ApiAlertStatusHistory::query()
                ->where('alertRuleId', $alertRule->_id)
                ->where('createdAt', '<', $before)
                ->orderByDesc('createdAt')
                ->first();

            $result->put(
                $alertRuleId,
                $document !== null
                    ? $this->toEvent($document)
                    : new AlertStatusEvent(
                        alertRuleId: (string) $alertRuleId,
                        occurredAt: $before->copy()->subSecond(),
                        status: AlertRule::RESOlVED,
                        count: 0,
                        summary: null,
                    ),
            );
        }

        return $result;
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
