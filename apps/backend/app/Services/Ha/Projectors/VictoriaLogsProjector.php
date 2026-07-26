<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Models\VictoriaLogsCheck;

final class VictoriaLogsProjector extends SingleSlotCheckProjector
{
    protected function checkModel(): string
    {
        return VictoriaLogsCheck::class;
    }

    protected function stateFor(BaseModel $check): string
    {
        return $check->state == VictoriaLogsCheck::FIRE ? AlertRule::CRITICAL : AlertRule::RESOlVED;
    }
}
