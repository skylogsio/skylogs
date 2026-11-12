<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Models\ElasticCheck;

final class ElasticProjector extends SingleSlotCheckProjector
{
    protected function checkModel(): string
    {
        return ElasticCheck::class;
    }

    protected function stateFor(BaseModel $check): string
    {
        return $check->state == ElasticCheck::FIRE ? AlertRule::CRITICAL : AlertRule::RESOlVED;
    }
}
