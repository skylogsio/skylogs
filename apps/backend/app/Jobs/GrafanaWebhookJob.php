<?php

namespace App\Jobs;

use App\Helpers\Constants;
use App\Models\AlertRule;
use App\Models\GrafanaWebhookAlert;
use App\Services\SendNotifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GrafanaWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $alerts;

    public $instance;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($instance, $alerts)
    {
        $this->alerts = $alerts;
        $this->instance = $instance;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $alertRules = AlertRule::where('type', Constants::GRAFANA)->get();
        $alerts = $this->alerts;

        foreach ($alerts as $alert) {
            foreach ($alertRules as $alertRule) {
                $isMatch = true;
                $matchLabels = [];
                $matchAnnotations = [];
                if ($this->instance == $alertRule['instance'] && $alert['labels']['alertname'] == $alertRule['grafana_alertname']) {

                    foreach ($alertRule->extraField as $key => $value) {
                        if ((! empty($alert['labels'][$key]) && $alert['labels'][$key] == $value)) {
                            $matchLabels[$key] = $value;
                        } elseif ((! empty($alert['annotations'][$key]) && $alert['annotations'][$key] == $value)) {
                            $matchAnnotations[$key] = $value;
                        } else {
                            $isMatch = false;
                            break;
                        }
                    }

                } else {
                    $isMatch = false;
                }

                if ($isMatch) {
                    $model = new GrafanaWebhookAlert;
                    $alert['grafana_alertname'] = $alert['labels']['alertname'];
                    $alert['alertname'] = $alertRule->alertname;
                    $alert['instance'] = $this->instance;
                    $model->alertRuleId = $alertRule->_id;

                    if ($model->customSave($alert)) {
                        SendNotifyService::CreateNotify(SendNotifyJob::GRAFANA_WEBHOOK, $model, $model->alertRuleId);
                    }

                }

            }
        }

    }
}
