<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Http\Middleware\HaNodeAuth;
use App\Jobs\SendNotifyJob;
use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use App\Models\ApiAlertStatusHistory;
use App\Models\ElasticCheck;
use App\Models\ElasticHistory;
use App\Models\GrafanaCheck;
use App\Models\GrafanaWebhookAlert;
use App\Models\HaStateVersion;
use App\Models\HealthCheck;
use App\Models\HealthHistory;
use App\Models\Notify;
use App\Models\PrometheusCheck;
use App\Models\PrometheusHistory;
use App\Models\ZabbixCheck;
use App\Models\ZabbixWebhookAlert;
use App\Services\AlertRuleService;
use App\Services\Ha\AlertStateKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Support\TeamTestData;

const HA_SECRET = 'test-ha-node-secret';

function haApply(string $key, array|string|null $value, ?string $secret = HA_SECRET): TestResponse
{
    $headers = $secret === null ? [] : [HaNodeAuth::SECRET_HEADER => $secret];

    return test()->postJson(route('ha.apply'), ['key' => $key, 'value' => $value], $headers);
}

/**
 * The shape the leader's projectors publish, with only the per type parts left
 * to the caller.
 */
function haValue(AlertRule $alertRule, string $type, string $instanceId, string $state, int $version, array $overrides = []): array
{
    return [
        'key' => 'alert:'.$alertRule->_id.':'.$type.':'.$instanceId,
        'version' => $version,
        'nodeId' => 'node-1',
        'timestamp' => 1785000000,
        'alertRuleId' => (string) $alertRule->_id,
        'alertRuleName' => $alertRule->name,
        'type' => $type,
        'instance' => [],
        'state' => $state,
        'firedAt' => $state === AlertRule::RESOlVED ? null : 1785000000,
        'resolvedAt' => $state === AlertRule::RESOlVED ? 1785000000 : null,
        'rule' => [
            'state' => $state,
            'fireCount' => $state === AlertRule::RESOlVED ? 0 : 1,
            'notifyAt' => 1785000000,
            'acknowledgedBy' => null,
        ],
        'extra' => [],
        ...$overrides,
    ];
}

function haPrometheusEntry(string $instance, int $status = PrometheusCheck::FIRE): array
{
    return [
        'dataSourceId' => 'ds-1',
        'dataSourceName' => 'Prom A',
        'alertRuleName' => 'HA Apply Alert',
        'labels' => ['alertname' => 'NodeDown', 'instance' => $instance, 'severity' => 'critical'],
        'annotations' => ['summary' => 'node is down'],
        'skylogsStatus' => $status,
    ];
}

function haAlertRuleOfType(AlertRuleType $type, array $attributes = []): AlertRule
{
    $alertRule = AlertRule::create([
        'name' => 'HA Apply Alert',
        'type' => $type->value,
        'userId' => test()->owner->id,
        ...$attributes,
    ]);

    $created = test()->createdAlertRules;
    $created[] = $alertRule;
    test()->createdAlertRules = $created;

    return $alertRule;
}

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'ha.enabled' => true,
        'ha.node_id' => 'node-2',
        'ha.node_secret' => HA_SECRET,
        'ha.allowed_cidrs' => [],
    ]);

    $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
    $this->createdAlertRules = [];

    Queue::fake();
});

afterEach(function () {
    Carbon::setTestNow();

    foreach ($this->createdAlertRules as $alertRule) {
        $alertRuleId = $alertRule->_id;

        PrometheusCheck::query()->where('alertRuleId', $alertRuleId)->delete();
        PrometheusHistory::query()->where('alertRuleId', $alertRuleId)->delete();
        GrafanaCheck::query()->where('alertRuleId', $alertRuleId)->delete();
        GrafanaWebhookAlert::query()->where('alertRuleId', $alertRuleId)->delete();
        ZabbixCheck::query()->where('alertRuleId', $alertRuleId)->delete();
        ZabbixWebhookAlert::query()->where('alertRuleId', $alertRuleId)->delete();
        AlertInstance::query()->where('alertRuleId', $alertRuleId)->delete();
        ApiAlertHistory::query()->where('alertRuleId', $alertRuleId)->delete();
        ApiAlertStatusHistory::query()->where('alertRuleId', $alertRuleId)->delete();
        ElasticCheck::query()->where('alertRuleId', $alertRuleId)->delete();
        ElasticHistory::query()->where('alertRuleId', $alertRuleId)->delete();
        HealthCheck::query()->where('alertRuleId', $alertRuleId)->delete();
        HealthHistory::query()->where('alertRuleId', $alertRuleId)->delete();
        Notify::query()->where('alertRuleId', $alertRuleId)->delete();
        HaStateVersion::query()->where('key', 'like', AlertStateKey::prefixFor((string) $alertRuleId).'%')->delete();
        AlertRule::query()->where('_id', $alertRuleId)->delete();
    }

    TeamTestData::deleteUser($this->owner);
});

