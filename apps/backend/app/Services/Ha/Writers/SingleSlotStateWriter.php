<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Services\Ha\AlertStateKey;
use App\Services\Ha\AlertStateValue;

/**
 * Types with exactly one check document per alert rule, so exactly one slot.
 * The leader ships the whole check body, which makes the write a straight copy.
 */
abstract class SingleSlotStateWriter implements StateWriter
{
    /**
     * @return class-string<BaseModel>
     */
    abstract protected function checkModel(): string;

    abstract protected function stateFor(BaseModel $check): string;

    /**
     * Falls back to the rule's own state when the check is missing, rather than
     * assuming resolved. AlertRuleObserver drops a health check every time its
     * rule is updated, so for that type the rule is often the only surviving
     * record of where the slot stood, and assuming resolved would swallow the
     * resolve transition and leave a gap in the follower's timeline.
     */
    public function localState(AlertRule $alertRule, AlertStateValue $value): string
    {
        $check = $this->findCheck($alertRule);

        return $check ? $this->stateFor($check) : ($alertRule->state ?? AlertRule::RESOlVED);
    }

    public function write(AlertRule $alertRule, AlertStateValue $value): void
    {
        $model = $this->checkModel();
        $check = $this->findCheck($alertRule) ?? new $model;

        foreach ($value->extraArray('check') as $attribute => $attributeValue) {
            $check->setAttribute($attribute, $attributeValue);
        }

        $check->setAttribute('alertRuleId', $alertRule->getKey());
        $check->save();
    }

    public function remove(AlertRule $alertRule, AlertStateKey $key): void
    {
        $this->findCheck($alertRule)?->delete();
    }

    protected function findCheck(AlertRule $alertRule): ?BaseModel
    {
        $model = $this->checkModel();

        return $model::where('alertRuleId', $alertRule->getKey())->first();
    }
}
