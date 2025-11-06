<?php

namespace App\Models;

class AlertRulePrometheus extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    public static $types = [
        'api' => 'Api',
        'sentry' => 'Sentry',
        'health' => 'Health',
    ];
}
