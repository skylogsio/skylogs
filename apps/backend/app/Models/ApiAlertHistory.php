<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

class ApiAlertHistory extends BaseModel
{

    public $timestamps = true;
    public static $title = "Api Alert History";
    public static $KEY = "history";

    protected $guarded = ['id', '_id',];

    public const RESOLVED = 1;
    public const FIRE = 2;
    public const NOTIFICATION = 3;

    protected $appends = ['status'];

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, "alertname", "alertname");
    }

    public function getStatusAttribute()
    {
        return match ($this->state) {
            self::FIRE => AlertRule::CRITICAL,
            self::RESOLVED => AlertRule::RESOlVED,
            default => AlertRule::UNKNOWN,
        };
    }


}
