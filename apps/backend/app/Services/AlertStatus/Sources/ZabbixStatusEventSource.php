<?php

namespace App\Services\AlertStatus\Sources;

use App\Models\AlertRule;
use App\Models\ZabbixWebhookAlert;
use App\Services\AlertStatus\AlertStatusEvent;
use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use App\Services\AlertStatus\Sources\Concerns\QueriesHistoryModel;
use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Model;

final class ZabbixStatusEventSource implements AlertStatusEventSource
{
    use QueriesHistoryModel;

    /**
     * Zabbix severities below this are reported as a warning rather than critical.
     *
     * @var array<int, string>
     */
    private const WARNING_SEVERITIES = ['Not classified', 'Information', 'Warning'];

    protected function modelClass(): string
    {
        return ZabbixWebhookAlert::class;
    }

    /**
     * @param  ZabbixWebhookAlert  $document
     */
    protected function toEvent(Model $document): AlertStatusEvent
    {
        $status = match (true) {
            $document->event_status === ZabbixWebhookAlert::RESOLVED => AlertRule::RESOlVED,
            $document->event_status === ZabbixWebhookAlert::PROBLEM
                && in_array($document->event_severity, self::WARNING_SEVERITIES, true) => AlertRule::WARNING,
            $document->event_status === ZabbixWebhookAlert::PROBLEM => AlertRule::CRITICAL,
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
