<?php

namespace App\Jobs;

use App\Models\AlertRule;
use App\Models\ElasticCheck;
use App\Models\HealthCheck;
use App\Models\PrometheusCheck;
use App\Services\SendNotifyService;
use App\Utility\Constants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IntervalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $alerts = AlertRule::whereIn('type', [Constants::PROMETHEUS])->get();
        if ($alerts->isNotEmpty()) {
            foreach ($alerts as $alert) {
                $status = $alert->getStatus();
                if ($status != AlertRule::RESOlVED && ! empty($alert->interval) && $alert->interval > 0) {
                    if (empty($alert->notify_at)) {
                        $alert->notify_at = 0;
                    }
                    switch ($alert->type) {
                        case Constants::API:
                            break;
                        case Constants::GRAFANA:
                            break;
                        case Constants::ZABBIX:
                            break;
                        case Constants::NOTIFICATION:
                            break;
                        case Constants::SENTRY:
                            if ($status == AlertRule::CRITICAL || $status == AlertRule::WARNING) {
                                if ($alert->notify_at + (60 * $alert->interval) < time()) {
                                    $sentryWebhook = $alert->sentryWebhook;
                                    if (! empty($sentryWebhook)) {
                                        $alert->notify_at = time();
                                        $alert->save();
                                        SendNotifyService::CreateNotify(SendNotifyJob::SENTRY_WEBHOOK, $sentryWebhook, $alert->_id);
                                    }
                                }
                            }
                            break;
                        case Constants::HEALTH:
                            $check = HealthCheck::where('alert_rule_id', $alert->_id)->first();
                            if (! empty($check) && $check->state == HealthCheck::DOWN) {
                                if ($check->notify_at + (60 * $check->interval) < time()) {
                                    $check->notify_at = time();
                                    $check->save();
                                    SendNotifyService::CreateNotify(SendNotifyJob::HEALTH_CHECK, $check, $alert->_id);
                                }
                            }
                            break;
                        case Constants::PROMETHEUS:
                            $check = $alert->prometheusCheck;
                            if (! empty($check) && $check->state == PrometheusCheck::FIRE) {
                                if (($check->notify_at + (60 * $alert->interval)) < time()) {
                                    $check->notify_at = time();
                                    $check->save();
                                    SendNotifyService::CreateNotify(SendNotifyJob::PROMETHEUS_FIRE, $check, $alert->_id);
                                }
                            }
                            break;
                        case Constants::ELASTIC:
                            if ($status == AlertRule::CRITICAL) {
                                if ($alert->notify_at + (60 * $alert->interval) < time()) {
                                    $check = ElasticCheck::where('alert_rule_id', $alert->_id)->first();
                                    if (! empty($check)) {
                                        $alert->notify_at = time();
                                        $alert->save();
                                        SendNotifyService::CreateNotify(SendNotifyJob::ELASTIC_CHECK, $check, $alert->_id);
                                    }
                                }
                            }
                            break;
                    }
                }
            }
        }

    }
}
