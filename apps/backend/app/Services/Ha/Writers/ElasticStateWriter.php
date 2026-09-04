<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Models\ElasticCheck;
use App\Models\ElasticHistory;
use App\Services\Ha\AlertStateValue;

final class ElasticStateWriter extends SingleSlotStateWriter
{
    protected function checkModel(): string
    {
        return ElasticCheck::class;
    }

    protected function stateFor(BaseModel $check): string
    {
        return $check->state == ElasticCheck::FIRE ? AlertRule::CRITICAL : AlertRule::RESOlVED;
    }

    public function writeHistory(AlertRule $alertRule, AlertStateValue $value): void
    {
        $check = $value->extraArray('check');

        ElasticHistory::create([
            'alertRuleId' => $alertRule->getKey(),
            'alertRuleName' => $value->alertRuleName ?? $alertRule->name,
            'dataSourceId' => $alertRule->dataSourceId,
            'dataviewName' => $alertRule->dataviewName,
            'dataviewTitle' => $alertRule->dataviewTitle,
            'queryString' => $alertRule->queryString,
            'conditionType' => $alertRule->conditionType,
            'minutes' => $alertRule->minutes,
            'countDocument' => $alertRule->countDocument,
            'currentCountDocument' => $check['currentCountDocument'] ?? 0,
            'state' => $value->isFiring() ? ElasticCheck::FIRE : ElasticCheck::RESOLVED,
        ]);
    }
}
