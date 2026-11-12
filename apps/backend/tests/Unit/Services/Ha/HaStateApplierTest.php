<?php

use App\Models\AlertRule;
use App\Services\Ha\HaStateApplier;
use App\Services\Ha\Writers\StateWriterFactory;
use Tests\Support\Ha\InMemoryHaStateVersionStore;

const APPLIER_KEY = 'alert:6512ab000000000000000001:prometheus:9f8a1c';

function haApplierValue(array $overrides = []): array
{
    return [
        'key' => APPLIER_KEY,
        'version' => 5,
        'nodeId' => 'node-1',
        'timestamp' => 1785000000,
        'alertRuleId' => '6512ab000000000000000001',
        'alertRuleName' => 'node down',
        'type' => 'prometheus',
        'instance' => ['labels' => ['alertname' => 'NodeDown']],
        'state' => AlertRule::CRITICAL,
        'rule' => ['state' => AlertRule::CRITICAL, 'fireCount' => 1],
        'extra' => [],
        ...$overrides,
    ];
}

function haApplier(): HaStateApplier
{
    return new HaStateApplier(new StateWriterFactory, test()->versions);
}

beforeEach(function () {
    config(['cache.default' => 'array', 'ha.enabled' => true, 'ha.node_id' => 'node-2']);

    $this->versions = new InMemoryHaStateVersionStore;
});

describe('HaStateApplier version gate', function () {
    it('rejects a version the node has already passed', function () {
        $this->versions->seed(APPLIER_KEY, 9, 'node-1');

        expect(haApplier()->apply(APPLIER_KEY, haApplierValue(['version' => 5])))
            ->toBe(['applied' => false, 'reason' => HaStateApplier::REASON_STALE]);
    });

    it('rejects a replay of the version it already holds', function () {
        $this->versions->seed(APPLIER_KEY, 5, 'node-1');

        expect(haApplier()->apply(APPLIER_KEY, haApplierValue(['version' => 5])))
            ->toBe(['applied' => false, 'reason' => HaStateApplier::REASON_STALE]);
    });

    it('breaks a tie on the same version against the lower node id', function () {
        $this->versions->seed(APPLIER_KEY, 5, 'node-2');

        expect(haApplier()->apply(APPLIER_KEY, haApplierValue(['version' => 5, 'nodeId' => 'node-1'])))
            ->toBe(['applied' => false, 'reason' => HaStateApplier::REASON_STALE]);
    });

    it('leaves the recorded version untouched when it rejects', function () {
        $this->versions->seed(APPLIER_KEY, 9, 'node-1');

        haApplier()->apply(APPLIER_KEY, haApplierValue(['version' => 5]));

        expect($this->versions->current(APPLIER_KEY))->toBe(9);
    });
});

describe('HaStateApplier malformed input', function () {
    it('rejects a key that is not an alert slot', function () {
        expect(haApplier()->apply('nonsense', haApplierValue()))
            ->toBe(['applied' => false, 'reason' => HaStateApplier::REASON_MALFORMED]);
    });

    it('rejects a malformed tombstone rather than deleting something arbitrary', function () {
        expect(haApplier()->apply('alert:6512ab000000000000000001', null))
            ->toBe(['applied' => false, 'reason' => HaStateApplier::REASON_MALFORMED]);
    });
});
