<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Models\VictoriaLogsCheck;
use App\Models\VictoriaLogsHistory;
use App\Services\Ha\AlertStateValue;

final class VictoriaLogsStateWriter extends SingleSlotStateWriter
{
    protected function checkModel(): string
    {
        return VictoriaLogsCheck::class;
    }

    protected function stateFor(BaseModel $check): string
    {
        return $check->state == VictoriaLogsCheck::FIRE ? AlertRule::CRITICAL : AlertRule::RESOlVED;
    }

    public function writeHistory(AlertRule $alertRule, AlertStateValue $value): void
    {
        $check = $value->extraArray('check');

        VictoriaLogsHistory::create([
            'alertRuleId' => $alertRule->getKey(),
            'alertRuleName' => $value->alertRuleName ?? $alertRule->name,
            'dataSourceId' => $alertRule->dataSourceId,
            'queryString' => $alertRule->queryString,
            'conditionType' => $alertRule->conditionType,
            'minutes' => $alertRule->minutes,
            'countDocument' => $alertRule->countDocument,
            'currentCountDocument' => $check['currentCountDocument'] ?? 0,
            'state' => $value->isFiring() ? VictoriaLogsCheck::FIRE : VictoriaLogsCheck::RESOLVED,
        ]);
    }
}
