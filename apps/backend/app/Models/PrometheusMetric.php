<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

class PrometheusMetric extends BaseModel
{

    public $timestamps = true;
    public static $title = "Prometheus Metric";
    public static $KEY = "prometheus_metric";

    protected $guarded = ['id', '_id',];


    public function prometheusInstance(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, "instance_id", "_id");
    }


}
