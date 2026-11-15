<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertRule;
use App\Models\DataSource\DataSource;
use App\Models\ElasticCheck;
use Tests\Support\TeamTestData;

describe('AlertingController Elastic update', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->dataSource = DataSource::withoutEvents(fn () => DataSource::create([
            'name' => 'Elastic Update Source',
            'type' => 'elastic',
            'url' => 'https://elastic.example.com',
            'username' => 'elastic-user',
            'password' => 'elastic-pass',
            'userId' => $this->owner->id,
        ]));

        $this->alertRule = AlertRule::create([
            'name' => 'Elastic Update Original',
            'type' => AlertRuleType::ELASTIC->value,
            'userId' => $this->owner->id,
            'state' => AlertRule::CRITICAL,
            'dataSourceId' => $this->dataSource->_id,
            'dataviewName' => 'logs',
            'dataviewTitle' => 'logs-*',
            'queryString' => 'level:error',
            'minutes' => 5,
            'countDocument' => 10,
            'conditionType' => ElasticCheck::CONDITION_TYPE_GREATER_OR_EQUAL,
        ]);

        $this->check = ElasticCheck::create([
            'alertRuleId' => $this->alertRule->_id,
            'dataviewName' => 'logs',
            'dataviewTitle' => 'logs-*',
            'queryString' => 'level:error',
            'minutes' => 5,
            'countDocument' => 10,
            'currentCountDocument' => 15,
            'state' => ElasticCheck::FIRE,
        ]);
    });

    afterEach(function () {
        ElasticCheck::query()->where('alertRuleId', $this->alertRule->_id)->delete();
        AlertRule::query()->where('_id', $this->alertRule->_id)->delete();
        DataSource::query()->where('_id', $this->dataSource->_id)->delete();
        TeamTestData::deleteUser($this->owner);
    });

    function elasticUpdatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => test()->alertRule->name,
            'dataSourceId' => (string) test()->dataSource->_id,
            'dataviewName' => 'logs',
            'dataviewTitle' => 'logs-*',
            'queryString' => 'level:error',
            'minutes' => 5,
            'countDocument' => 10,
            'conditionType' => ElasticCheck::CONDITION_TYPE_GREATER_OR_EQUAL,
            'endpointIds' => [],
            'userIds' => [],
            'teamIds' => [],
            'tags' => [],
        ], $overrides);
    }

    it('keeps the elastic check and critical state when only the name changes', function () {
        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/alert-rule/'.$this->alertRule->_id, elasticUpdatePayload([
                'name' => 'Elastic Update Renamed',
            ]))
            ->assertSuccessful()
            ->assertJson(['status' => true]);

        $this->alertRule->refresh();
        $check = ElasticCheck::query()->where('alertRuleId', $this->alertRule->_id)->first();

        expect($this->alertRule->name)->toBe('Elastic Update Renamed')
            ->and($this->alertRule->state)->toBe(AlertRule::CRITICAL)
            ->and($check)->not->toBeNull()
            ->and((string) $check->_id)->toBe((string) $this->check->_id)
            ->and((int) $check->state)->toBe(ElasticCheck::FIRE);
    });

    it('resets state and deletes the elastic check when query config changes', function () {
        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/alert-rule/'.$this->alertRule->_id, elasticUpdatePayload([
                'queryString' => 'level:warn',
            ]))
            ->assertSuccessful()
            ->assertJson(['status' => true]);

        $this->alertRule->refresh();

        expect($this->alertRule->queryString)->toBe('level:warn')
            ->and($this->alertRule->state)->toBe(AlertRule::RESOlVED)
            ->and(ElasticCheck::query()->where('alertRuleId', $this->alertRule->_id)->exists())->toBeFalse();
    });
});
