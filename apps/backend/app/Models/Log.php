<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

class Log extends BaseModel
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