describe('POST /api/ha/apply authentication', function () {
    it('rejects a request with no secret', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::PROMETHEUS);
        $key = 'alert:'.$alertRule->_id.':prometheus:'.AlertStateKey::prometheusInstanceId([]);

        haApply($key, haValue($alertRule, 'prometheus', 'x', AlertRule::CRITICAL, 1), secret: null)
            ->assertUnauthorized();
    });

    it('rejects a request with the wrong secret', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::PROMETHEUS);

        haApply('alert:'.$alertRule->_id.':prometheus:x', null, secret: 'guessed')
            ->assertUnauthorized();
    });

    it('rejects a malformed key before touching anything', function () {
        haApply('not-a-key', null)->assertStatus(422);
    });
});

/*
 | The sidecar notifies with the value exactly as it stored it: the raw JSON
 | text of the slot, not an object.
 */
describe('POST /api/ha/apply sidecar payload', function () {
    it('decodes a value the sidecar sent as json text', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::PROMETHEUS);
        $entry = haPrometheusEntry('10.0.0.4:9100');
        $instanceId = AlertStateKey::prometheusInstanceId($entry['labels']);
        $key = 'alert:'.$alertRule->_id.':prometheus:'.$instanceId;

        $value = haValue($alertRule, 'prometheus', $instanceId, AlertRule::CRITICAL, 1, [
            'instance' => ['labels' => $entry['labels']],
            'extra' => ['entry' => $entry],
        ]);

        haApply($key, json_encode($value))->assertOk()->assertJson(['applied' => true]);

        expect(PrometheusCheck::where('alertRuleId', $alertRule->_id)->first()->alerts)->toHaveCount(1)
            ->and((int) HaStateVersion::where('key', $key)->value('version'))->toBe(1);
    });

    it('removes the slot on a json null, the tombstone the sidecar replicates', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::PROMETHEUS);
        $entry = haPrometheusEntry('10.0.0.4:9100');
        $instanceId = AlertStateKey::prometheusInstanceId($entry['labels']);
        $key = 'alert:'.$alertRule->_id.':prometheus:'.$instanceId;

        haApply($key, json_encode(haValue($alertRule, 'prometheus', $instanceId, AlertRule::CRITICAL, 1, [
            'instance' => ['labels' => $entry['labels']],
            'extra' => ['entry' => $entry],
        ])))->assertOk();

        haApply($key, null)->assertOk()->assertJson(['applied' => true]);

        expect(HaStateVersion::where('key', $key)->count())->toBe(0);
    });

    /*
     | A payload nobody can read must not be mistaken for a tombstone: dropping
     | the slot on unreadable input would delete state the leader still holds.
     */
    it('rejects a value that is not a slot document rather than treating it as a delete', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::PROMETHEUS);
        $key = 'alert:'.$alertRule->_id.':prometheus:9f8a1c';

        haApply($key, '"mobin"')->assertStatus(422);
        haApply($key, 'not json at all')->assertStatus(422);
    });
});

