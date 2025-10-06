<?php

namespace App\Jobs;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Services\PrometheusInstanceService;
use App\Services\PrometheusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckPrometheusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->onQueue('httpRequests');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $alertRules = AlertRule::where('type', AlertRuleType::PROMETHEUS)->get();
        $alerts = app(PrometheusInstanceService::class)->getTriggered();

        $prometheusService = app(PrometheusService::class);
        $fireAlertsByRule = $prometheusService->CheckPrometheusFiredAlerts($alerts, $alertRules);
        $prometheusService->CheckAlerts($alertRules, $fireAlertsByRule);


    }

}
