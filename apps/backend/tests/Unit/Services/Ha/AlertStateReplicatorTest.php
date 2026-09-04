<?php

use App\Enums\AlertRuleType;
use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Models\GrafanaCheck;
use App\Models\HealthCheck;
use App\Models\PrometheusCheck;
use App\Models\ZabbixCheck;
use App\Services\Ha\AlertStateKey;
use App\Services\Ha\AlertStateReplicator;
use App\Services\Ha\HaLeaderService;
use App\Services\Ha\HaReplicationContext;
use App\Services\Ha\HaStateVersionStore;
use App\Services\Ha\Projectors\StateProjectorFactory;
use App\Services\Ha\RaftClient;
use Tests\Support\Factories\AlertRuleFactory;

const HA_RULE_ID = '6512ab000000000000000001';

/**
 * Stands in for the MongoDB backed counter so the projection logic can be
 * exercised without a database.
 */
final class HaVersionStoreStub extends HaStateVersionStore
{
    /** @var array<string, int> */
    public array $versions = [];

    /** @var array<string, string|null> */
    public array $states = [];

    /** @var array<int, string> */
    public array $forgotten = [];

    public function next(string $key, ?string $state = null): int
    {
        $this->states[$key] = $state;

        return $this->versions[$key] = ($this->versions[$key] ?? 0) + 1;
    }

    public function current(string $key): int
    {
        return $this->versions[$key] ?? 0;
    }

    public function keysWithPrefix(string $prefix): array
    {
        return array_values(array_filter(
            array_keys($this->versions),
            fn (string $key): bool => str_starts_with($key, $prefix),
        ));
    }

    public function unresolvedKeysWithPrefix(string $prefix): array
    {
        return array_values(array_filter(
            $this->keysWithPrefix($prefix),
            fn (string $key): bool => ($this->states[$key] ?? null) !== AlertRule::RESOlVED,
        ));
    }

    public function forget(string $key): void
    {
        unset($this->versions[$key], $this->states[$key]);
        $this->forgotten[] = $key;
    }
}

/**
 * @return list<array{key: string, value: array<string, mixed>|null}>
 */
function haPublished(): array
{
    return test()->published->getArrayCopy();
}

function haVersions(): HaVersionStoreStub
{
    return test()->versions;
}

function haReplicator(bool $isLeader = true): AlertStateReplicator
{
    $leader = Mockery::mock(HaLeaderService::class);
    $leader->shouldReceive('isLeader')->andReturn($isLeader);
    $leader->shouldReceive('nodeId')->andReturn('node-1');

    $published = test()->published;
    $raft = Mockery::mock(RaftClient::class);
    $raft->shouldReceive('set')->andReturnUsing(function (string $key, ?array $value) use ($published): void {
        $published->append(['key' => $key, 'value' => $value]);
    });

    return new AlertStateReplicator($leader, new StateProjectorFactory, haVersions(), $raft);
}

function haAlertRule(AlertRuleType $type, array $attributes = []): AlertRule
{
    return AlertRuleFactory::unsaved([
        '_id' => HA_RULE_ID,
        'name' => 'node down',
        'type' => $type,
        'state' => AlertRule::CRITICAL,
        'fireCount' => 1,
        ...$attributes,
    ]);
}

/**
 * Puts a model in the state an observer sees after a save: the previous values
 * are the original, the new ones are current.
 */
function haSavedCheck(string $model, array $original, array $current): BaseModel
{
    $check = new $model;

    foreach ($original as $attribute => $value) {
        $check->setAttribute($attribute, $value);
    }

    $check->syncOriginal();

    foreach ($current as $attribute => $value) {
        $check->setAttribute($attribute, $value);
    }

    $check->syncChanges();

    return $check;
}

function haPrometheusAlert(string $instance, int $status = PrometheusCheck::FIRE): array
{
    return [
        'dataSourceId' => 'ds-1',
        'dataSourceName' => 'Prom A',
        'alertRuleName' => 'node down',
        'dataSourceAlertName' => 'NodeDown',
        'labels' => ['alertname' => 'NodeDown', 'instance' => $instance],
        'annotations' => ['summary' => 'down'],
        'skylogsStatus' => $status,
    ];
}

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'ha.enabled' => true,
        'ha.node_id' => 'node-1',
    ]);

    $this->versions = new HaVersionStoreStub;
    $this->published = new ArrayObject;
});