describe('POST /api/ha/apply prometheus', function () {
    it('writes the alert into the check and the transition into the history', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::PROMETHEUS);
        $entry = haPrometheusEntry('10.0.0.4:9100');
        $instanceId = AlertStateKey::prometheusInstanceId($entry['labels']);

        haApply(
            'alert:'.$alertRule->_id.':prometheus:'.$instanceId,
            haValue($alertRule, 'prometheus', $instanceId, AlertRule::CRITICAL, 1, [
                'instance' => ['labels' => $entry['labels']],
                'extra' => ['entry' => $entry, 'annotations' => $entry['annotations'], 'dataSourceId' => 'ds-1'],
            ]),
        )->assertOk()->assertJson(['applied' => true]);

        $check = PrometheusCheck::where('alertRuleId', $alertRule->_id)->first();

        expect($check)->not->toBeNull()
            ->and((int) $check->state)->toBe(PrometheusCheck::FIRE)
            ->and($check->alerts)->toHaveCount(1)
            ->and($check->alerts[0]['labels'])->toBe($entry['labels'])
            ->and(PrometheusHistory::where('alertRuleId', $alertRule->_id)->count())->toBe(1)
            ->and(AlertRule::find($alertRule->_id)->state)->toBe(AlertRule::CRITICAL);
    });

    it('keeps one entry per instance rather than appending duplicates', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::PROMETHEUS);
        $entry = haPrometheusEntry('10.0.0.4:9100');
        $instanceId = AlertStateKey::prometheusInstanceId($entry['labels']);
        $key = 'alert:'.$alertRule->_id.':prometheus:'.$instanceId;

        haApply($key, haValue($alertRule, 'prometheus', $instanceId, AlertRule::CRITICAL, 1, [
            'instance' => ['labels' => $entry['labels']],
            'extra' => ['entry' => $entry],
        ]))->assertOk();

        haApply($key, haValue($alertRule, 'prometheus', $instanceId, AlertRule::RESOlVED, 2, [
            'instance' => ['labels' => $entry['labels']],
            'extra' => ['entry' => haPrometheusEntry('10.0.0.4:9100', PrometheusCheck::RESOLVED)],
        ]))->assertOk();

        $check = PrometheusCheck::where('alertRuleId', $alertRule->_id)->first();

        expect($check->alerts)->toHaveCount(1)
            ->and((int) $check->alerts[0]['skylogsStatus'])->toBe(PrometheusCheck::RESOLVED)
            ->and((int) $check->state)->toBe(PrometheusCheck::RESOLVED)
            ->and(PrometheusHistory::where('alertRuleId', $alertRule->_id)->count())->toBe(2);
    });

    it('removes the slot on a tombstone', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::PROMETHEUS);
        $entry = haPrometheusEntry('10.0.0.4:9100');
        $instanceId = AlertStateKey::prometheusInstanceId($entry['labels']);
        $key = 'alert:'.$alertRule->_id.':prometheus:'.$instanceId;

        haApply($key, haValue($alertRule, 'prometheus', $instanceId, AlertRule::CRITICAL, 1, [
            'instance' => ['labels' => $entry['labels']],
            'extra' => ['entry' => $entry],
        ]))->assertOk();

        haApply($key, null)->assertOk()->assertJson(['applied' => true]);

        expect(PrometheusCheck::where('alertRuleId', $alertRule->_id)->first()->alerts)->toBe([])
            ->and(HaStateVersion::where('key', $key)->count())->toBe(0);
    });
});

