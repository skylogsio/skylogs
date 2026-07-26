<?php

use App\Enums\AlertRuleType;
use App\Services\Ha\AlertStateKey;

describe('AlertStateKey', function () {
    it('builds a key from the rule id, type and instance', function () {
        $key = AlertStateKey::make('6512ab000000000000000001', AlertRuleType::PROMETHEUS, '9f8a1c');

        expect($key->toString())->toBe('alert:6512ab000000000000000001:prometheus:9f8a1c');
    });

    it('falls back to the single slot instance segment', function () {
        $key = AlertStateKey::make('6512ab000000000000000001', AlertRuleType::HEALTH);

        expect($key->toString())->toBe('alert:6512ab000000000000000001:health:_');
    });

    it('keeps the type segment free of underscores so victoria logs stays parseable', function () {
        $key = AlertStateKey::make('6512ab000000000000000001', AlertRuleType::VICTORIA_LOGS);

        expect($key->type)->toBe('victoriaLogs');
    });

    it('keeps grafana and pmm in separate namespaces', function () {
        $grafana = AlertStateKey::make('6512ab000000000000000001', AlertRuleType::GRAFANA, 'abc');
        $pmm = AlertStateKey::make('6512ab000000000000000001', AlertRuleType::PMM, 'abc');

        expect($grafana->toString())->not->toBe($pmm->toString());
    });

    it('round trips through parse', function () {
        $key = AlertStateKey::parse('alert:6512ab000000000000000001:prometheus:9f8a1c');

        expect($key->alertRuleId)->toBe('6512ab000000000000000001')
            ->and($key->type)->toBe('prometheus')
            ->and($key->instanceId)->toBe('9f8a1c');
    });

    it('parses a legacy grafana instance id that contains colons', function () {
        $key = AlertStateKey::parse('alert:6512ab000000000000000001:grafana:legacy:9f8a1c');

        expect($key->instanceId)->toBe('legacy:9f8a1c');
    });

    it('rejects a malformed key', function () {
        AlertStateKey::parse('alert:6512ab000000000000000001:prometheus');
    })->throws(InvalidArgumentException::class);

    it('hashes prometheus labels irrespective of their order', function () {
        expect(AlertStateKey::prometheusInstanceId(['instance' => 'n1', 'alertname' => 'Down']))
            ->toBe(AlertStateKey::prometheusInstanceId(['alertname' => 'Down', 'instance' => 'n1']));
    });

    it('uses the grafana fingerprint as the instance id', function () {
        expect(AlertStateKey::grafanaInstanceId(['fingerprint' => 'deadbeef']))->toBe('deadbeef');
    });

    it('hashes the client supplied api instance', function () {
        expect(AlertStateKey::apiInstanceId('srv-1'))->toBe(sha1('srv-1'));
    });

    it('exposes the prefix of every key belonging to one rule', function () {
        expect(AlertStateKey::prefixFor('6512ab000000000000000001'))->toBe('alert:6512ab000000000000000001:');
    });
});
