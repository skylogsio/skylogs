<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

class ApiAlertStatusHistory extends BaseModel
{
    public $timestamps = true;

    public static $title = 'Api Alert History';

    public static $KEY = 'history';

    protected $guarded = ['id', '_id'];

    public const RESOLVED = 1;

    public const FIRE = 2;

    public const NOTIFICATION = 3;

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alertname', 'alertname');
    }
}
