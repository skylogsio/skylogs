<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertRule;
use App\Models\ZabbixCheck;
use App\Models\ZabbixWebhookAlert;
use App\Services\Ha\AlertStateValue;

final class ZabbixStateWriter extends ArrayCheckStateWriter
{
    protected function checkModel(): string
    {
        return ZabbixCheck::class;
    }

    protected function attribute(): string
    {
        return 'fireEvents';
    }

    protected function instanceId(mixed $entry): string
    {
        return (string) $entry;
    }

    /**
     * The array holds the ids of the events that are firing and nothing else,
     * so being present is the whole of the state.
     */
    protected function stateForEntry(mixed $entry): string
    {
        return AlertRule::CRITICAL;
    }

    protected function keepsResolvedEntries(): bool
    {
        return false;
    }

    protected function entryFor(AlertStateValue $value): mixed
    {
        return $value->key->instanceId;
    }

    /**
     * The Zabbix check keeps no state attribute; its fire count is the size of
     * the event array, which the alert rule aggregate already carries.
     *
     * @param  array<int, mixed>  $entries
     */
    protected function checkState(array $entries, AlertStateValue $value): mixed
    {
        return null;
    }

    /**
     * Zabbix, like Grafana, is its own history: the timeline reads the webhook
     * documents rather than a dedicated history collection.
     */
    public function writeHistory(AlertRule $alertRule, AlertStateValue $value): void
    {
        ZabbixWebhookAlert::create([
            'alertRuleId' => $alertRule->getKey(),
            'alertRuleName' => $value->alertRuleName,
            'dataSourceId' => $value->extra('dataSourceId'),
            'dataSourceName' => $value->extra('dataSourceName'),
            'event_id' => $value->key->instanceId,
            'event_status' => $value->isFiring() ? ZabbixWebhookAlert::PROBLEM : ZabbixWebhookAlert::RESOLVED,
            'event_severity' => $value->extra('eventSeverity'),
            'alert_subject' => $value->extra('alertSubject'),
            'alert_message' => $value->extra('alertMessage'),
        ]);
    }
}
