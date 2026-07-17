<?php

namespace App\Services\AlertStatus\Sources;

use App\Models\AlertRule;
use App\Models\PrometheusCheck;
use App\Models\PrometheusHistory;
use App\Services\AlertMessage\AlertMessageTemplateRenderer;
use App\Services\AlertStatus\AlertStatusEvent;
use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class PrometheusStatusEventSource implements AlertStatusEventSource
{
    public function __construct(
        private readonly AlertMessageTemplateRenderer $messageRenderer = new AlertMessageTemplateRenderer,
    ) {}

    /**
     * Alerts with no history before the window are assumed resolved unless the check is still firing.
     *
     * @param  Collection<string, AlertRule>  $alertRules
     * @return Collection<string, AlertStatusEvent>
     */
    public function fetchBaseline(Collection $alertRules, Carbon $before): Collection
    {
        $checks = $this->checksForAlertRules($alertRules);
        $result = collect();

        foreach ($alertRules as $alertRuleId => $alertRule) {
            $document = PrometheusHistory::query()
                ->where('alertRuleId', $alertRule->_id)
                ->where('createdAt', '<', $before)
                ->orderByDesc('createdAt')
                ->first();

            $check = $checks->get((string) $alertRuleId);

            if ($document !== null) {
                $event = $this->toEvent($document, $alertRules);

                if ($this->shouldReconcileAsFire($event, $check)) {
                    $event = $this->syntheticFireEvent($alertRule, $check, $before->copy()->subSecond());
                }

                $result->put($alertRuleId, $event);

                continue;
            }

            if ($check !== null && (int) $check->state === PrometheusCheck::FIRE) {
                $result->put($alertRuleId, $this->syntheticFireEvent($alertRule, $check, $before->copy()->subSecond()));

                continue;
            }

            $result->put(
                $alertRuleId,
                new AlertStatusEvent(
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

    public function fetchEvents(Collection $alertRules, Carbon $from, Carbon $to): Collection
    {
        $events = $this->fetchHistoryEvents($alertRules, $from, $to);

        return $this->supplementWithOpenFireState($alertRules, $events, $from, $to);
    }

    /**
     * @param  Collection<string, AlertRule>  $alertRules
     * @return Collection<int, AlertStatusEvent>
     */
    private function fetchHistoryEvents(Collection $alertRules, Carbon $from, Carbon $to): Collection
    {
        $ids = $alertRules->pluck('_id')->all();

        if ($ids === []) {
            return collect();
        }

        return PrometheusHistory::query()
            ->whereIn('alertRuleId', $ids)
            ->where('createdAt', '>=', $from)
            ->where('createdAt', '<=', $to)
            ->orderBy('createdAt')
            ->get()
            ->map(fn (PrometheusHistory $document) => $this->toEvent($document, $alertRules))
            ->values()
            ->toBase();
    }

    /**
     * @param  Collection<string, AlertRule>  $alertRules
     * @return Collection<string, PrometheusCheck>
     */
    private function checksForAlertRules(Collection $alertRules): Collection
    {
        return PrometheusCheck::query()
            ->whereIn('alertRuleId', $alertRules->pluck('_id')->all())
            ->get()
            ->keyBy(fn (PrometheusCheck $check): string => (string) $check->alertRuleId);
    }

    private function shouldReconcileAsFire(AlertStatusEvent $baselineEvent, ?PrometheusCheck $check): bool
    {
        return $check !== null
            && (int) $check->state === PrometheusCheck::FIRE
            && ! $this->isFiringStatus($baselineEvent->status);
    }

    private function isFiringStatus(string $status): bool
    {
        return in_array($status, [AlertRule::CRITICAL, AlertRule::WARNING], true);
    }

    /**
     * @param  Collection<int, AlertStatusEvent>  $events
     * @return Collection<int, AlertStatusEvent>
     */
    private function supplementWithOpenFireState(
        Collection $alertRules,
        Collection $events,
        Carbon $from,
        Carbon $to,
    ): Collection {
        $checks = $this->checksForAlertRules($alertRules);
        $eventsByRule = $events->groupBy('alertRuleId');
        $supplements = collect();

        foreach ($alertRules as $alertRuleId => $alertRule) {
            $check = $checks->get((string) $alertRuleId);

            if ($check === null || (int) $check->state !== PrometheusCheck::FIRE) {
                continue;
            }

            $ruleEvents = $eventsByRule->get((string) $alertRuleId, collect())
                ->sortBy(fn (AlertStatusEvent $event): int => $event->occurredAt->getTimestamp())
                ->values();

            if ($this->isFiringStatus((string) $ruleEvents->last()?->status)) {
                continue;
            }

            $occurredAt = $this->resolveOpenFireTimestamp($alertRule, $check, $from, $to);

            if ($occurredAt === null) {
                continue;
            }

            $supplements->push($this->syntheticFireEvent($alertRule, $check, $occurredAt));
        }

        return $events->merge($supplements)->values();
    }

    private function resolveOpenFireTimestamp(
        AlertRule $alertRule,
        PrometheusCheck $check,
        Carbon $from,
        Carbon $to,
    ): ?Carbon {
        $fireDocument = PrometheusHistory::query()
            ->where('alertRuleId', $alertRule->_id)
            ->where('state', PrometheusHistory::FIRE)
            ->where('createdAt', '>=', $from)
            ->where('createdAt', '<=', $to)
            ->orderByDesc('createdAt')
            ->first();

        if ($fireDocument !== null) {
            return Carbon::parse($fireDocument->createdAt);
        }

        $updatedAt = $check->updatedAt !== null ? Carbon::parse($check->updatedAt) : null;

        if ($updatedAt !== null && $updatedAt->betweenIncluded($from, $to)) {
            return $updatedAt;
        }

        return null;
    }

    private function syntheticFireEvent(AlertRule $alertRule, PrometheusCheck $check, Carbon $occurredAt): AlertStatusEvent
    {
        $status = $this->statusFromCheck($check);

        return new AlertStatusEvent(
            alertRuleId: (string) $alertRule->_id,
            occurredAt: $occurredAt,
            status: $status,
            count: $this->countFromCheck($check),
            summary: $this->isFiringStatus($status)
                ? $this->messageRenderer->renderDefault($alertRule, $check->toArray())
                : null,
        );
    }

    private function statusFromCheck(PrometheusCheck $check): string
    {
        if ((int) $check->state !== PrometheusCheck::FIRE) {
            return AlertRule::RESOlVED;
        }

        $alerts = collect($check->alerts ?? []);

        return match (true) {
            $alerts->isNotEmpty() && $alerts->every(fn (array $alert) => ($alert['labels']['severity'] ?? null) === 'warning') => AlertRule::WARNING,
            default => AlertRule::CRITICAL,
        };
    }

    private function countFromCheck(PrometheusCheck $check): int
    {
        return collect($check->alerts ?? [])
            ->filter(fn (array $alert): bool => empty($alert['skylogsStatus']) || $alert['skylogsStatus'] == PrometheusCheck::FIRE)
            ->count();
    }

    /**
     * @param  Collection<string, AlertRule>  $alertRules
     */
    private function toEvent(PrometheusHistory $document, Collection $alertRules): AlertStatusEvent
    {
        $alerts = collect($document->alerts ?? []);
        $isFiring = (int) $document->state === PrometheusHistory::FIRE;

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
            count: (int) ($document->countFire ?? $alerts->count()),
            summary: $summary,
        );
    }
}
