<?php

namespace App\Jobs;

use App\Enums\AlertRuleType;
use App\Queue\Middleware\EnsureLeader;
use App\Services\AlertRuleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AddChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureLeader];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $alertRules = app(AlertRuleService::class)->getAlerts([
            AlertRuleType::ELASTIC,
            AlertRuleType::VICTORIA_LOGS,
            AlertRuleType::HEALTH,
        ]);

        foreach ($alertRules as $alert) {
            match ($alert->type) {
                AlertRuleType::ELASTIC => CheckElasticJob::dispatch($alert),
                AlertRuleType::VICTORIA_LOGS => CheckVictoriaLogsJob::dispatch($alert),
                AlertRuleType::HEALTH => CheckHealthJob::dispatch($alert),
            };
        }

    }
}
