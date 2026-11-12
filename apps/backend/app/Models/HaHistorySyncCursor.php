<?php

namespace App\Models;

/**
 * Per-collection watermark a follower uses for incremental history/notify sync.
 *
 * afterUpdatedAt / afterId identify the last document successfully applied for
 * that wire alias, so the next page starts strictly after it.
 */
class HaHistorySyncCursor extends BaseModel
{
    protected $collection = 'ha_history_sync_cursors';

    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected function casts(): array
    {
        return [
            'afterUpdatedAt' => 'integer',
        ];
    }
}
