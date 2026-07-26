<?php

namespace App\Providers;

use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Models\ElasticCheck;
use App\Models\GrafanaCheck;
use App\Models\HealthCheck;
use App\Models\PrometheusCheck;
use App\Models\VictoriaLogsCheck;
use App\Models\ZabbixCheck;
use App\Observers\Ha\HaAlertRuleObserver;
use App\Observers\Ha\HaCheckObserver;
use App\Services\Ha\AlertStateReplicator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Check documents whose runtime state is replicated through Raft.
     *
     * @var array<int, class-string<BaseModel>>
     */
    private const HA_REPLICATED_CHECKS = [
        PrometheusCheck::class,
        GrafanaCheck::class,
        ZabbixCheck::class,
        AlertInstance::class,
        ElasticCheck::class,
        VictoriaLogsCheck::class,
        HealthCheck::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared so that the per key publish memo spans a whole request or worker.
        $this->app->singleton(AlertStateReplicator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api-alert', function (Request $request) {
            return Limit::perMinute(60)->by($request->bearerToken());
        });

        $this->registerHaObservers();
    }

    /**
     * The observers are always registered; the replicator itself is what turns
     * into a no-op when HA is disabled or this node is a follower.
     */
    private function registerHaObservers(): void
    {
        AlertRule::observe(HaAlertRuleObserver::class);

        foreach (self::HA_REPLICATED_CHECKS as $check) {
            $check::observe(HaCheckObserver::class);
        }
    }
}
