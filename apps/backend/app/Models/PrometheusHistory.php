<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

// use MongoDB\Laravel\Eloquent\Model;

class PrometheusHistory extends BaseModel
{
    public $timestamps = true;

    public static $title = 'Prometheus History';

    public static $KEY = 'prometheus_history';

    protected $guarded = ['id', '_id'];

    protected $casts = [];

    public const RESOLVED = 1;

    public const FIRE = 2;

    public const NOTIFICATION = 3;

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alertRuleId', '_id');
    }

    public function getAlertRulePrometheus(): ?AlertRulePrometheus
    {

        $alertRulePrometheus = AlertRulePrometheus::where('name', $this['instance'].'-'.$this['labels']['alertname'])->first();

        if ($alertRulePrometheus) {
            return $alertRulePrometheus;
        } else {
            return null;
        }
    }
}
