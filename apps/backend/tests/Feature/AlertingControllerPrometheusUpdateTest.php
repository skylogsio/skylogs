<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertRule;
use Tests\Support\TeamTestData;

describe('AlertingController Prometheus update', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
    });

    afterEach(function () {
        TeamTestData::deleteUser($this->owner);
    });

    function prometheusUpdatePayload(AlertRule $alertRule, array $overrides = []): array
    {
        return array_merge([
            'name' => $alertRule->name,
            'description' => '',
            'showAcknowledgeBtn' => false,
            'queryType' => AlertRule::DYNAMIC_QUERY_TYPE,
            'dataSourceIds' => [],
            'dataSourceAlertName' => 'HighMemory',
            'endpointIds' => [],
            'userIds' => [],
            'teamIds' => [],
            'tags' => [],
        ], $overrides);
    }

    it('updates the name without clearing state', function (AlertRuleType $type) {
        $alertRule = AlertRule::create([
            'name' => 'Query rule original',
            'type' => $type->value,
            'userId' => $this->owner->id,
            'state' => AlertRule::CRITICAL,
            'description' => '',
            'showAcknowledgeBtn' => false,
            'queryType' => AlertRule::DYNAMIC_QUERY_TYPE,
            'dataSourceIds' => [],
            'dataSourceAlertName' => 'HighMemory',
            'queryText' => '',
            'extraField' => [],
            'tags' => [],
        ]);

        try {
            $this->actingAs($this->owner, 'api')
                ->putJson('/api/v1/alert-rule/'.$alertRule->_id, prometheusUpdatePayload($alertRule, [
                    'name' => 'Query rule renamed',
                ]))
                ->assertSuccessful()
                ->assertJson(['status' => true]);

            $alertRule->refresh();

            expect($alertRule->name)->toBe('Query rule renamed')
                ->and($alertRule->state)->toBe(AlertRule::CRITICAL)
                ->and($alertRule->dataSourceAlertName)->toBe('HighMemory');
        } finally {
            AlertRule::query()->where('_id', $alertRule->_id)->delete();
        }
    })->with([
        'prometheus' => AlertRuleType::PROMETHEUS,
        'grafana' => AlertRuleType::GRAFANA,
        'pmm' => AlertRuleType::PMM,
    ]);

    it('clears state when the monitored alert name changes', function () {
        $alertRule = AlertRule::create([
            'name' => 'Query rule original',
            'type' => AlertRuleType::PROMETHEUS->value,
            'userId' => $this->owner->id,
            'state' => AlertRule::CRITICAL,
            'description' => '',
            'showAcknowledgeBtn' => false,
            'queryType' => AlertRule::DYNAMIC_QUERY_TYPE,
            'dataSourceIds' => [],
            'dataSourceAlertName' => 'HighMemory',
            'queryText' => '',
            'extraField' => [],
            'tags' => [],
        ]);

        try {
            $this->actingAs($this->owner, 'api')
                ->putJson('/api/v1/alert-rule/'.$alertRule->_id, prometheusUpdatePayload($alertRule, [
                    'dataSourceAlertName' => 'HighCpu',
                ]))
                ->assertSuccessful()
                ->assertJson(['status' => true]);

            $alertRule->refresh();

            expect($alertRule->name)->toBe('Query rule original')
                ->and($alertRule->dataSourceAlertName)->toBe('HighCpu')
                ->and($alertRule->state)->toBeNull();
        } finally {
            AlertRule::query()->where('_id', $alertRule->_id)->delete();
        }
    });
});
