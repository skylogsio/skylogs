<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;

/**
 * Seeds alert rules and per-type status history for manual AlertStatus API testing.
 *
 * Run: php artisan db:seed --class=AlertStatusTestDataSeeder
 */
class AlertStatusTestDataSeeder extends Seeder
{
    private const RULES_PER_TYPE = 8;

    private const MIN_EVENTS_PER_RULE = 25;

    private const MAX_EVENTS_PER_RULE = 120;

    private const HISTORY_DAYS = 30;

    /** @var list<AlertRuleType> */
    private const SUPPORTED_TYPES = [
        AlertRuleType::API,
        AlertRuleType::PROMETHEUS,
        AlertRuleType::GRAFANA,
        AlertRuleType::ZABBIX,
        AlertRuleType::SENTRY,
        AlertRuleType::ELASTIC,
        AlertRuleType::VICTORIA_LOGS,
        AlertRuleType::HEALTH,
    ];

    public function run(): void
    {
        $this->call(RolesAdminSeeder::class);

        $owner = User::query()->where('username', 'admin')->firstOrFail();
        $windowStart = Carbon::now()->subDays(self::HISTORY_DAYS)->startOfHour();
        $windowEnd = Carbon::now();

        $this->command?->info(sprintf(
            'Seeding AlertStatus test data (%d rules per type, %d–%d events each, %d-day window)...',
            self::RULES_PER_TYPE,
            self::MIN_EVENTS_PER_RULE,
            self::MAX_EVENTS_PER_RULE,
            self::HISTORY_DAYS,
        ));

        $alertRules = collect();

        foreach (self::SUPPORTED_TYPES as $type) {
            for ($index = 1; $index <= self::RULES_PER_TYPE; $index++) {
                $alertRule = AlertRule::create([
                    'name' => sprintf('seed-%s-%02d', $type->value, $index),
                    'type' => $type->value,
                    'userId' => $owner->id,
                    'description' => 'AlertStatus API test seed data',
                    'state' => AlertRule::UNKNOWN,
                ]);

                $eventCount = random_int(self::MIN_EVENTS_PER_RULE, self::MAX_EVENTS_PER_RULE);
                $timeline = $this->buildTimeline($windowStart, $windowEnd, $eventCount, $type);

                $this->seedHistory($alertRule, $type, $timeline);

                $alertRules->push($alertRule);
            }
        }

        $sampleIds = $alertRules->take(5)->pluck('id')->all();

        $this->command?->newLine();
        $this->command?->info('AlertStatus test data seeded successfully.');
        $this->command?->line('Login: admin / 123456');
        $this->command?->line(sprintf('Window: fromTime=%d toTime=%d', $windowStart->timestamp, $windowEnd->timestamp));
        $this->command?->line('Sample alertRuleIds: '.implode(', ', $sampleIds));
        $this->command?->line('Endpoint: GET /api/v1/alert-rule/status?'.http_build_query([
            'alertRuleIds' => $sampleIds,
            'fromTime' => $windowStart->timestamp,
            'toTime' => $windowEnd->timestamp,
            'bucketCount' => 100,
        ]));
    }

    /**
     * @return list<array{at: Carbon, status: string}>
     */
    private function buildTimeline(Carbon $from, Carbon $to, int $eventCount, AlertRuleType $type): array
    {
        $durationSeconds = max(1, $to->diffInSeconds($from));
        $points = [];
        $maxJitter = min(300, (int) floor($durationSeconds / max(2, $eventCount * 2)));

        for ($index = 0; $index < $eventCount; $index++) {
            $offset = $eventCount === 1
                ? 0
                : (int) round(($durationSeconds * $index) / ($eventCount - 1));
            $jitter = $maxJitter > 0 ? random_int(-$maxJitter, $maxJitter) : 0;
            $seconds = min($durationSeconds, max(0, $offset + $jitter));

            $points[] = [
                'at' => $from->copy()->addSeconds($seconds),
                'status' => $this->randomStatus($type),
            ];
        }

        usort($points, fn (array $left, array $right) => $left['at']->timestamp <=> $right['at']->timestamp);

        return $points;
    }

