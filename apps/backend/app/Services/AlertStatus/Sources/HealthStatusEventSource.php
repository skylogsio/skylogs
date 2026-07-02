<?php

namespace App\Services\AlertStatus\Sources;

use App\Models\AlertRule;
use App\Models\HealthHistory;
use App\Services\AlertStatus\AlertStatusEvent;
use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use App\Services\AlertStatus\Sources\Concerns\QueriesHistoryModel;
use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Model;

final class HealthStatusEventSource implements AlertStatusEventSource
{
    use QueriesHistoryModel;

    protected function modelClass(): string
    {
        return HealthHistory::class;
    }

    /**
     * @param  HealthHistory  $document
     */
    protected function toEvent(Model $document): AlertStatusEvent
    {
        $status = match ((int) $document->state) {
            HealthHistory::DOWN => AlertRule::CRITICAL,
            HealthHistory::UP => AlertRule::RESOlVED,
            default => AlertRule::UNKNOWN,
        };

        $summary = $status === AlertRule::CRITICAL
            ? sprintf(
                'Health check for "%s" is down (checked %s time(s), threshold %s).',
                $document->url ?? $document->alertRuleName ?? '',
                $document->counter ?? '?',
                $document->threshold ?? '?',
            )
            : null;

        return new AlertStatusEvent(
            alertRuleId: (string) $document->alertRuleId,
            occurredAt: Carbon::parse($document->createdAt),
            status: $status,
            count: (int) ($document->counter ?? 0),
            summary: $summary,
        );
    }
}
