<?php

namespace App\Jobs;

use App\Enums\AlertRuleType;
use App\Queue\Middleware\EnsureLeader;
use App\Services\AlertRuleService;
use App\Services\DataSource\DataSourceAvailability;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AddChecksJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 15;

    public int $uniqueFor = 15;

    public function uniqueId(): string
    {
        return 'add-log-checks';
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['datasource-check', 'add-log-checks'];
    }

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

        $availability = app(DataSourceAvailability::class);
        $alertRules = app(AlertRuleService::class)->getAlerts([
            AlertRuleType::ELASTIC,
            AlertRuleType::VICTORIA_LOGS,
        ]);

        foreach ($alertRules as $alert) {
            if ($availability->isCoolingDown((string) ($alert->dataSourceId ?? ''))) {
                continue;
            }

            match ($alert->type) {
                AlertRuleType::ELASTIC => CheckElasticJob::dispatch($alert),
                AlertRuleType::VICTORIA_LOGS => CheckVictoriaLogsJob::dispatch($alert),
            };
        }

    }
}