describe('AlertStateReplicator prometheus', function () {
    it('publishes one change per changed instance', function () {
        $rule = haAlertRule(AlertRuleType::PROMETHEUS);

        $check = haSavedCheck(PrometheusCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'state' => PrometheusCheck::FIRE,
            'alerts' => [haPrometheusAlert('n1'), haPrometheusAlert('n2')],
        ], [
            'alerts' => [haPrometheusAlert('n1'), haPrometheusAlert('n2', PrometheusCheck::RESOLVED)],
        ]);

        haReplicator()->replicateCheck($check, $rule);

        expect(haPublished())->toHaveCount(1);

        $publish = haPublished()[0];
        $instanceId = AlertStateKey::prometheusInstanceId(['alertname' => 'NodeDown', 'instance' => 'n2']);

        expect($publish['key'])->toBe('alert:'.HA_RULE_ID.':prometheus:'.$instanceId)
            ->and($publish['value']['state'])->toBe(AlertRule::RESOlVED)
            ->and($publish['value']['instance'])->toBe(['labels' => ['alertname' => 'NodeDown', 'instance' => 'n2']]);
    });

    it('stamps the key, version, node and rule aggregate onto the payload', function () {
        $rule = haAlertRule(AlertRuleType::PROMETHEUS, ['fireCount' => 3, 'notifyAt' => 1785000000]);

        $check = haSavedCheck(PrometheusCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'state' => PrometheusCheck::FIRE,
        ], [
            'alerts' => [haPrometheusAlert('n1')],
        ]);

        haReplicator()->replicateCheck($check, $rule);

        $publish = haPublished()[0];

        expect($publish['value']['key'])->toBe($publish['key'])
            ->and($publish['value']['version'])->toBe(1)
            ->and($publish['value']['nodeId'])->toBe('node-1')
            ->and($publish['value']['alertRuleId'])->toBe(HA_RULE_ID)
            ->and($publish['value']['alertRuleName'])->toBe('node down')
            ->and($publish['value']['type'])->toBe('prometheus')
            ->and($publish['value']['state'])->toBe(AlertRule::CRITICAL)
            ->and($publish['value']['resolvedAt'])->toBeNull()
            ->and($publish['value']['firedAt'])->toBeInt()
            ->and($publish['value']['rule'])->toBe([
                'state' => AlertRule::CRITICAL,
                'fireCount' => 3,
                'notifyAt' => 1785000000,
                'acknowledgedBy' => null,
            ])
            ->and($publish['value']['extra']['annotations'])->toBe(['summary' => 'down']);
    });

    it('publishes a pruned instance as resolved so the follower can close its timeline', function () {
        $rule = haAlertRule(AlertRuleType::PROMETHEUS);

        $check = haSavedCheck(PrometheusCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'state' => PrometheusCheck::FIRE,
            'alerts' => [haPrometheusAlert('n1', PrometheusCheck::RESOLVED)],
        ], [
            'alerts' => [],
        ]);

        haReplicator()->replicateCheck($check, $rule);

        expect(haPublished()[0]['value']['state'])->toBe(AlertRule::RESOlVED);
    });

    it('publishes nothing when no instance moved', function () {
        $rule = haAlertRule(AlertRuleType::PROMETHEUS);

        $check = haSavedCheck(PrometheusCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'state' => PrometheusCheck::FIRE,
            'alerts' => [haPrometheusAlert('n1')],
        ], [
            'alerts' => [haPrometheusAlert('n1')],
        ]);

        haReplicator()->replicateCheck($check, $rule);

        expect(haPublished())->toBeEmpty();
    });
});

