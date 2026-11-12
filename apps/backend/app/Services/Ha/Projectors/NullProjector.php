<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertRule;
use App\Models\BaseModel;

/**
 * Types that hold no runtime state worth replicating.
 */
final class NullProjector implements StateProjector
{
    public function projectCheck(BaseModel $check, AlertRule $alertRule): array
    {
        return [];
    }

    public function projectDeletion(BaseModel $check, AlertRule $alertRule): array
    {
        return [];
    }

    public function projectRule(AlertRule $alertRule): array
    {
        return [];
    }
}
