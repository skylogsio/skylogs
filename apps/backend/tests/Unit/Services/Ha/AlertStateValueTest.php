<?php

use App\Models\AlertRule;
use App\Services\Ha\AlertStateKey;
use App\Services\Ha\AlertStateValue;

function haStateValuePayload(array $overrides = []): array
{
    return [
        'key' => 'alert:6512ab000000000000000001:prometheus:9f8a1c',
        'version' => 42,
        'nodeId' => 'node-1',
        'timestamp' => 1785000000,
        'alertRuleId' => '6512ab000000000000000001',
        'alertRuleName' => 'node down',
        'type' => 'prometheus',
        'instance' => ['labels' => ['alertname' => 'NodeDown']],
        'state' => AlertRule::CRITICAL,
        'firedAt' => 1785000000,
        'resolvedAt' => null,
        'rule' => ['state' => AlertRule::CRITICAL, 'fireCount' => 3, 'notifyAt' => 1785000000, 'acknowledgedBy' => null],
        'extra' => ['annotations' => ['summary' => 'down'], 'dataSourceId' => 'ds-1'],
        ...$overrides,
    ];
}

function haStateValue(array $overrides = []): AlertStateValue
{
    $payload = haStateValuePayload($overrides);

    return AlertStateValue::fromArray(AlertStateKey::parse($payload['key']), $payload);
}

describe('AlertStateValue', function () {
    it('parses the published payload', function () {
        $value = haStateValue();

        expect($value->version)->toBe(42)
            ->and($value->nodeId)->toBe('node-1')
            ->and($value->alertRuleId)->toBe('6512ab000000000000000001')
            ->and($value->alertRuleName)->toBe('node down')
            ->and($value->type)->toBe('prometheus')
            ->and($value->instance)->toBe(['labels' => ['alertname' => 'NodeDown']])
            ->and($value->state)->toBe(AlertRule::CRITICAL);
    });

    it('round trips back to the published shape', function () {
        expect(haStateValue()->toArray())->toBe(haStateValuePayload());
    });

    it('exposes the leader aggregate through accessors', function () {
        $value = haStateValue();

        expect($value->ruleState())->toBe(AlertRule::CRITICAL)
            ->and($value->fireCount())->toBe(3)
            ->and($value->notifyAt())->toBe(1785000000)
            ->and($value->acknowledgedBy())->toBeNull();
    });

    it('reports the resolve timestamp as the moment the slot changed', function () {
        $value = haStateValue(['state' => AlertRule::RESOlVED, 'firedAt' => null, 'resolvedAt' => 1785000900]);

        expect($value->isResolved())->toBeTrue()
            ->and($value->isFiring())->toBeFalse()
            ->and($value->changedAt())->toBe(1785000900);
    });

    it('falls back to the publish timestamp when neither edge is stamped', function () {
        $value = haStateValue(['firedAt' => null, 'resolvedAt' => null]);

        expect($value->changedAt())->toBe(1785000000);
    });

    it('treats a warning as firing', function () {
        expect(haStateValue(['state' => AlertRule::WARNING])->isFiring())->toBeTrue();
    });

    it('tolerates a payload that is missing everything but the key', function () {
        $key = AlertStateKey::parse('alert:6512ab000000000000000001:health:_');
        $value = AlertStateValue::fromArray($key, []);

        expect($value->version)->toBe(0)
            ->and($value->alertRuleId)->toBe('6512ab000000000000000001')
            ->and($value->type)->toBe('health')
            ->and($value->state)->toBe(AlertRule::UNKNOWN)
            ->and($value->rule)->toBe([])
            ->and($value->extra)->toBe([]);
    });

    it('reads per type extras with a default', function () {
        $value = haStateValue();

        expect($value->extra('dataSourceId'))->toBe('ds-1')
            ->and($value->extra('missing', 'fallback'))->toBe('fallback')
            ->and($value->extraArray('annotations'))->toBe(['summary' => 'down'])
            ->and($value->extraArray('dataSourceId'))->toBe([]);
    });
});
