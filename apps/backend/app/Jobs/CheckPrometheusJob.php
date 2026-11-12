<?php

namespace App\Jobs;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Queue\Middleware\EnsureLeader;
use App\Services\PrometheusInstanceService;
use App\Services\PrometheusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckPrometheusJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Covers the pooled HTTP fetch and evaluating every Prometheus rule, and
     * stays below Redis retry_after so the job is never handed out twice.
     */
    public int $timeout = 60;

    /**
     * Matches the timeout so a long run cannot lose its lock and overlap.
     */
    public int $uniqueFor = 60;

    public function uniqueId(): string
    {
        return 'check-prometheus';
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['datasource-check', 'prometheus'];
    }

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

        $alertRules = AlertRule::where('type', AlertRuleType::PROMETHEUS)->get();
        $sync = app(PrometheusInstanceService::class)->getTriggeredForAlertSync();

        $prometheusService = app(PrometheusService::class);
        $fireAlertsByRule = $prometheusService->CheckPrometheusFiredAlerts($sync['alerts'], $alertRules);
        $prometheusService->CheckAlerts($alertRules, $fireAlertsByRule, $sync['failedDataSourceIds']);

    }
}
