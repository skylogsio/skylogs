<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertRule;
use App\Models\GrafanaCheck;
use App\Models\GrafanaWebhookAlert;
use App\Services\GrafanaService;
use App\Services\Ha\AlertStateValue;

/**
 * Serves both Grafana and PMM rules, which share the grafana_checks and
 * grafana_webhook_alerts collections.
 */
final class GrafanaStateWriter extends ArrayCheckStateWriter
{
    protected function checkModel(): string
    {
        return GrafanaCheck::class;
    }

    protected function attribute(): string
    {
        return 'alerts';
    }

    protected function instanceId(mixed $entry): string
    {
        if (! is_array($entry)) {
            return '';
        }

        return (string) ($entry['instanceKey'] ?? GrafanaService::grafanaAlertInstanceKey($entry));
    }

    protected function stateForEntry(mixed $entry): string
    {
        $status = is_array($entry) ? ($entry['status'] ?? null) : null;

        return $status === GrafanaWebhookAlert::RESOLVED ? AlertRule::RESOlVED : AlertRule::CRITICAL;
    }

    /**
     * A resolved Grafana alert is dropped from the batch rather than marked, so
     * the stored array only ever holds firing instances.
     */
    protected function keepsResolvedEntries(): bool
    {
        return false;
    }

    protected function entryFor(AlertStateValue $value): mixed
    {
        $entry = $value->extraArray('entry');

        if ($entry === []) {
            $entry = [
                'labels' => $value->instance['labels'] ?? [],
                'annotations' => $value->extraArray('annotations'),
                'dataSourceId' => $value->extra('dataSourceId'),
                'alertRuleName' => $value->alertRuleName,
            ];
        }

        $entry['status'] = GrafanaWebhookAlert::FIRING;
        $entry['instanceKey'] = $value->key->instanceId;

        return $entry;
    }

    /**
     * @param  array<int, mixed>  $entries
     */
    protected function checkState(array $entries, AlertStateValue $value): mixed
    {
        return $entries === [] ? GrafanaWebhookAlert::RESOLVED : GrafanaWebhookAlert::FIRING;
    }

    /**
     * Grafana has no history collection of its own: the timeline is built from
     * the webhook documents, so a transition has to leave one behind.
     */
    public function writeHistory(AlertRule $alertRule, AlertStateValue $value): void
    {
        $entry = $value->extraArray('entry');
        $entry['status'] = $value->isFiring() ? GrafanaWebhookAlert::FIRING : GrafanaWebhookAlert::RESOLVED;
        $entry['instanceKey'] = $value->key->instanceId;

        GrafanaWebhookAlert::create([
            'alertRuleId' => $alertRule->getKey(),
            'alertRuleName' => $value->alertRuleName,
            'dataSourceId' => $value->extra('dataSourceId'),
            'status' => $entry['status'],
            'alerts' => [$entry],
        ]);
    }
}
