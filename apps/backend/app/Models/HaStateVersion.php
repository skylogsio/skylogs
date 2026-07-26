<?php

namespace App\Models;

/**
 * Per key monotonic counter for replicated alert state.
 *
 * A counter rather than a clock: node clocks drift, so timestamps could not be
 * trusted to order two writes to the same slot.
 */
class HaStateVersion extends BaseModel
{
    protected $collection = 'ha_state_versions';

    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }
}