describe('POST /api/ha/apply other types', function () {
    it('keys a grafana instance by its fingerprint and leaves a webhook document behind', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::GRAFANA);
        $entry = [
            'fingerprint' => 'deadbeef',
            'status' => GrafanaWebhookAlert::FIRING,
            'labels' => ['alertname' => 'Latency', 'severity' => 'critical'],
            'annotations' => ['summary' => 'slow'],
            'instanceKey' => 'deadbeef',
        ];

        haApply(
            'alert:'.$alertRule->_id.':grafana:deadbeef',
            haValue($alertRule, 'grafana', 'deadbeef', AlertRule::CRITICAL, 1, [
                'instance' => ['instanceKey' => 'deadbeef', 'labels' => $entry['labels']],
                'extra' => ['entry' => $entry],
            ]),
        )->assertOk();

        $check = GrafanaCheck::where('alertRuleId', $alertRule->_id)->first();

        expect($check->alerts)->toHaveCount(1)
            ->and($check->alerts[0]['instanceKey'])->toBe('deadbeef')
            ->and($check->state)->toBe(GrafanaWebhookAlert::FIRING)
            ->and(GrafanaWebhookAlert::where('alertRuleId', $alertRule->_id)->count())->toBe(1);
    });

    it('pushes and pulls a zabbix event id', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::ZABBIX);
        $key = 'alert:'.$alertRule->_id.':zabbix:9001';

        haApply($key, haValue($alertRule, 'zabbix', '9001', AlertRule::CRITICAL, 1, [
            'instance' => ['eventId' => '9001'],
            'extra' => ['eventSeverity' => 'High'],
        ]))->assertOk();

        expect(ZabbixCheck::where('alertRuleId', $alertRule->_id)->first()->fireEvents)->toBe(['9001']);

        haApply($key, haValue($alertRule, 'zabbix', '9001', AlertRule::RESOlVED, 2, [
            'instance' => ['eventId' => '9001'],
        ]))->assertOk();

        expect(ZabbixCheck::where('alertRuleId', $alertRule->_id)->first()->fireEvents)->toBe([])
            ->and(ZabbixWebhookAlert::where('alertRuleId', $alertRule->_id)->count())->toBe(2);
    });

    it('creates the api instance and both of its history rows', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::API);
        $instanceId = AlertStateKey::apiInstanceId('srv-1');

        haApply(
            'alert:'.$alertRule->_id.':api:'.$instanceId,
            haValue($alertRule, 'api', $instanceId, AlertRule::CRITICAL, 1, [
                'instance' => ['instance' => 'srv-1'],
                'extra' => ['instanceState' => AlertInstance::FIRE, 'description' => 'connection refused'],
            ]),
        )->assertOk();

        $instance = AlertInstance::where('alertRuleId', $alertRule->_id)->first();

        expect($instance)->not->toBeNull()
            ->and($instance->instance)->toBe('srv-1')
            ->and((int) $instance->state)->toBe(AlertInstance::FIRE)
            ->and(ApiAlertHistory::where('alertRuleId', $alertRule->_id)->count())->toBe(1)
            ->and(ApiAlertStatusHistory::where('alertRuleId', $alertRule->_id)->count())->toBe(1);
    });

    it('copies the whole check body for a single slot type', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::ELASTIC, [
            'queryString' => 'level:error',
            'minutes' => 5,
            'countDocument' => 10,
        ]);

        haApply(
            'alert:'.$alertRule->_id.':elastic:'.AlertStateKey::SINGLE_SLOT,
            haValue($alertRule, 'elastic', AlertStateKey::SINGLE_SLOT, AlertRule::CRITICAL, 1, [
                'extra' => ['check' => [
                    'alertRuleId' => (string) $alertRule->_id,
                    'queryString' => 'level:error',
                    'minutes' => 5,
                    'countDocument' => 10,
                    'currentCountDocument' => 15,
                    'state' => ElasticCheck::FIRE,
                ]],
            ]),
        )->assertOk();

        $check = ElasticCheck::where('alertRuleId', $alertRule->_id)->first();
        $history = ElasticHistory::where('alertRuleId', $alertRule->_id)->first();

        expect((int) $check->state)->toBe(ElasticCheck::FIRE)
            ->and((int) $check->currentCountDocument)->toBe(15)
            ->and($history)->not->toBeNull()
            ->and((int) $history->state)->toBe(ElasticHistory::FIRE);
    });

    /*
     | Health is the awkward one: AlertRuleObserver deletes the check whenever
     | the rule is saved, exactly as it does on the leader, so the rule state is
     | what has to carry the transition on both sides.
     */
    it('records both edges of a health transition even though the check is dropped', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::HEALTH, [
            'alertname' => 'HA Apply Alert',
            'url' => 'https://health.example.com',
            'threshold' => 3,
        ]);

        $key = 'alert:'.$alertRule->_id.':health:'.AlertStateKey::SINGLE_SLOT;

        haApply($key, haValue($alertRule, 'health', AlertStateKey::SINGLE_SLOT, AlertRule::CRITICAL, 1, [
            'extra' => ['check' => ['state' => HealthCheck::DOWN, 'counter' => 3]],
        ]))->assertOk();

        haApply($key, haValue($alertRule, 'health', AlertStateKey::SINGLE_SLOT, AlertRule::RESOlVED, 2, [
            'extra' => ['check' => ['state' => HealthCheck::UP, 'counter' => 0]],
        ]))->assertOk();

        $states = HealthHistory::where('alertRuleId', $alertRule->_id)
            ->orderBy('createdAt')
            ->get()
            ->map(fn (HealthHistory $history): int => (int) $history->state)
            ->all();

        expect($states)->toBe([HealthHistory::DOWN, HealthHistory::UP])
            ->and(AlertRule::find($alertRule->_id)->state)->toBe(AlertRule::RESOlVED);
    });
});

