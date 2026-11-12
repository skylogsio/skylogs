<?php

namespace App\Services\Ha\Projectors;

use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Services\Ha\AlertStateChange;

/**
 * Turns a local write into the set of replicated slots it changed.
 *
 * One implementation per alert rule type, mirroring the per type shape of
 * App\Services\AlertStatus\AlertStatusEventSourceFactory.
 */
interface StateProjector
{
    /**
     * The slots changed by a write to this type's check document. Array valued
     * documents emit one change per changed instance, not one per document.
     *
     * @return array<int, AlertStateChange>
     */
    public function projectCheck(BaseModel $check, AlertRule $alertRule): array;

    /**
     * Tombstones for every slot a deleted check document was carrying.
     *
     * @return array<int, AlertStateChange>
     */
    public function projectDeletion(BaseModel $check, AlertRule $alertRule): array;

    /**
     * The slots to refresh when the rule aggregate itself changed. Bounded to
     * the firing instances: a resolved slot's own write already replicated it.
     *
     * @return array<int, AlertStateChange>
     */
    public function projectRule(AlertRule $alertRule): array;
}
