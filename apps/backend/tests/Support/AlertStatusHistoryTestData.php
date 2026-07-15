<?php

namespace Tests\Support;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\ApiAlertStatusHistory;
use App\Models\ElasticHistory;
use App\Models\GrafanaWebhookAlert;
use App\Models\HealthHistory;
use App\Models\PrometheusHistory;
use App\Models\SentryWebhookAlert;
use App\Models\User;
use App\Models\VictoriaLogsHistory;
use App\Models\ZabbixWebhookAlert;
use Carbon\Carbon;

final class AlertStatusHistoryTestData
{
    public const WINDOW_FROM = 1_782_000_000;

    public const WINDOW_TO = 1_783_000_000;

    public const FIRE_AT = 1_782_605_000;

    public const RESOLVE_AT = 1_782_655_000;

    public const SLOT_COUNT = 100;

    public static function bucketSeconds(
        int $from = self::WINDOW_FROM,
        int $to = self::WINDOW_TO,
        int $slotCount = self::SLOT_COUNT,
    ): int {
        return max(1, (int) ceil(max(1, $to - $from) / max(1, $slotCount)));
    }

    /**
     * @return list<array{fromTime: int, toTime: int}>
     */
    public static function bucketsOverlappingInterval(
        int $intervalStart,
        int $intervalEnd,
        int $from = self::WINDOW_FROM,
        int $to = self::WINDOW_TO,
        int $slotCount = self::SLOT_COUNT,
    ): array {
        $bucketSeconds = self::bucketSeconds($from, $to, $slotCount);
        $buckets = [];

        for ($i = 0; $i < $slotCount; $i++) {
            $bucketFrom = $from + ($i * $bucketSeconds);
            $bucketTo = ($i === $slotCount - 1) ? $to : $from + (($i + 1) * $bucketSeconds);

            if ($bucketFrom >= $bucketTo) {
                break;
            }

            if ($bucketFrom < $intervalEnd && $bucketTo > $intervalStart) {
                $buckets[] = [
                    'fromTime' => $bucketFrom,
                    'toTime' => $bucketTo,
                ];
            }
        }

        return $buckets;
    }

    public static function createAlertRule(User $owner, AlertRuleType|string $type, string $name = 'Status Timeline Alert'): AlertRule
    {
        $typeValue = $type instanceof AlertRuleType ? $type->value : $type;

        return AlertRule::create([
            'name' => $name,
            'type' => $typeValue,
            'userId' => $owner->id,
        ]);
    }

    public static function seedResolvedBaseline(AlertRule $alertRule, int $timestamp): void
    {
        self::seedStatusEvent($alertRule, $timestamp, resolved: true);
    }

    public static function seedFireEvent(AlertRule $alertRule, int $timestamp): void
    {
        self::seedStatusEvent($alertRule, $timestamp, resolved: false);
    }

    public static function seedResolveEvent(AlertRule $alertRule, int $timestamp): void
    {
        self::seedStatusEvent($alertRule, $timestamp, resolved: true);
    }

