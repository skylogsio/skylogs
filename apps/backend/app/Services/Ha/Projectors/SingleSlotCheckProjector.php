<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Services\Ha\AlertStateChange;
use Illuminate\Support\Arr;

/**
 * Types that have exactly one check document per alert rule, so exactly one
 * replicated slot.
 */
abstract class SingleSlotCheckProjector extends AbstractStateProjector
{
    /**
     * @return class-string<BaseModel>
     */
    abstract protected function checkModel(): string;

    abstract protected function stateFor(BaseModel $check): string;

    public function projectCheck(BaseModel $check, AlertRule $alertRule): array
    {
        return [$this->change($alertRule, $check)];
    }

    public function projectDeletion(BaseModel $check, AlertRule $alertRule): array
    {
        return [AlertStateChange::tombstone($this->key($alertRule))];
    }

    public function projectRule(AlertRule $alertRule): array
    {
        $model = $this->checkModel();

        return [$this->change($alertRule, $model::where('alertRuleId', $alertRule->getKey())->first())];
    }

    private function change(AlertRule $alertRule, ?BaseModel $check): AlertStateChange
    {
        $key = $this->key($alertRule);

        return new AlertStateChange($key, $this->value(
            $alertRule,
            $key,
            [],
            $check ? $this->stateFor($check) : ($alertRule->state ?? AlertRule::UNKNOWN),
            $this->changedAt($check),
            ['check' => $this->checkAttributes($check)],
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function checkAttributes(?BaseModel $check): ?array
    {
        if (! $check) {
            return null;
        }

        return Arr::except($check->attributesToArray(), ['_id', 'id', 'createdAt', 'updatedAt']);
    }
}
