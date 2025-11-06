<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

class ElasticHistory extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    public const RESOLVED = 1;

    public const FIRE = 2;

    public const NOTIFICATION = 3;

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alertRuleId', '_id');
    }
}
