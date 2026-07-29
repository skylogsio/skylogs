<?php

namespace App\Services\Ha;

use App\Models\ApiAlertHistory;
use App\Models\ApiAlertStatusHistory;
use App\Models\ElasticHistory;
use App\Models\GrafanaWebhookAlert;
use App\Models\HealthHistory;
use App\Models\MetabaseWebhookAlert;
use App\Models\Notify;
use App\Models\PrometheusHistory;
use App\Models\SentryWebhookAlert;
use App\Models\VictoriaLogsHistory;
use App\Models\ZabbixWebhookAlert;
use Illuminate\Database\Eloquent\Model;

/**
 * Alert timeline and notify documents that followers pull incrementally from the
 * leader. Kept out of the configuration snapshot so multi‑GB archives do not
 * ride every config version bump, and kept out of Raft so the KV log stays
 * limited to hot alert slots.
 *
 * StatusHistory stays node-local (RefreshStatusHistoryJob). Splunk has no
 * history model in this catalog.
 */
final class HaHistoryCatalog
{
    /**
     * The applier sets the primary key itself. updatedAt comes from the leader
     * so the follower's cursor and the leader's watermark stay aligned.
     *
     * @var array<int, string>
     */
    public const APPLIER_OWNED_FIELDS = ['_id', 'id'];

    /**
     * Keyed by the name the page uses on the wire.
     *
     * @return array<string, class-string<Model>>
     */
    public static function collections(): array
    {
        return [
            'prometheusHistories' => PrometheusHistory::class,
            'grafanaWebhookAlerts' => GrafanaWebhookAlert::class,
            'zabbixWebhookAlerts' => ZabbixWebhookAlert::class,
            'elasticHistories' => ElasticHistory::class,
            'victoriaLogsHistories' => VictoriaLogsHistory::class,
            'healthHistories' => HealthHistory::class,
            'apiAlertHistories' => ApiAlertHistory::class,
            'apiAlertStatusHistories' => ApiAlertStatusHistory::class,
            'sentryWebhookAlerts' => SentryWebhookAlert::class,
            'metabaseWebhookAlerts' => MetabaseWebhookAlert::class,
            'notifies' => Notify::class,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function aliases(): array
    {
        return array_keys(self::collections());
    }

    /**
     * @return class-string<Model>|null
     */
    public static function model(string $alias): ?string
    {
        return self::collections()[$alias] ?? null;
    }

    public static function aliasFor(Model $model): ?string
    {
        foreach (self::collections() as $alias => $class) {
            if ($model instanceof $class) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function protectedFields(): array
    {
        return self::APPLIER_OWNED_FIELDS;
    }
}
