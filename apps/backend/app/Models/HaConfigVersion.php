<?php

namespace App\Models;

/**
 * Counters that let a follower skip a configuration snapshot it already has.
 *
 * Two named counters live here: the local one an observer bumps on every write
 * to a replicated collection, and the last leader version this node applied.
 */
class HaConfigVersion extends BaseModel
{
    protected $collection = 'ha_config_versions';

    /**
     * Bumped by HaConfigObserver whenever replicated configuration changes.
     */
    public const LOCAL = 'config';

    /**
     * The leader version this node last pulled and applied.
     */
    public const APPLIED_LEADER = 'appliedLeaderConfig';

    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }
}
