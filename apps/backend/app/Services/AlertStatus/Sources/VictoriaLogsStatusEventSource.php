<?php

namespace App\Services\AlertStatus\Sources;

use App\Models\AlertRule;
use App\Models\VictoriaLogsCheck;
use App\Models\VictoriaLogsHistory;
use App\Services\AlertStatus\AlertStatusEvent;
use App\Services\AlertStatus\Contracts\AlertStatusEventSource;
use App\Services\AlertStatus\Sources\Concerns\QueriesHistoryModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use MongoDB\Laravel\Eloquent\Model;

final class VictoriaLogsStatusEventSource implements AlertStatusEventSource
{
    use QueriesHistoryModel {
        fetchEvents as private fetchHistoryEvents;
    }

    protected function modelClass(): string
    {
        return VictoriaLogsHistory::class;
    }

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
            $document = VictoriaLogsHistory::query()
                ->where('alertRuleId', $alertRule->_id)
                ->where('createdAt', '<', $before)
                ->orderByDesc('createdAt')
                ->first();

            $check = $checks->get((string) $alertRuleId);

            if ($document !== null) {
                $event = $this->toEvent($document);

                if ($this->shouldReconcileAsFire($event, $check)) {
                    $event = $this->syntheticFireEvent($alertRule, $check, $before->copy()->subSecond());
                }

                $result->put($alertRuleId, $event);

                continue;
            }

            if ($check !== null && (int) $check->state === VictoriaLogsCheck::FIRE) {
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
     * @return Collection<string, VictoriaLogsCheck>
     */
    private function checksForAlertRules(Collection $alertRules): Collection
    {
        return VictoriaLogsCheck::query()
            ->whereIn('alertRuleId', $alertRules->pluck('_id')->all())
            ->get()
            ->keyBy(fn (VictoriaLogsCheck $check): string => (string) $check->alertRuleId);
    }

    private function shouldReconcileAsFire(AlertStatusEvent $baselineEvent, ?VictoriaLogsCheck $check): bool
    {
        return $check !== null
            && (int) $check->state === VictoriaLogsCheck::FIRE
            && $baselineEvent->status === AlertRule::RESOlVED;
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

            if ($check === null || (int) $check->state !== VictoriaLogsCheck::FIRE) {
                continue;
            }

            $ruleEvents = $eventsByRule->get((string) $alertRuleId, collect())
                ->sortBy(fn (AlertStatusEvent $event): int => $event->occurredAt->getTimestamp())
                ->values();

            if ($ruleEvents->last()?->status === AlertRule::CRITICAL) {
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
        VictoriaLogsCheck $check,
        Carbon $from,
        Carbon $to,
    ): ?Carbon {
        $fireDocument = VictoriaLogsHistory::query()
            ->where('alertRuleId', $alertRule->_id)
            ->where('state', VictoriaLogsHistory::FIRE)
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

    private function syntheticFireEvent(AlertRule $alertRule, VictoriaLogsCheck $check, Carbon $occurredAt): AlertStatusEvent
    {
        $fireDocument = VictoriaLogsHistory::query()
            ->where('alertRuleId', $alertRule->_id)
            ->where('state', VictoriaLogsHistory::FIRE)
            ->orderByDesc('createdAt')
            ->first();

        $count = (int) ($fireDocument->currentCountDocument ?? $check->currentCountDocument ?? 0);

        return new AlertStatusEvent(
            alertRuleId: (string) $alertRule->_id,
            occurredAt: $occurredAt,
            status: AlertRule::CRITICAL,
            count: $count,
            summary: sprintf(
                'Query "%s" matched %d document(s) (threshold %d) over the last %s minute(s).',
                $check->queryString ?? $alertRule->queryString ?? '',
                $count,
                (int) ($check->countDocument ?? $alertRule->countDocument ?? 0),
                $check->minutes ?? $alertRule->minutes ?? '?',
            ),
        );
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