describe('AlertStateReplicator grafana', function () {
    it('keys an instance by its fingerprint', function () {
        $rule = haAlertRule(AlertRuleType::GRAFANA);

        $check = haSavedCheck(GrafanaCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'alerts' => [],
        ], [
            'alerts' => [[
                'fingerprint' => 'deadbeef',
                'status' => 'firing',
                'labels' => ['alertname' => 'Latency'],
                'annotations' => [],
            ]],
        ]);

        haReplicator()->replicateCheck($check, $rule);

        $publish = haPublished()[0];

        expect($publish['key'])->toBe('alert:'.HA_RULE_ID.':grafana:deadbeef')
            ->and($publish['value']['state'])->toBe(AlertRule::CRITICAL);
    });

    it('publishes a resolve when the instance leaves the batch', function () {
        $rule = haAlertRule(AlertRuleType::GRAFANA, ['state' => AlertRule::RESOlVED, 'fireCount' => 0]);

        $check = haSavedCheck(GrafanaCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'alerts' => [[
                'fingerprint' => 'deadbeef',
                'status' => 'firing',
                'labels' => ['alertname' => 'Latency'],
            ]],
        ], [
            'alerts' => [],
        ]);

        haReplicator()->replicateCheck($check, $rule);

        $publish = haPublished()[0];

        expect($publish['key'])->toBe('alert:'.HA_RULE_ID.':grafana:deadbeef')
            ->and($publish['value']['state'])->toBe(AlertRule::RESOlVED)
            ->and($publish['value']['resolvedAt'])->toBeInt();
    });
});

describe('AlertStateReplicator zabbix', function () {
    it('keys an instance by its event id', function () {
        $rule = haAlertRule(AlertRuleType::ZABBIX);

        $check = haSavedCheck(ZabbixCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'fireEvents' => [],
        ], [
            'fireEvents' => ['9001'],
        ]);

        haReplicator()->replicateCheck($check, $rule);

        expect(haPublished()[0]['key'])->toBe('alert:'.HA_RULE_ID.':zabbix:9001');
    });

    it('publishes a resolve for an event that was pulled from the check', function () {
        $rule = haAlertRule(AlertRuleType::ZABBIX);

        $check = haSavedCheck(ZabbixCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'fireEvents' => ['9001', '9002'],
        ], [
            'fireEvents' => ['9001'],
        ]);

        haReplicator()->replicateCheck($check, $rule);

        expect(haPublished())->toHaveCount(1);

        $publish = haPublished()[0];

        expect($publish['key'])->toBe('alert:'.HA_RULE_ID.':zabbix:9002')
            ->and($publish['value']['state'])->toBe(AlertRule::RESOlVED);
    });
});

describe('AlertStateReplicator api', function () {
    it('hashes the instance name into the key', function () {
        $rule = haAlertRule(AlertRuleType::API);

        $instance = haSavedCheck(AlertInstance::class, [
            'alertRuleId' => HA_RULE_ID,
            'instance' => 'srv-1',
            'state' => AlertInstance::RESOLVED,
        ], [
            'state' => AlertInstance::FIRE,
        ]);

        haReplicator()->replicateCheck($instance, $rule);

        $publish = haPublished()[0];

        expect($publish['key'])->toBe('alert:'.HA_RULE_ID.':api:'.sha1('srv-1'))
            ->and($publish['value']['instance'])->toBe(['instance' => 'srv-1'])
            ->and($publish['value']['state'])->toBe(AlertRule::CRITICAL);
    });

    it('tombstones a deleted instance', function () {
        $rule = haAlertRule(AlertRuleType::API);

        $instance = haSavedCheck(AlertInstance::class, [
            'alertRuleId' => HA_RULE_ID,
            'instance' => 'srv-1',
            'state' => AlertInstance::FIRE,
        ], []);

        haReplicator()->replicateCheckDeletion($instance, $rule);

        $publish = haPublished()[0];

        expect($publish['key'])->toBe('alert:'.HA_RULE_ID.':api:'.sha1('srv-1'))
            ->and($publish['value'])->toBeNull();
    });
});

describe('AlertStateReplicator single slot types', function () {
    it('uses the single slot instance segment for health', function () {
        $rule = haAlertRule(AlertRuleType::HEALTH);

        $check = haSavedCheck(HealthCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'state' => HealthCheck::UP,
            'counter' => 0,
        ], [
            'state' => HealthCheck::DOWN,
            'counter' => 3,
        ]);

        haReplicator()->replicateCheck($check, $rule);

        $publish = haPublished()[0];

        expect($publish['key'])->toBe('alert:'.HA_RULE_ID.':health:_')
            ->and($publish['value']['state'])->toBe(AlertRule::CRITICAL)
            ->and($publish['value']['extra']['check']['counter'])->toBe(3);
    });
});

