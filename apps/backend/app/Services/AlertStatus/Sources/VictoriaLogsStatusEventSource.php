<?php

namespace App\Services\AlertStatus\Sources;

use App\Models\AlertRule;
use App\Models\VictoriaLogsHistory;
use App\Services\AlertStatus\AlertStatusEvent;
use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use App\Services\AlertStatus\Sources\Concerns\QueriesHistoryModel;
use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Model;

final class VictoriaLogsStatusEventSource implements AlertStatusEventSource
{
    use QueriesHistoryModel;

    protected function modelClass(): string
    {
        return VictoriaLogsHistory::class;
    }

    /**
     * @param  VictoriaLogsHistory  $document
     */
    protected function toEvent(Model $document): AlertStatusEvent
    {
        $status = match ((int) $document->state) {
            VictoriaLogsHistory::FIRE => AlertRule::CRITICAL,
            VictoriaLogsHistory::RESOLVED => AlertRule::RESOlVED,
            default => AlertRule::UNKNOWN,
        };

        $count = (int) ($document->currentCountDocument ?? 0);

        $summary = $status === AlertRule::CRITICAL
            ? sprintf(
                'Query "%s" matched %d document(s) (threshold %d) over the last %s minute(s).',
                $document->queryString ?? '',
                $count,
                (int) ($document->countDocument ?? 0),
                $document->minutes ?? '?',
            )
            : null;

        return new AlertStatusEvent(
            alertRuleId: (string) $document->alertRuleId,
            occurredAt: Carbon::parse($document->createdAt),
            status: $status,
            count: $count,
            summary: $summary,
        );
    }
}
