<?php

namespace App\Services\AlertStatus\Sources;

use App\Models\AlertRule;
use App\Models\GrafanaWebhookAlert;
use App\Services\AlertMessage\AlertMessageTemplateRenderer;
use App\Services\AlertStatus\AlertStatusEvent;
use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Shared source for both GRAFANA and PMM alert rule types, which both write to
 * the `grafana_webhook_alerts` collection.
 */
final class GrafanaStatusEventSource implements AlertStatusEventSource
{
    public function __construct(
        private readonly AlertMessageTemplateRenderer $messageRenderer = new AlertMessageTemplateRenderer,
    ) {}

    public function fetchEvents(Collection $alertRules, Carbon $from, Carbon $to): Collection
    {
        $ids = $alertRules->pluck('_id')->all();

        if ($ids === []) {
            return collect();
        }

        return GrafanaWebhookAlert::query()
            ->whereIn('alertRuleId', $ids)
            ->where('createdAt', '>=', $from)
            ->where('createdAt', '<=', $to)
            ->orderBy('createdAt')
            ->get()
            ->map(fn (GrafanaWebhookAlert $document) => $this->toEvent($document, $alertRules))
            ->values();
    }

    public function fetchBaseline(Collection $alertRules, Carbon $before): Collection
    {
        $result = collect();

        foreach ($alertRules as $alertRuleId => $alertRule) {
            $document = GrafanaWebhookAlert::query()
                ->where('alertRuleId', $alertRule->_id)
                ->where('createdAt', '<', $before)
                ->orderByDesc('createdAt')
                ->first();

            if ($document !== null) {
                $result->put($alertRuleId, $this->toEvent($document, $alertRules));
            }
        }

        return $result;
    }

    /**
     * @param  Collection<string, AlertRule>  $alertRules
     */
    private function toEvent(GrafanaWebhookAlert $document, Collection $alertRules): AlertStatusEvent
    {
        $alerts = collect($document->alerts ?? []);
        $isFiring = $document->status === GrafanaWebhookAlert::FIRING;

        $status = match (true) {
            ! $isFiring => AlertRule::RESOlVED,
            $alerts->isNotEmpty() && $alerts->every(fn (array $alert) => ($alert['labels']['severity'] ?? null) === 'warning') => AlertRule::WARNING,
            default => AlertRule::CRITICAL,
        };

        $alertRuleId = (string) $document->alertRuleId;
        $alertRule = $alertRules->get($alertRuleId);

        $summary = $isFiring && $alertRule !== null
            ? $this->messageRenderer->renderDefault($alertRule, $document->toArray())
            : null;

        return new AlertStatusEvent(
            alertRuleId: $alertRuleId,
            occurredAt: Carbon::parse($document->createdAt),
            status: $status,
            count: $alerts->count(),
            summary: $summary,
        );
    }
}