    private function randomStatus(AlertRuleType $type): string
    {
        $roll = random_int(1, 100);

        return match ($type) {
            AlertRuleType::PROMETHEUS, AlertRuleType::GRAFANA, AlertRuleType::ZABBIX, AlertRuleType::SENTRY => match (true) {
                $roll <= 45 => AlertRule::RESOlVED,
                $roll <= 65 => AlertRule::WARNING,
                default => AlertRule::CRITICAL,
            },
            default => $roll <= 55 ? AlertRule::RESOlVED : AlertRule::CRITICAL,
        };
    }

    /**
     * @param  list<array{at: Carbon, status: string}>  $timeline
     */
    private function seedHistory(AlertRule $alertRule, AlertRuleType $type, array $timeline): void
    {
        foreach ($timeline as $point) {
            match ($type) {
                AlertRuleType::API => $this->seedApiHistory($alertRule, $point['at'], $point['status']),
                AlertRuleType::PROMETHEUS => $this->seedPrometheusHistory($alertRule, $point['at'], $point['status']),
                AlertRuleType::GRAFANA => $this->seedGrafanaHistory($alertRule, $point['at'], $point['status']),
                AlertRuleType::ZABBIX => $this->seedZabbixHistory($alertRule, $point['at'], $point['status']),
                AlertRuleType::SENTRY => $this->seedSentryHistory($alertRule, $point['at'], $point['status']),
                AlertRuleType::ELASTIC => $this->seedElasticHistory($alertRule, $point['at'], $point['status']),
                AlertRuleType::VICTORIA_LOGS => $this->seedVictoriaLogsHistory($alertRule, $point['at'], $point['status']),
                AlertRuleType::HEALTH => $this->seedHealthHistory($alertRule, $point['at'], $point['status']),
                default => null,
            };
        }
    }

    private function seedApiHistory(AlertRule $alertRule, Carbon $at, string $status): void
    {
        $isFire = $status === AlertRule::CRITICAL;

        ApiAlertStatusHistory::create([
            'alertRuleId' => $alertRule->_id,
            'state' => $isFire ? ApiAlertStatusHistory::FIRE : ApiAlertStatusHistory::RESOLVED,
            'countAlerts' => $isFire ? random_int(1, 5) : 0,
            'firedInstances' => $isFire ? [
                [
                    'instance' => 'host-'.random_int(1, 20),
                    'description' => fake()->sentence(),
                ],
            ] : [],
            'createdAt' => $at,
        ]);
    }

    private function seedPrometheusHistory(AlertRule $alertRule, Carbon $at, string $status): void
    {
        $isFire = $status !== AlertRule::RESOlVED;
        $severity = match ($status) {
            AlertRule::WARNING => 'warning',
            AlertRule::CRITICAL => 'critical',
            default => 'info',
        };

        PrometheusHistory::create([
            'alertRuleId' => $alertRule->_id,
            'state' => $isFire ? PrometheusHistory::FIRE : PrometheusHistory::RESOLVED,
            'countFire' => $isFire ? random_int(1, 4) : 0,
            'alerts' => $isFire ? [[
                'labels' => [
                    'severity' => $severity,
                    'alertname' => $alertRule->name,
                    'instance' => 'node-'.random_int(1, 10),
                ],
                'annotations' => [
                    'summary' => fake()->sentence(),
                    'description' => fake()->paragraph(),
                ],
            ]] : [],
            'createdAt' => $at,
        ]);
    }

