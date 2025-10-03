<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

class HealthHistory extends BaseModel
{

    public $timestamps = true;
    public static $title = "Health History";
    public static $KEY = "health_history";

    protected $guarded = ['id', '_id',];

    public const DOWN = 1;
    public const UP = 2;


    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, "alertRuleId", "_id");
    }

}
