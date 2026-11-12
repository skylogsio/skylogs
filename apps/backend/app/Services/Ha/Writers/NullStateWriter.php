<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertRule;
use App\Services\Ha\AlertStateKey;
use App\Services\Ha\AlertStateValue;

/**
 * Types that hold no runtime state worth replicating, and so nothing to apply.
 */
final class NullStateWriter implements StateWriter
{
    public function localState(AlertRule $alertRule, AlertStateValue $value): string
    {
        return $value->state;
    }

    public function write(AlertRule $alertRule, AlertStateValue $value): void {}

    public function writeHistory(AlertRule $alertRule, AlertStateValue $value): void {}

    public function remove(AlertRule $alertRule, AlertStateKey $key): void {}
}