describe('POST /api/ha/apply idempotence', function () {
    it('treats a replay of the same version as a no-op', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::PROMETHEUS);
        $entry = haPrometheusEntry('10.0.0.4:9100');
        $instanceId = AlertStateKey::prometheusInstanceId($entry['labels']);
        $key = 'alert:'.$alertRule->_id.':prometheus:'.$instanceId;
        $value = haValue($alertRule, 'prometheus', $instanceId, AlertRule::CRITICAL, 1, [
            'instance' => ['labels' => $entry['labels']],
            'extra' => ['entry' => $entry],
        ]);

        haApply($key, $value)->assertOk()->assertJson(['applied' => true]);
        haApply($key, $value)->assertOk()->assertJson(['applied' => false, 'reason' => 'stale']);

        expect(PrometheusHistory::where('alertRuleId', $alertRule->_id)->count())->toBe(1);
    });

    it('rejects a delivery that arrives out of order', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::PROMETHEUS);
        $entry = haPrometheusEntry('10.0.0.4:9100');
        $instanceId = AlertStateKey::prometheusInstanceId($entry['labels']);
        $key = 'alert:'.$alertRule->_id.':prometheus:'.$instanceId;

        haApply($key, haValue($alertRule, 'prometheus', $instanceId, AlertRule::RESOlVED, 9, [
            'instance' => ['labels' => $entry['labels']],
            'extra' => ['entry' => haPrometheusEntry('10.0.0.4:9100', PrometheusCheck::RESOLVED)],
        ]))->assertOk();

        haApply($key, haValue($alertRule, 'prometheus', $instanceId, AlertRule::CRITICAL, 4, [
            'instance' => ['labels' => $entry['labels']],
            'extra' => ['entry' => $entry],
        ]))->assertOk()->assertJson(['applied' => false, 'reason' => 'stale']);

        expect(AlertRule::find($alertRule->_id)->state)->toBe(AlertRule::RESOlVED);
    });

    it('reports an unknown alert rule rather than inventing one', function () {
        haApply('alert:6512ab000000000000009999:prometheus:aaa', [
            'version' => 1,
            'nodeId' => 'node-1',
            'state' => AlertRule::CRITICAL,
        ])->assertOk()->assertJson(['applied' => false, 'reason' => 'unknownAlertRule']);
    });
});

describe('POST /api/ha/apply notifications', function () {
    it('never notifies, however many transitions it applies', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::API);
        $instanceId = AlertStateKey::apiInstanceId('srv-1');
        $key = 'alert:'.$alertRule->_id.':api:'.$instanceId;

        haApply($key, haValue($alertRule, 'api', $instanceId, AlertRule::CRITICAL, 1, [
            'instance' => ['instance' => 'srv-1'],
            'extra' => ['instanceState' => AlertInstance::FIRE],
        ]))->assertOk();

        haApply($key, haValue($alertRule, 'api', $instanceId, AlertRule::RESOlVED, 2, [
            'instance' => ['instance' => 'srv-1'],
            'extra' => ['instanceState' => AlertInstance::RESOLVED],
        ]))->assertOk();

        Queue::assertNotPushed(SendNotifyJob::class);

        expect(Notify::where('alertRuleId', $alertRule->_id)->count())->toBe(0);
    });
});

describe('POST /api/ha/apply timeline', function () {
    it('produces a fire then resolve timeline the dashboard can read', function () {
        $alertRule = haAlertRuleOfType(AlertRuleType::PROMETHEUS);
        $entry = haPrometheusEntry('10.0.0.4:9100');
        $instanceId = AlertStateKey::prometheusInstanceId($entry['labels']);
        $key = 'alert:'.$alertRule->_id.':prometheus:'.$instanceId;

        /*
         | The two transitions have to land in different buckets, otherwise the
         | assertion would only prove that one of them was written.
         */
        $firedAt = Carbon::parse('2026-07-26 12:00:00', 'UTC');

        Carbon::setTestNow($firedAt);

        haApply($key, haValue($alertRule, 'prometheus', $instanceId, AlertRule::CRITICAL, 1, [
            'instance' => ['labels' => $entry['labels']],
            'extra' => ['entry' => $entry],
        ]))->assertOk();

        Carbon::setTestNow($firedAt->copy()->addMinutes(30));

        haApply($key, haValue($alertRule, 'prometheus', $instanceId, AlertRule::RESOlVED, 2, [
            'instance' => ['labels' => $entry['labels']],
            'extra' => ['entry' => haPrometheusEntry('10.0.0.4:9100', PrometheusCheck::RESOLVED)],
        ]))->assertOk();

        $timeline = app(AlertRuleService::class)->getAlertsStatusHistory(
            [(string) $alertRule->_id],
            $firedAt->copy()->subHour()->getTimestamp(),
            $firedAt->copy()->addHour()->getTimestamp(),
            $this->owner,
        );

        $segments = collect($timeline)->firstWhere('alertRuleId', (string) $alertRule->_id)['segments'] ?? [];

        expect(collect($segments)->pluck('status')->unique()->values()->all())
            ->toContain(AlertRule::CRITICAL)
            ->toContain(AlertRule::RESOlVED);
    });
});
