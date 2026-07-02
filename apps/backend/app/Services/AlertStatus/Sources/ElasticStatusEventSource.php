<?php

namespace App\Services\AlertStatus\Sources;

use App\Models\AlertRule;
use App\Models\ElasticHistory;
use App\Services\AlertStatus\AlertStatusEvent;
use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use App\Services\AlertStatus\Sources\Concerns\QueriesHistoryModel;
use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Model;

final class ElasticStatusEventSource implements AlertStatusEventSource
{
    use QueriesHistoryModel;

    protected function modelClass(): string
    {
        return ElasticHistory::class;
    }

    /**
     * @param  ElasticHistory  $document
     */
    protected function toEvent(Model $document): AlertStatusEvent
    {
        $status = match ((int) $document->state) {
            ElasticHistory::FIRE => AlertRule::CRITICAL,
            ElasticHistory::RESOLVED => AlertRule::RESOlVED,
            default => AlertRule::UNKNOWN,
        };

        $count = (int) ($document->currentCountDocument ?? 0);

        $summary = $status === AlertRule::CRITICAL
            ? sprintf(
                'Query "%s" matched %d document(s) (threshold %d) over the last %s minute(s) in "%s".',
                $document->queryString ?? '',
                $count,
                (int) ($document->countDocument ?? 0),
                $document->minutes ?? '?',
                $document->dataviewTitle ?? $document->dataviewName ?? '',
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
