<?php

namespace Database\Seeders;

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Enums\HealthAlertType;
use App\Models\AlertRule;
use App\Models\ApiAlertStatusHistory;
use App\Models\Auth\Role;
use App\Models\ElasticHistory;
use App\Models\GrafanaWebhookAlert;
use App\Models\HealthHistory;
use App\Models\PrometheusHistory;
use App\Models\SentryWebhookAlert;
use App\Models\Team;
use App\Models\User;
use App\Models\VictoriaLogsHistory;
use App\Models\ZabbixWebhookAlert;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        $member = $this->ensureMemberUser();
        $team = $this->ensureSeedTeam($owner, $member);
        $seedDataSourceId = '507f1f77bcf86cd799439099';

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
                $alertRule = AlertRule::create(
                    $this->buildAlertRuleAttributes($type, $index, $owner, $member, $team, $seedDataSourceId),
                );

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
        $this->command?->line('Member login: alert-status-member / 123456');
        $this->command?->line(sprintf('Team id: %s', $team->id));
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
     * Mirrors AlertingController::Store commonFields + per-type create payload.
     *
     * @return array<string, mixed>
     */
    private function buildAlertRuleAttributes(
        AlertRuleType $type,
        int $index,
        User $owner,
        User $member,
        Team $team,
        string $seedDataSourceId,
    ): array {
        $name = sprintf('seed-%s-%02d', $type->value, $index);

        $attributes = [
            'name' => $name,
            'type' => $type->value,
            'description' => 'AlertStatus API test seed data',
            'showAcknowledgeBtn' => true,
            'tags' => ['seed', 'alert-status', $type->value],
            'userId' => $owner->id,
            'endpointIds' => [],
            'userIds' => [$member->id],
            'teamIds' => [$team->id],
        ];

        return [
            ...$attributes,
            ...match ($type) {
                AlertRuleType::PROMETHEUS, AlertRuleType::GRAFANA => [
                    'queryType' => AlertRule::DYNAMIC_QUERY_TYPE,
                    'dataSourceIds' => [$seedDataSourceId],
                    'dataSourceAlertName' => $name,
                    'queryText' => '',
                    'queryObject' => null,
                    'extraField' => [
                        'environment' => 'seed',
                        'service' => $type->value,
                    ],
                ],
                AlertRuleType::SENTRY => [
                    'dataSourceIds' => [$seedDataSourceId],
                    'dataSourceAlertName' => $name,
                ],
                AlertRuleType::ZABBIX => [
                    'dataSourceIds' => [$seedDataSourceId],
                    'hosts' => ['zabbix-host-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
                    'actions' => ['action-1'],
                    'severities' => ['Warning', 'High'],
                ],
                AlertRuleType::API => [
                    'enableAutoResolve' => $index % 2 === 0,
                    'autoResolveMinutes' => $index % 2 === 0 ? 30 : 0,
                    'apiToken' => Str::random(60),
                ],
                AlertRuleType::ELASTIC => [
                    'dataSourceId' => $seedDataSourceId,
                    'dataviewName' => 'seed-dataview',
                    'dataviewTitle' => 'Seed Data View',
                    'queryString' => 'level:error AND service:'.$name,
                    'minutes' => 15,
                    'conditionType' => 'greater_than',
                    'countDocument' => 10,
                ],
                AlertRuleType::VICTORIA_LOGS => [
                    'dataSourceId' => $seedDataSourceId,
                    'queryString' => 'status:failed AND service:'.$name,
                    'minutes' => 10,
                    'conditionType' => 'greater_than',
                    'countDocument' => 5,
                ],
                AlertRuleType::HEALTH => [
                    'url' => 'https://health-seed.example.com/'.$name,
                    'checkType' => HealthAlertType::DATASOURCE->value,
                    'threshold' => 5,
                ],
                default => [],
            },
        ];
    }

    private function ensureMemberUser(): User
    {
        Role::firstOrCreate([
            'name' => Constants::ROLE_MEMBER->value,
            'guard_name' => 'api',
        ]);

        $member = User::firstOrCreate(
            ['username' => 'alert-status-member'],
            [
                'name' => 'Alert Status Member',
                'password' => Hash::make('123456'),
            ],
        );

        if (! $member->hasRole(Constants::ROLE_MEMBER->value)) {
            $member->assignRole(Constants::ROLE_MEMBER->value);
        }

        return $member->fresh();
    }

    private function ensureSeedTeam(User $owner, User $member): Team
    {
        return Team::firstOrCreate(
            ['name' => 'alert-status-seed-team'],
            [
                'ownerId' => $owner->id,
                'userIds' => [$owner->id, $member->id],
                'description' => 'Team for AlertStatus API seed data',
            ],
        );
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

    private function seedText(int $words = 6): string
    {
        $t = '';
        for ($i = 0; $i < $words; $i++) {
            $t .= $i.'word ';
        }

        return $t;
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
                    'description' => $this->seedText(),
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
                    'summary' => $this->seedText(),
                    'description' => $this->seedText(12),
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
                    'summary' => $this->seedText(),
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
            AlertRule::CRITICAL => ['Average', 'High', 'Disaster'][random_int(0, 2)],
            default => 'Information',
        };

        ZabbixWebhookAlert::create([
            'alertRuleId' => $alertRule->_id,
            'alertRuleName' => $alertRule->name,
            'dataSourceName' => 'zabbix-seed',
            'event_status' => $isProblem ? ZabbixWebhookAlert::PROBLEM : ZabbixWebhookAlert::RESOLVED,
            'event_severity' => $severity,
            'alert_subject' => $this->seedText(),
            'alert_message' => $this->seedText(10),
            'createdAt' => $at,
        ]);
    }

    private function seedSentryHistory(AlertRule $alertRule, Carbon $at, string $status): void
    {
        $action = match ($status) {
            AlertRule::WARNING => AlertRule::WARNING,
            AlertRule::CRITICAL => [AlertRule::CRITICAL, AlertRule::TRIGGERED][random_int(0, 1)],
            default => AlertRule::RESOlVED,
        };

        SentryWebhookAlert::create([
            'alertRuleId' => $alertRule->_id,
            'alertRuleName' => $alertRule->name,
            'dataSourceName' => 'sentry-seed',
            'action' => $action,
            'title' => $this->seedText(4),
            'message' => $this->seedText(),
            'description' => $this->seedText(10),
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
            'url' => $alertRule->url ?? 'https://health-seed.example.com/'.$alertRule->name,
            'state' => $isDown ? HealthHistory::DOWN : HealthHistory::UP,
            'counter' => random_int(1, 5),
            'threshold' => $alertRule->threshold ?? 3,
            'createdAt' => $at,
        ]);
    }
}
