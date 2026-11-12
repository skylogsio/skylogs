<?php

namespace App\Services\AlertStatus\Sources\Concerns;

use App\Services\AlertStatus\AlertStatusEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Shared query logic for status event sources backed by a single MongoDB history
 * collection indexed on (alertRuleId, createdAt). Concrete sources only need to
 * say which model to query and how to map a raw document to a normalized event.
 */
trait QueriesHistoryModel
{
    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    abstract protected function toEvent(Model $document): AlertStatusEvent;

    public function fetchEvents(Collection $alertRules, Carbon $from, Carbon $to): Collection
    {
        $ids = $alertRules->pluck('_id')->all();

        if ($ids === []) {
            return collect();
        }

        $modelClass = $this->modelClass();

        return $modelClass::query()
            ->whereIn('alertRuleId', $ids)
            ->where('createdAt', '>=', $from)
            ->where('createdAt', '<=', $to)
            ->orderBy('createdAt')
            ->get()
            ->map(fn (Model $document) => $this->toEvent($document))
            ->values()
            ->toBase();
    }

    public function fetchBaseline(Collection $alertRules, Carbon $before): Collection
    {
        $modelClass = $this->modelClass();
        $result = collect();

        foreach ($alertRules as $alertRuleId => $alertRule) {
            $document = $modelClass::query()
                ->where('alertRuleId', $alertRule->_id)
                ->where('createdAt', '<', $before)
                ->orderByDesc('createdAt')
                ->first();

            if ($document !== null) {
                $result->put($alertRuleId, $this->toEvent($document));
            }
        }

        return $result;
    }
}
