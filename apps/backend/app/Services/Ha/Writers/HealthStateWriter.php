<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Models\HealthCheck;
use App\Models\HealthHistory;
use App\Services\Ha\AlertStateValue;

final class HealthStateWriter extends SingleSlotStateWriter
{
    protected function checkModel(): string
    {
        return HealthCheck::class;
    }

    protected function stateFor(BaseModel $check): string
    {
        return $check->state == HealthCheck::DOWN ? AlertRule::CRITICAL : AlertRule::RESOlVED;
    }

    public function writeHistory(AlertRule $alertRule, AlertStateValue $value): void
    {
        $check = $value->extraArray('check');
        $isDown = $value->isFiring();

        HealthHistory::create([
            'alertRuleId' => $alertRule->getKey(),
            'alertRuleName' => $alertRule->alertname,
            'checkType' => $alertRule->checkType,
            'url' => $alertRule->url,
            'threshold' => $alertRule->threshold,
            'state' => $isDown ? HealthCheck::DOWN : HealthCheck::UP,
            'counter' => $isDown ? ($check['counter'] ?? 0) : 0,
        ]);
    }
}
