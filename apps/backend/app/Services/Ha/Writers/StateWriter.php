<?php

namespace App\Services\Ha\Writers;

use App\Models\AlertRule;
use App\Services\Ha\AlertStateKey;
use App\Services\Ha\AlertStateValue;

/**
 * Applies one replicated slot to this node's own documents.
 *
 * The mirror image of App\Services\Ha\Projectors\StateProjector: the projector
 * turns a local write into a slot, the writer turns a slot back into the same
 * local write, so that a follower's collections end up byte comparable with the
 * leader's and the alert status timeline is derived identically on both.
 */
interface StateWriter
{
    /**
     * The state this node currently holds for the slot, expressed in AlertRule
     * state constants. A slot this node has never seen reads as resolved.
     */
    public function localState(AlertRule $alertRule, AlertStateValue $value): string;

    /**
     * Write the slot into the type's check document.
     */
    public function write(AlertRule $alertRule, AlertStateValue $value): void;

    /**
     * Record the transition in whichever collection the timeline reads for this
     * type. Called only when the slot actually moved, never on a replay.
     */
    public function writeHistory(AlertRule $alertRule, AlertStateValue $value): void;

    /**
     * Drop the slot entirely, because the leader tombstoned it.
     */
    public function remove(AlertRule $alertRule, AlertStateKey $key): void;
}
