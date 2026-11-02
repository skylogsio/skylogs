<?php

namespace App\Providers;

use App\Interfaces\NotificationChannel;
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
use App\Observers\Ha\HaConfigObserver;
use App\Services\Ha\AlertStateReplicator;
use App\Services\Ha\HaConfigCatalog;
use App\Services\Notification\ChannelRegistry;
use App\Services\Notification\Channels\BaleChannel;
use App\Services\Notification\Channels\CallChannel;
use App\Services\Notification\Channels\DiscordChannel;
use App\Services\Notification\Channels\EmailChannel;
use App\Services\Notification\Channels\MatterMostChannel;
use App\Services\Notification\Channels\SmsChannel;
use App\Services\Notification\Channels\TeamsChannel;
use App\Services\Notification\Channels\TelegramChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
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
     * One class per endpoint type. Adding a channel means adding an
     * EndpointType case and its class here.
     *
     * @var array<int, class-string<NotificationChannel>>
     */
    private const NOTIFICATION_CHANNELS = [
        TelegramChannel::class,
        BaleChannel::class,
        SmsChannel::class,
        CallChannel::class,
        EmailChannel::class,
        TeamsChannel::class,
        DiscordChannel::class,
        MatterMostChannel::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared so that the per key publish memo spans a whole request or worker.
        $this->app->singleton(AlertStateReplicator::class);

        $this->app->tag(self::NOTIFICATION_CHANNELS, 'notification.channels');
        $this->app->singleton(
            ChannelRegistry::class,
            fn (Application $app): ChannelRegistry => new ChannelRegistry($app->tagged('notification.channels')),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api-alert', function (Request $request) {
            return Limit::perMinute(100)->by($request->bearerToken());
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

        /*
         | Every replicated collection, so that a new writer anywhere in the
         | application cannot forget to invalidate a follower's snapshot.
         */
        foreach (HaConfigCatalog::models() as $model) {
            $model::observe(HaConfigObserver::class);
        }
    }
}