    public static function seedStatusEvent(AlertRule $alertRule, int $timestamp, bool $resolved): void
    {
        $at = Carbon::createFromTimestamp($timestamp);
        $type = $alertRule->type;

        match ($type) {
            AlertRuleType::API => ApiAlertStatusHistory::create([
                'alertRuleId' => $alertRule->_id,
                'state' => $resolved ? ApiAlertStatusHistory::RESOLVED : ApiAlertStatusHistory::FIRE,
                'countAlerts' => $resolved ? 0 : 1,
                'firedInstances' => $resolved ? [] : [
                    ['instance' => 'host-1', 'description' => 'connection refused'],
                ],
                'createdAt' => $at,
            ]),
            AlertRuleType::ELASTIC => ElasticHistory::create([
                'alertRuleId' => $alertRule->_id,
                'state' => $resolved ? ElasticHistory::RESOLVED : ElasticHistory::FIRE,
                'queryString' => 'level:error',
                'countDocument' => 10,
                'currentCountDocument' => $resolved ? 0 : 15,
                'minutes' => 5,
                'dataviewTitle' => 'logs-test',
                'createdAt' => $at,
            ]),
            AlertRuleType::VICTORIA_LOGS => VictoriaLogsHistory::create([
                'alertRuleId' => $alertRule->_id,
                'state' => $resolved ? VictoriaLogsHistory::RESOLVED : VictoriaLogsHistory::FIRE,
                'queryString' => 'status:failed',
                'countDocument' => 5,
                'currentCountDocument' => $resolved ? 0 : 8,
                'minutes' => 5,
                'createdAt' => $at,
            ]),
            AlertRuleType::GRAFANA, AlertRuleType::PMM => GrafanaWebhookAlert::create([
                'alertRuleId' => $alertRule->_id,
                'status' => $resolved ? GrafanaWebhookAlert::RESOLVED : GrafanaWebhookAlert::FIRING,
                'alerts' => $resolved ? [] : [[
                    'labels' => [
                        'severity' => 'critical',
                        'alertname' => $alertRule->name,
                    ],
                    'annotations' => [
                        'summary' => 'Service is unavailable',
                    ],
                ]],
                'createdAt' => $at,
            ]),
            AlertRuleType::PROMETHEUS => PrometheusHistory::create([
                'alertRuleId' => $alertRule->_id,
                'state' => $resolved ? PrometheusHistory::RESOLVED : PrometheusHistory::FIRE,
                'countFire' => $resolved ? 0 : 1,
                'alerts' => $resolved ? [] : [[
                    'labels' => [
                        'severity' => 'critical',
                        'alertname' => $alertRule->name,
                    ],
                    'annotations' => [
                        'summary' => 'Database is down',
                    ],
                ]],
                'createdAt' => $at,
            ]),
            AlertRuleType::ZABBIX => ZabbixWebhookAlert::create([
                'alertRuleId' => $alertRule->_id,
                'alertRuleName' => $alertRule->name,
                'dataSourceName' => 'zabbix-test',
                'event_status' => $resolved ? ZabbixWebhookAlert::RESOLVED : ZabbixWebhookAlert::PROBLEM,
                'event_severity' => $resolved ? 'Information' : 'High',
                'alert_subject' => 'CPU usage high',
                'alert_message' => 'CPU exceeded threshold',
                'createdAt' => $at,
            ]),
            AlertRuleType::SENTRY => SentryWebhookAlert::create([
                'alertRuleId' => $alertRule->_id,
                'alertRuleName' => $alertRule->name,
                'dataSourceName' => 'sentry-test',
                'action' => $resolved ? AlertRule::RESOlVED : AlertRule::CRITICAL,
                'title' => 'Unhandled exception',
                'message' => 'Null pointer in worker',
                'description' => 'Stack trace omitted',
                'createdAt' => $at,
            ]),
            AlertRuleType::HEALTH => HealthHistory::create([
                'alertRuleId' => $alertRule->_id,
                'alertRuleName' => $alertRule->name,
                'url' => 'https://health-test.example.com',
                'state' => $resolved ? HealthHistory::UP : HealthHistory::DOWN,
                'counter' => 1,
                'threshold' => 3,
                'createdAt' => $at,
            ]),
            default => null,
        };
    }

    public static function deleteAlertAndHistory(AlertRule $alertRule): void
    {
        $alertRuleId = $alertRule->_id;

        ApiAlertStatusHistory::query()->where('alertRuleId', $alertRuleId)->delete();
        ElasticHistory::query()->where('alertRuleId', $alertRuleId)->delete();
        VictoriaLogsHistory::query()->where('alertRuleId', $alertRuleId)->delete();
        GrafanaWebhookAlert::query()->where('alertRuleId', $alertRuleId)->delete();
        PrometheusHistory::query()->where('alertRuleId', $alertRuleId)->delete();
        ZabbixWebhookAlert::query()->where('alertRuleId', $alertRuleId)->delete();
        SentryWebhookAlert::query()->where('alertRuleId', $alertRuleId)->delete();
        HealthHistory::query()->where('alertRuleId', $alertRuleId)->delete();
        AlertRule::query()->where('_id', $alertRuleId)->delete();
    }

    /**
     * @param  array<int, array{status: string, fromTime: int, toTime: int, count: int}>  $segments
     */
    public static function segmentStatusAt(array $segments, int $timestamp): ?string
    {
        foreach ($segments as $segment) {
            if ($timestamp >= $segment['fromTime'] && $timestamp < $segment['toTime']) {
                return $segment['status'];
            }
        }

        return null;
    }
}
