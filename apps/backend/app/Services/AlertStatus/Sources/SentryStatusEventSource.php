<?php

namespace App\Services\AlertStatus\Sources;

use App\Models\AlertRule;
use App\Models\SentryWebhookAlert;
use App\Services\AlertStatus\AlertStatusEvent;
use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use App\Services\AlertStatus\Sources\Concerns\QueriesHistoryModel;
use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Model;

final class SentryStatusEventSource implements AlertStatusEventSource
{
    use QueriesHistoryModel;

    protected function modelClass(): string
    {
        return SentryWebhookAlert::class;
    }

    /**
     * @param  SentryWebhookAlert  $document
     */
    protected function toEvent(Model $document): AlertStatusEvent
    {
        $status = match ($document->action) {
            AlertRule::CRITICAL, AlertRule::TRIGGERED => AlertRule::CRITICAL,
            AlertRule::WARNING => AlertRule::WARNING,
            AlertRule::RESOlVED => AlertRule::RESOlVED,
            default => AlertRule::UNKNOWN,
        };

        $summary = $status === AlertRule::RESOlVED ? null : $document->defaultMessage();

        return new AlertStatusEvent(
            alertRuleId: (string) $document->alertRuleId,
            occurredAt: Carbon::parse($document->createdAt),
            status: $status,
            count: 1,
            summary: $summary,
        );
    }
}
