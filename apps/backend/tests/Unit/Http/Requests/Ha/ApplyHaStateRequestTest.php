<?php

use App\Http\Requests\Ha\ApplyHaStateRequest;
use App\Models\AlertRule;
use Illuminate\Support\Facades\Validator;

function haApplyPayload(array $overrides = []): array
{
    return [
        'key' => 'alert:6512ab000000000000000001:prometheus:9f8a1c',
        'value' => [
            'version' => 42,
            'nodeId' => 'node-1',
            'state' => AlertRule::CRITICAL,
        ],
        ...$overrides,
    ];
}

function haApplyErrors(array $payload): array
{
    return Validator::make($payload, (new ApplyHaStateRequest)->rules())->errors()->keys();
}

describe('ApplyHaStateRequest', function () {
    it('accepts a well formed payload', function () {
        expect(haApplyErrors(haApplyPayload()))->toBe([]);
    });

    it('accepts a tombstone, which carries a present but null value', function () {
        expect(haApplyErrors(haApplyPayload(['value' => null])))->toBe([]);
    });

    it('requires the value field to be sent, so an omission cannot be mistaken for a tombstone', function () {
        $payload = haApplyPayload();
        unset($payload['value']);

        expect(haApplyErrors($payload))->toContain('value');
    });

    it('rejects a key that is not an alert slot', function () {
        expect(haApplyErrors(haApplyPayload(['key' => 'config:6512ab000000000000000001'])))->toContain('key');
    });

    it('rejects a key whose alert rule id is not an object id', function () {
        expect(haApplyErrors(haApplyPayload(['key' => 'alert:nope:prometheus:9f8a1c'])))->toContain('key');
    });

    it('rejects a key with no instance segment', function () {
        expect(haApplyErrors(haApplyPayload(['key' => 'alert:6512ab000000000000000001:prometheus:'])))->toContain('key');
    });

    it('accepts a legacy grafana instance id containing colons', function () {
        expect(haApplyErrors(haApplyPayload(['key' => 'alert:6512ab000000000000000001:grafana:legacy:9f8a1c'])))->toBe([]);
    });

    it('rejects a value with no version, which could not be ordered', function () {
        expect(haApplyErrors(haApplyPayload(['value' => ['nodeId' => 'node-1', 'state' => AlertRule::CRITICAL]])))
            ->toContain('value.version');
    });

    it('rejects a non numeric version', function () {
        expect(haApplyErrors(haApplyPayload(['value' => ['version' => 'first', 'nodeId' => 'node-1', 'state' => AlertRule::CRITICAL]])))
            ->toContain('value.version');
    });

    it('rejects a value with no publishing node, which could not break a version tie', function () {
        expect(haApplyErrors(haApplyPayload(['value' => ['version' => 42, 'state' => AlertRule::CRITICAL]])))
            ->toContain('value.nodeId');
    });

    it('rejects a value with no state', function () {
        expect(haApplyErrors(haApplyPayload(['value' => ['version' => 42, 'nodeId' => 'node-1']])))
            ->toContain('value.state');
    });
});
