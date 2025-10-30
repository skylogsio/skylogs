<?php

namespace App\Models\DataSource;

use App\Enums\DataSourceType;
use App\Models\BaseModel;
use App\Models\User;
use App\Observers\DataSourceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use MongoDB\Laravel\Relations\BelongsTo;

#[ObservedBy(DataSourceObserver::class)]
class DataSource extends BaseModel
{
    protected $casts = [
        'type' => DataSourceType::class,
    ];

    public const UNKNOWN = 'unknown';

    public const WARNING = 'warning';

    public const CRITICAL = 'critical';

    public const TRIGGERED = 'triggered';

    public const RESOlVED = 'resolved';

    public $timestamps = true;

    public static $title = 'Alert Rule';

    public static $KEY = 'alerts';

    protected $guarded = ['id', '_id'];

    protected $appends = ['copy'];

    public function getCopyAttribute()
    {
        return match ($this->type) {
            DataSourceType::SENTRY => config('app.frontend_url').route('webhook.sentry', ['token' => $this->webhookToken], false),
            DataSourceType::GRAFANA => config('app.frontend_url').route('webhook.grafana', ['token' => $this->webhookToken], false),
            DataSourceType::PMM => config('app.frontend_url').route('webhook.pmm', ['token' => $this->webhookToken], false),
            DataSourceType::ZABBIX => config('app.frontend_url').route('webhook.zabbix', ['token' => $this->webhookToken], false),
            DataSourceType::SPLUNK => config('app.frontend_url').route('webhook.splunk', ['token' => $this->webhookToken], false),
            default => $this->webhookToken,
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatus()
    {
        return self::UNKNOWN;
    }

    public static $types = [
        'api' => 'Api',
        'notification' => 'Notification',
        'sentry' => 'Sentry',
        'metabase' => 'Metabase',
        'prometheus' => 'Prometheus',
        'grafana' => 'Grafana',
        'health' => 'Health',
        'elastic' => 'Elastic',
        'zabbix' => 'Zabbix',
    ];

    public function isOk()
    {
        //        return PrometheusInstanceService::getHealthCheck($this);
    }

    public function grafanaOrganizationsUrl()
    {
        return $this->url.'/api/orgs';
    }

    public function grafanaAlertRulesUrl()
    {
        return $this->url.'/api/v1/provisioning/alert-rules';
    }

    public function prometheusGetRulesUrl()
    {
        return $this->url.'/api/v1/rules';
    }

    public function prometheusGetLabelsUrl()
    {
        return $this->url.'/api/v1/labels';
    }

    public function prometheusGetLabelsValueUrl($label)
    {
        return $this->url."/api/v1/label/$label/values";
    }

    public function prometheusGetAlertsUrl()
    {
        return $this->url.'/api/v1/alerts';
    }

    public function prometheusGetQueryUrl($metric)
    {
        return $this->url."/api/v1/query?query=$metric";
    }

    public function zabbixApiUrl()
    {
        return $this->url.'/api_jsonrpc.php';
    }
}
