<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\ApiAlertStatusHistory;
use Tests\Support\TeamTestData;

describe('AlertInstance createStatusHistory', function () {
    afterEach(function () {
        if (isset($this->alertRule)) {
            ApiAlertStatusHistory::query()->where('alertRuleId', $this->alertRule->_id)->delete();
            AlertInstance::query()->where('alertRuleId', $this->alertRule->_id)->delete();
            AlertRule::query()->where('_id', $this->alertRule->_id)->delete();
        }

        if (isset($this->owner)) {
            TeamTestData::deleteUser($this->owner);
        }
    });

    it('stores a fire state in status history when an instance is firing', function () {
        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->alertRule = AlertRule::create([
            'name' => 'API Status History Test',
            'type' => AlertRuleType::API->value,
            'userId' => $this->owner->id,
        ]);

        $instance = AlertInstance::create([
            'alertRuleId' => $this->alertRule->_id,
            'alertRuleName' => $this->alertRule->name,
            'instance' => 'host-1',
            'state' => AlertInstance::FIRE,
            'description' => 'connection refused',
        ]);

        $history = $instance->createHistory();
        $instance->createStatusHistory($history);

        $statusHistory = ApiAlertStatusHistory::query()
            ->where('alertRuleId', $this->alertRule->_id)
            ->latest('createdAt')
            ->first();

        expect($statusHistory)->not->toBeNull()
            ->and((int) $statusHistory->state)->toBe(AlertInstance::FIRE)
            ->and($statusHistory->countAlerts)->toBe(1)
            ->and($statusHistory->firedInstances)->toHaveCount(1);
    });

    it('stores a resolved state in status history when all instances are resolved', function () {
        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->alertRule = AlertRule::create([
            'name' => 'API Status History Resolve Test',
            'type' => AlertRuleType::API->value,
            'userId' => $this->owner->id,
        ]);

        $instance = AlertInstance::create([
            'alertRuleId' => $this->alertRule->_id,
            'alertRuleName' => $this->alertRule->name,
            'instance' => 'host-1',
            'state' => AlertInstance::RESOLVED,
            'description' => 'recovered',
        ]);

        $history = $instance->createHistory();
        $instance->createStatusHistory($history);

        $statusHistory = ApiAlertStatusHistory::query()
            ->where('alertRuleId', $this->alertRule->_id)
            ->latest('createdAt')
            ->first();

        expect($statusHistory)->not->toBeNull()
            ->and((int) $statusHistory->state)->toBe(AlertInstance::RESOLVED);
    });
});