describe('AlertStateReplicator alert rule aggregate', function () {
    it('publishes the rule slot for a type that keeps no check document', function () {
        $rule = haAlertRule(AlertRuleType::SENTRY, ['state' => AlertRule::CRITICAL]);
        $rule->syncOriginal();
        $rule->state = AlertRule::RESOlVED;
        $rule->syncChanges();

        haReplicator()->replicateRule($rule);

        $publish = haPublished()[0];

        expect($publish['key'])->toBe('alert:'.HA_RULE_ID.':sentry:_')
            ->and($publish['value']['state'])->toBe(AlertRule::RESOlVED);
    });

    it('publishes nothing when the save did not move the aggregate', function () {
        $rule = haAlertRule(AlertRuleType::SENTRY);
        $rule->syncOriginal();
        $rule->name = 'renamed';
        $rule->syncChanges();

        haReplicator()->replicateRule($rule);

        expect(haPublished())->toBeEmpty();
    });

    it('tombstones every slot of a deleted rule', function () {
        $rule = haAlertRule(AlertRuleType::PROMETHEUS);

        haVersions()->next('alert:'.HA_RULE_ID.':prometheus:aaa', AlertRule::CRITICAL);
        haVersions()->next('alert:'.HA_RULE_ID.':prometheus:bbb', AlertRule::RESOlVED);
        haVersions()->next('alert:6512ab000000000000000002:prometheus:ccc', AlertRule::CRITICAL);

        haReplicator()->replicateRuleDeletion($rule);

        expect(haPublished())->toHaveCount(2)
            ->and(collect(haPublished())->every(fn (array $publish): bool => $publish['value'] === null))->toBeTrue()
            ->and(haVersions()->forgotten)->toBe([
                'alert:'.HA_RULE_ID.':prometheus:aaa',
                'alert:'.HA_RULE_ID.':prometheus:bbb',
            ]);
    });
});

describe('AlertStateReplicator guards', function () {
    it('publishes nothing on a follower', function () {
        $rule = haAlertRule(AlertRuleType::PROMETHEUS);

        $check = haSavedCheck(PrometheusCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'alerts' => [],
        ], [
            'alerts' => [haPrometheusAlert('n1')],
        ]);

        haReplicator(isLeader: false)->replicateCheck($check, $rule);

        expect(haPublished())->toBeEmpty();
    });

    it('publishes nothing while applying replicated state', function () {
        $rule = haAlertRule(AlertRuleType::PROMETHEUS);

        $check = haSavedCheck(PrometheusCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'alerts' => [],
        ], [
            'alerts' => [haPrometheusAlert('n1')],
        ]);

        HaReplicationContext::apply(fn () => haReplicator()->replicateCheck($check, $rule));

        expect(haPublished())->toBeEmpty();
    });

    it('publishes nothing while ha is disabled', function () {
        config(['ha.enabled' => false]);

        $rule = haAlertRule(AlertRuleType::PROMETHEUS);

        $check = haSavedCheck(PrometheusCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'alerts' => [],
        ], [
            'alerts' => [haPrometheusAlert('n1')],
        ]);

        haReplicator()->replicateCheck($check, $rule);

        expect(haPublished())->toBeEmpty();
    });

    it('does not publish the same payload twice', function () {
        $rule = haAlertRule(AlertRuleType::PROMETHEUS);
        $replicator = haReplicator();

        $check = haSavedCheck(PrometheusCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'alerts' => [],
        ], [
            'alerts' => [haPrometheusAlert('n1')],
        ]);

        $replicator->replicateCheck($check, $rule);
        $replicator->replicateCheck($check, $rule);

        expect(haPublished())->toHaveCount(1);
    });

    it('gives every publish of a key a newer version', function () {
        $rule = haAlertRule(AlertRuleType::PROMETHEUS);
        $replicator = haReplicator();

        $firing = haSavedCheck(PrometheusCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'alerts' => [],
        ], [
            'alerts' => [haPrometheusAlert('n1')],
        ]);

        $resolving = haSavedCheck(PrometheusCheck::class, [
            'alertRuleId' => HA_RULE_ID,
            'alerts' => [haPrometheusAlert('n1')],
        ], [
            'alerts' => [haPrometheusAlert('n1', PrometheusCheck::RESOLVED)],
        ]);

        $replicator->replicateCheck($firing, $rule);
        $replicator->replicateCheck($resolving, $rule);

        $versions = collect(haPublished())->map(fn (array $publish): int => $publish['value']['version']);

        expect($versions->all())->toBe([1, 2]);
    });
});
