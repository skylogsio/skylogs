<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Models\HealthCheck;

final class HealthProjector extends SingleSlotCheckProjector
{
    protected function checkModel(): string
    {
        return HealthCheck::class;
    }

    protected function stateFor(BaseModel $check): string
    {
        return $check->state == HealthCheck::DOWN ? AlertRule::CRITICAL : AlertRule::RESOlVED;
    }
}
