<?php

namespace App\Jobs;

use App\Models\HealthCheck;
use App\Models\Service;
use App\Models\ServiceCheck;
use App\Services\ServiceCheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckServiceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $alert;

    public function __construct($alert)
    {
        $this->onQueue('httpRequests');
        $this->alert = $alert;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //        echo "TEST";
        $check = ServiceCheck::firstOrCreate(
            [
                'alertRuleId' => $this->alert->_id,
            ],
            [
                'service_id' => $this->alert->service_id,
                'url' => $this->alert->service->url,
                'threshold_down' => $this->alert->threshold_down,
                'threshold_up' => $this->alert->threshold_up,
                'basic_auth_username' => $this->alert->service->basic_auth_username,
                'basic_auth_password' => $this->alert->service->basic_auth_password,
                'api_token' => $this->alert->service->api_token,
                'state' => HealthCheck::UP,
                'counter' => 0,
            ]
        );

        switch ($check->service->type) {
            case Service::TYPE_SENTRY:
                ServiceCheckService::CheckSentry($check);
                break;
            case Service::TYPE_ZABBIX:
                ServiceCheckService::CheckZabbix($check);
                break;
            case Service::TYPE_ELASTIC:
                //                ServiceCheckService::CheckElastic($check);
                break;
            case Service::TYPE_PMM:
                //                ServiceCheckService::CheckPmm($check);
                break;
            case Service::TYPE_PROMETHEUS:
                //                ServiceCheckService::CheckPrometheus($check);
                break;
            case Service::TYPE_GRAFANA:
                //                ServiceCheckService::CheckGrafana($check);
                break;
        }

    }

    public function uniqueId()
    {
        return $this->alert->_id;
    }
}
