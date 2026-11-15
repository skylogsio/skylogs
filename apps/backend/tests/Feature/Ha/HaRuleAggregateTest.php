<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\GrafanaCheck;
use App\Models\GrafanaWebhookAlert;
use App\Models\HaStateVersion;
use App\Models\PrometheusCheck;
use App\Services\GrafanaService;
use App\Services\Ha\AlertStateKey;
use App\Services\Ha\AlertStateReplicator;
use App\Services\Ha\HaLeaderService;
use App\Services\Ha\HaStateVersionStore;
use App\Services\Ha\Projectors\StateProjectorFactory;
use App\Services\Ha\RaftClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TeamTestData;

/**
 * @return list<array{key: string, value: array<string, mixed>|null}>
 */
function haAggregatePublished(): array
{
    return test()->published->getArrayCopy();
}

function haAggregateReplicator(): AlertStateReplicator
{
    $leader = Mockery::mock(HaLeaderService::class);
    $leader->shouldReceive('isLeader')->andReturn(true);
    $leader->shouldReceive('nodeId')->andReturn('node-1');

    $published = test()->published;
    $raft = Mockery::mock(RaftClient::class);
    $raft->shouldReceive('set')->andReturnUsing(function (string $key, ?array $value) use ($published): void {
        $published->append(['key' => $key, 'value' => $value]);
    });

    return new AlertStateReplicator($leader, new StateProjectorFactory, app(HaStateVersionStore::class), $raft);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function haAggregateRule(AlertRuleType $type, array $attributes = []): AlertRule
{
    $alertRule = AlertRule::withoutEvents(fn () => AlertRule::create([
        'name' => 'HA Aggregate Alert',
        'type' => $type->value,
        'userId' => test()->owner->id,
        'state' => AlertRule::RESOlVED,
        'fireCount' => 0,
        ...$attributes,
    ]));

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
        'ha.leader_cache_seconds' => 0,
        'ha.raft.url' => 'http://raft:8000',
    ]);

    $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
    $this->createdAlertRules = [];
    $this->published = new ArrayObject;

    Queue::fake();
});

afterEach(function () {
    foreach ($this->createdAlertRules as $alertRule) {
        $alertRuleId = $alertRule->_id;

        AlertInstance::query()->where('alertRuleId', $alertRuleId)->delete();
        PrometheusCheck::query()->where('alertRuleId', $alertRuleId)->delete();
        GrafanaCheck::query()->where('alertRuleId', $alertRuleId)->delete();
        HaStateVersion::query()->where('key', 'like', AlertStateKey::prefixFor((string) $alertRuleId).'%')->delete();
        AlertRule::query()->where('_id', $alertRuleId)->delete();
    }

    TeamTestData::deleteUser($this->owner);
});

