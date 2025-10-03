<?php

namespace App\Jobs;

use App\Models\Notify;
use App\Services\SendNotifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

//use Log;

class SendNotifyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Notify $notify;

    public const HEALTH_CHECK = "health-check";
    public const ELASTIC_CHECK = "elastic-check";
    public const RESOLVED_MANUALLY = "resolved_manually";
    public const PROMETHEUS_WEBHOOK_RESOLVE = "prometheus-webhook-resolve";
    public const PROMETHEUS_WEBHOOK_FIRE = "prometheus-webhook-fire";
    public const GRAFANA_WEBHOOK = "grafana-webhook";
    public const PROMETHEUS_RESOLVE = "prometheus-resolve";
    public const PROMETHEUS_FIRE = "prometheus-fire";
    public const API_RESOLVE = "user-resolve";
    public const API_FIRE = "user-fire";
    public const API_NOTIFICATION = "user-notification";
    public const USER_TEST = "user-test";
    public const ALERT_RULE_TEST = "alert-rule-test";
    public const ALERT_RULE_ACKNOWLEDGED = "alert-rule-acknowledged";
    public const SENTRY_WEBHOOK = "sentry-webhook";
    public const METABASE_WEBHOOK = "metabase-webhook";
    public const ZABBIX_WEBHOOK = "zabbix-webhook";
    public const SENTRY_WEBHOOK_TEST = "sentry-webhook-test";

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Notify $notify)
    {

        $this->onQueue('sendNotifies');
        $this->notify = $notify;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->notify->status = Notify::STATUS_RUNNING;
        $this->notify->save();

        switch ($this->notify->type) {
            case self::RESOLVED_MANUALLY:
            case self::GRAFANA_WEBHOOK:
            case self::HEALTH_CHECK:
            case self::ELASTIC_CHECK:
            case self::API_RESOLVE:
            case self::API_FIRE:
            case self::API_NOTIFICATION:
            case self::SENTRY_WEBHOOK:
            case self::METABASE_WEBHOOK:
            case self::ZABBIX_WEBHOOK:
            case self::PROMETHEUS_RESOLVE:
            case self::PROMETHEUS_FIRE:
                SendNotifyService::SendMessage($this->notify);
                break;
            case self::ALERT_RULE_TEST:
                SendNotifyService::SendMessage($this->notify, isTest:true);
                break;
            case self::ALERT_RULE_ACKNOWLEDGED:
                SendNotifyService::SendMessage($this->notify, isAcknowledged:true);
                break;
        }
        $this->notify->status = Notify::STATUS_DONE;
        $this->notify->save();
    }

    public function fail($exception = null)
    {
        if (is_array($exception)) {

            \Log::debug("notify error ", $exception);
        } else {
            try {
                \Log::debug("notify error ", array($exception));

            } catch (\Exception $exception) {

            }
        }

        $this->notify->error = $exception;
        $this->notify->status = Notify::STATUS_FAIL;
        $this->notify->save();
//        echo $exception;
    }


}