    private function seedGrafanaHistory(AlertRule $alertRule, Carbon $at, string $status): void
    {
        $isFiring = $status !== AlertRule::RESOlVED;
        $severity = match ($status) {
            AlertRule::WARNING => 'warning',
            AlertRule::CRITICAL => 'critical',
            default => 'info',
        };

        GrafanaWebhookAlert::create([
            'alertRuleId' => $alertRule->_id,
            'status' => $isFiring ? GrafanaWebhookAlert::FIRING : GrafanaWebhookAlert::RESOLVED,
            'alerts' => $isFiring ? [[
                'labels' => [
                    'severity' => $severity,
                    'alertname' => $alertRule->name,
                ],
                'annotations' => [
                    'summary' => fake()->sentence(),
                ],
            ]] : [],
            'createdAt' => $at,
        ]);
    }

    private function seedZabbixHistory(AlertRule $alertRule, Carbon $at, string $status): void
    {
        $isProblem = $status !== AlertRule::RESOlVED;
        $severity = match ($status) {
            AlertRule::WARNING => 'Warning',
            AlertRule::CRITICAL => fake()->randomElement(['Average', 'High', 'Disaster']),
            default => 'Information',
        };

        ZabbixWebhookAlert::create([
            'alertRuleId' => $alertRule->_id,
            'alertRuleName' => $alertRule->name,
            'dataSourceName' => 'zabbix-seed',
            'event_status' => $isProblem ? ZabbixWebhookAlert::PROBLEM : ZabbixWebhookAlert::RESOLVED,
            'event_severity' => $severity,
            'alert_subject' => fake()->sentence(),
            'alert_message' => fake()->paragraph(),
            'createdAt' => $at,
        ]);
    }

    private function seedSentryHistory(AlertRule $alertRule, Carbon $at, string $status): void
    {
        $action = match ($status) {
            AlertRule::WARNING => AlertRule::WARNING,
            AlertRule::CRITICAL => fake()->randomElement([AlertRule::CRITICAL, AlertRule::TRIGGERED]),
            default => AlertRule::RESOlVED,
        };

        SentryWebhookAlert::create([
            'alertRuleId' => $alertRule->_id,
            'alertRuleName' => $alertRule->name,
            'dataSourceName' => 'sentry-seed',
            'action' => $action,
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'createdAt' => $at,
        ]);
    }

    private function seedElasticHistory(AlertRule $alertRule, Carbon $at, string $status): void
    {
        $isFire = $status === AlertRule::CRITICAL;
        $threshold = random_int(5, 20);

        ElasticHistory::create([
            'alertRuleId' => $alertRule->_id,
            'state' => $isFire ? ElasticHistory::FIRE : ElasticHistory::RESOLVED,
            'queryString' => 'level:error AND service:'.$alertRule->name,
            'countDocument' => $threshold,
            'currentCountDocument' => $isFire ? random_int($threshold, $threshold + 15) : 0,
            'minutes' => random_int(5, 30),
            'dataviewTitle' => 'logs-seed',
            'createdAt' => $at,
        ]);
    }

    private function seedVictoriaLogsHistory(AlertRule $alertRule, Carbon $at, string $status): void
    {
        $isFire = $status === AlertRule::CRITICAL;
        $threshold = random_int(3, 15);

        VictoriaLogsHistory::create([
            'alertRuleId' => $alertRule->_id,
            'state' => $isFire ? VictoriaLogsHistory::FIRE : VictoriaLogsHistory::RESOLVED,
            'queryString' => 'status:failed',
            'countDocument' => $threshold,
            'currentCountDocument' => $isFire ? random_int($threshold, $threshold + 10) : 0,
            'minutes' => random_int(5, 20),
            'createdAt' => $at,
        ]);
    }

    private function seedHealthHistory(AlertRule $alertRule, Carbon $at, string $status): void
    {
        $isDown = $status === AlertRule::CRITICAL;

        HealthHistory::create([
            'alertRuleId' => $alertRule->_id,
            'alertRuleName' => $alertRule->name,
            'url' => 'https://example.com/'.fake()->slug(),
            'state' => $isDown ? HealthHistory::DOWN : HealthHistory::UP,
            'counter' => random_int(1, 5),
            'threshold' => 3,
            'createdAt' => $at,
        ]);
    }
}
