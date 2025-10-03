<?php

namespace App\Jobs;

use App\Enums\AlertRuleType;
use App\Models\AlertInstance;
use App\Services\AlertRuleService;
use App\Services\ApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoResolveApiAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $alerts = app(AlertRuleService::class)->getAlerts(AlertRuleType::API);
        $now = time();
        if ($alerts->isNotEmpty()) {
            foreach ($alerts as $alert) {
                if (!$alert['enableAutoResolve']) continue;
                $minutes = $alert->autoResolveMinutes;
                $instances = $alert->apiInstances();
                if (!empty($instances) && $instances->isNotEmpty()) {
                    $fireInstances = $instances->where("state", AlertInstance::FIRE);
                    foreach ($fireInstances as $instance) {
                        $lastUpdate = $instance->updatedAt->getTimeStamp();
                        if (($lastUpdate + ($minutes * 60)) < $now) {
                            $post = [
                                "apiToken" => $alert->apiToken,
                                "instance" => $instance->instance,
                                "description" => "Alert Resolved Automatically"
                            ];
                            app(ApiService::class)->resolveAlert($post);
                        }
                    }
                }

            }

        }

    }

}
