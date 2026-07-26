<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertRule;
use App\Models\HaStateVersion;
use App\Models\PrometheusCheck;
use App\Models\PrometheusHistory;
use App\Services\Ha\AlertStateKey;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TeamTestData;

/**
 * Stubs are appended to the factory rather than replacing what is already
 * registered, and the first one that matches answers. A test that redefines the
 * log part way through therefore has to start from a clean factory.
 *
 * The sidecar hands back the raw JSON text it stored, so every slot is encoded
 * here exactly as GET /get would return it.
 */
function haFakeSidecar(bool $isLeader, array $data): void
{
    Http::swap(new Factory(app(Dispatcher::class)));

    Http::fake([
        'raft:8000/status' => Http::response([
            'node_id' => 'node-2',
            'is_leader' => $isLeader,
            'leader' => $isLeader ? '172.28.7.12:7000' : '172.28.7.11:7000',
            'state' => $isLeader ? 'Leader' : 'Follower',
        ]),
        'raft:8000/get' => Http::response(array_map(json_encode(...), $data)),
    ]);
}

function haReconcileSlot(AlertRule $alertRule, string $instanceId, int $version, string $state, array $entry): array
{
    return [
        'key' => 'alert:'.$alertRule->_id.':prometheus:'.$instanceId,
        'version' => $version,
        'nodeId' => 'node-1',
        'timestamp' => 1785000000,
        'alertRuleId' => (string) $alertRule->_id,
        'alertRuleName' => $alertRule->name,
        'type' => 'prometheus',
        'instance' => ['labels' => $entry['labels']],
        'state' => $state,
        'firedAt' => 1785000000,
        'resolvedAt' => null,
        'rule' => ['state' => $state, 'fireCount' => 1, 'notifyAt' => null, 'acknowledgedBy' => null],
        'extra' => ['entry' => $entry],
    ];
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

    $this->alertRule = AlertRule::create([
        'name' => 'HA Reconcile Alert',
        'type' => AlertRuleType::PROMETHEUS->value,
        'userId' => $this->owner->id,
    ]);

    $this->entry = [
        'dataSourceId' => 'ds-1',
        'labels' => ['alertname' => 'NodeDown', 'instance' => '10.0.0.4:9100', 'severity' => 'critical'],
        'annotations' => ['summary' => 'node is down'],
        'skylogsStatus' => PrometheusCheck::FIRE,
    ];

    $this->instanceId = AlertStateKey::prometheusInstanceId($this->entry['labels']);
    $this->key = 'alert:'.$this->alertRule->_id.':prometheus:'.$this->instanceId;

    Queue::fake();
});

afterEach(function () {
    $alertRuleId = $this->alertRule->_id;

    PrometheusCheck::query()->where('alertRuleId', $alertRuleId)->delete();
    PrometheusHistory::query()->where('alertRuleId', $alertRuleId)->delete();
    HaStateVersion::query()->where('key', 'like', AlertStateKey::prefixFor((string) $alertRuleId).'%')->delete();
    AlertRule::query()->where('_id', $alertRuleId)->delete();

    TeamTestData::deleteUser($this->owner);
});

describe('ha:reconcile on a follower', function () {
    it('pulls the log and applies a slot it never received', function () {
        haFakeSidecar(isLeader: false, data: [
            $this->key => haReconcileSlot($this->alertRule, $this->instanceId, 4, AlertRule::CRITICAL, $this->entry),
        ]);

        $this->artisan('ha:reconcile')->assertSuccessful();

        $check = PrometheusCheck::where('alertRuleId', $this->alertRule->_id)->first();

        expect($check)->not->toBeNull()
            ->and($check->alerts)->toHaveCount(1)
            ->and(AlertRule::find($this->alertRule->_id)->state)->toBe(AlertRule::CRITICAL)
            ->and((int) HaStateVersion::where('key', $this->key)->value('version'))->toBe(4);
    });

    it('is idempotent, so a second pass over an unchanged log changes nothing', function () {
        haFakeSidecar(isLeader: false, data: [
            $this->key => haReconcileSlot($this->alertRule, $this->instanceId, 4, AlertRule::CRITICAL, $this->entry),
        ]);

        $this->artisan('ha:reconcile')->assertSuccessful();
        $this->artisan('ha:reconcile')->assertSuccessful();

        expect(PrometheusHistory::where('alertRuleId', $this->alertRule->_id)->count())->toBe(1);
    });

    it('drops a local slot the log no longer carries', function () {
        haFakeSidecar(isLeader: false, data: [
            $this->key => haReconcileSlot($this->alertRule, $this->instanceId, 4, AlertRule::CRITICAL, $this->entry),
        ]);

        $this->artisan('ha:reconcile')->assertSuccessful();

        haFakeSidecar(isLeader: false, data: []);

        $this->artisan('ha:reconcile')->assertSuccessful();

        expect(PrometheusCheck::where('alertRuleId', $this->alertRule->_id)->first()->alerts)->toBe([])
            ->and(HaStateVersion::where('key', $this->key)->count())->toBe(0);
    });

    it('never publishes back into the log', function () {
        haFakeSidecar(isLeader: false, data: [
            $this->key => haReconcileSlot($this->alertRule, $this->instanceId, 4, AlertRule::CRITICAL, $this->entry),
        ]);

        $this->artisan('ha:reconcile')->assertSuccessful();

        Queue::assertNothingPushed();
    });
});

describe('ha:reconcile on a leader', function () {
    it('inherits the log counters so a promoted node keeps publishing forward', function () {
        haFakeSidecar(isLeader: true, data: [
            $this->key => haReconcileSlot($this->alertRule, $this->instanceId, 11, AlertRule::CRITICAL, $this->entry),
        ]);

        $this->artisan('ha:reconcile')->assertSuccessful();

        expect((int) HaStateVersion::where('key', $this->key)->value('version'))->toBe(11);
    });

    it('does not apply the log to its own documents', function () {
        haFakeSidecar(isLeader: true, data: [
            $this->key => haReconcileSlot($this->alertRule, $this->instanceId, 11, AlertRule::CRITICAL, $this->entry),
        ]);

        $this->artisan('ha:reconcile')->assertSuccessful();

        expect(PrometheusCheck::where('alertRuleId', $this->alertRule->_id)->count())->toBe(0);
    });
});

describe('ha:reconcile without a sidecar', function () {
    it('succeeds so that a node whose sidecar is still electing can still boot', function () {
        Http::fake(['raft:8000/*' => Http::failedConnection()]);

        $this->artisan('ha:reconcile')->assertSuccessful();
    });

    it('does nothing at all while ha is disabled', function () {
        config(['ha.enabled' => false]);

        Http::fake();

        $this->artisan('ha:reconcile')->assertSuccessful();

        Http::assertNothingSent();
    });
});