describe('HA rule aggregate carrier', function () {
    it('publishes one resolved api instance when nothing is firing', function () {
        $alertRule = haAggregateRule(AlertRuleType::API);

        AlertInstance::withoutEvents(fn () => AlertInstance::create([
            'alertRuleId' => $alertRule->_id,
            'instance' => 'srv-1',
            'state' => AlertInstance::RESOLVED,
        ]));

        haAggregateReplicator()->replicateRule($alertRule);

        $publish = haAggregatePublished()[0];

        expect(haAggregatePublished())->toHaveCount(1)
            ->and($publish['key'])->toBe('alert:'.$alertRule->_id.':api:'.AlertStateKey::apiInstanceId('srv-1'))
            ->and($publish['value']['state'])->toBe(AlertRule::RESOlVED)
            ->and($publish['value']['rule']['state'])->toBe(AlertRule::RESOlVED)
            ->and($publish['value']['rule']['fireCount'])->toBe(0);
    });

    it('publishes only the firing api instance when one is still firing', function () {
        $alertRule = haAggregateRule(AlertRuleType::API, [
            'state' => AlertRule::CRITICAL,
            'fireCount' => 1,
        ]);

        AlertInstance::withoutEvents(function () use ($alertRule): void {
            AlertInstance::create([
                'alertRuleId' => $alertRule->_id,
                'instance' => 'srv-fire',
                'state' => AlertInstance::FIRE,
            ]);
            AlertInstance::create([
                'alertRuleId' => $alertRule->_id,
                'instance' => 'srv-resolved',
                'state' => AlertInstance::RESOLVED,
            ]);
        });

        haAggregateReplicator()->replicateRule($alertRule);

        $publish = haAggregatePublished()[0];

        expect(haAggregatePublished())->toHaveCount(1)
            ->and($publish['key'])->toBe('alert:'.$alertRule->_id.':api:'.AlertStateKey::apiInstanceId('srv-fire'))
            ->and($publish['value']['state'])->toBe(AlertRule::CRITICAL)
            ->and($publish['value']['rule']['fireCount'])->toBe(1);
    });

    it('publishes one resolved prometheus entry when every alert is resolved', function () {
        $alertRule = haAggregateRule(AlertRuleType::PROMETHEUS);
        $first = ['alertname' => 'NodeDown', 'instance' => '10.0.0.1:9100'];
        $second = ['alertname' => 'NodeDown', 'instance' => '10.0.0.2:9100'];

        PrometheusCheck::withoutEvents(fn () => PrometheusCheck::create([
            'alertRuleId' => $alertRule->_id,
            'state' => PrometheusCheck::RESOLVED,
            'alerts' => [
                [
                    'labels' => $first,
                    'annotations' => ['summary' => 'up'],
                    'skylogsStatus' => PrometheusCheck::RESOLVED,
                ],
                [
                    'labels' => $second,
                    'annotations' => ['summary' => 'up'],
                    'skylogsStatus' => PrometheusCheck::RESOLVED,
                ],
            ],
        ]));

        haAggregateReplicator()->replicateRule($alertRule);

        $publish = haAggregatePublished()[0];

        expect(haAggregatePublished())->toHaveCount(1)
            ->and($publish['key'])->toBe('alert:'.$alertRule->_id.':prometheus:'.AlertStateKey::prometheusInstanceId($first))
            ->and($publish['value']['state'])->toBe(AlertRule::RESOlVED)
            ->and($publish['value']['rule']['state'])->toBe(AlertRule::RESOlVED)
            ->and($publish['value']['rule']['fireCount'])->toBe(0);
    });

    it('publishes a grafana log key when the check batch is empty', function () {
        $alertRule = haAggregateRule(AlertRuleType::GRAFANA);
        $key = 'alert:'.$alertRule->_id.':grafana:fp-1';

        GrafanaCheck::withoutEvents(fn () => GrafanaCheck::create([
            'alertRuleId' => $alertRule->_id,
            'state' => GrafanaWebhookAlert::RESOLVED,
            'alerts' => [],
        ]));

        HaStateVersion::create([
            'key' => $key,
            'version' => 4,
            'nodeId' => 'node-1',
            'state' => AlertRule::CRITICAL,
        ]);

        haAggregateReplicator()->replicateRule($alertRule);

        $publish = haAggregatePublished()[0];

        expect(haAggregatePublished())->toHaveCount(1)
            ->and($publish['key'])->toBe($key)
            ->and($publish['value']['state'])->toBe(AlertRule::RESOlVED)
            ->and($publish['value']['rule']['state'])->toBe(AlertRule::RESOlVED)
            ->and($publish['value']['rule']['fireCount'])->toBe(0);
    });
});

describe('Grafana rule staging', function () {
    it('publishes the resolved aggregate when the last grafana alert clears', function () {
        $alertRule = haAggregateRule(AlertRuleType::GRAFANA, [
            'state' => AlertRule::CRITICAL,
            'fireCount' => 1,
        ]);
        $fingerprint = 'fp-1';

        GrafanaCheck::withoutEvents(fn () => GrafanaCheck::create([
            'alertRuleId' => $alertRule->_id,
            'state' => GrafanaWebhookAlert::FIRING,
            'alerts' => [[
                'fingerprint' => $fingerprint,
                'status' => GrafanaWebhookAlert::FIRING,
                'labels' => ['alertname' => 'DiskFull'],
                'annotations' => ['summary' => 'disk full'],
            ]],
        ]));

        Http::fake([
            'raft:8000/leader' => Http::response([
                'leader' => true,
                'leaderNode' => 'node-2',
                'address' => '192.168.56.12:7000',
            ]),
            'raft:8000/set' => Http::response([]),
        ]);

        GrafanaService::updateAlertRuleStatus($alertRule, [[
            'fingerprint' => $fingerprint,
            'status' => GrafanaWebhookAlert::RESOLVED,
            'labels' => ['alertname' => 'DiskFull'],
        ]]);

        $published = Http::recorded()
            ->map(fn (array $pair) => $pair[0]->data())
            ->filter(fn (array $body): bool => isset($body['key']) && is_string($body['key']) && str_contains($body['key'], ':grafana:'))
            ->values();

        expect($published)->not->toBeEmpty();

        foreach ($published as $body) {
            expect($body['value']['state'])->toBe(AlertRule::RESOlVED)
                ->and($body['value']['rule']['state'])->toBe(AlertRule::RESOlVED)
                ->and($body['value']['rule']['fireCount'])->toBe(0);
        }
    });
});
