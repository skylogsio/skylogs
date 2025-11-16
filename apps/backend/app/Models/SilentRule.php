<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

class SilentRule extends BaseModel
{
    public const ALERTNAME = 'alertname';

    public const TYPES = 'types';

    public const LABELS = 'labels';

    public const TAGS = 'tags';

    public const CRITICAL = 'critical';

    public const TRIGGERED = 'triggered';

    public const RESOlVED = 'resolved';

    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessUsers()
    {
        return $this->embedsMany(User::class, 'accessUsers');
    }

    public function endpoints()
    {
        return $this->hasMany(Endpoint::class);
        //        return Endpoint::whereIn("_id",$this->en)
        //        return $this->embedsMany(Endpoint::class,"notifies");
    }

    public static $types = [
        'api' => 'Api',
        'notification' => 'Notification',
        'sentry' => 'Sentry',
        'prometheus' => 'Prometheus',
        'grafana' => 'Grafana',
        'health' => 'Health',
        'elastic' => 'Elastic',
        'zabbix' => 'Zabbix',
    ];
}
