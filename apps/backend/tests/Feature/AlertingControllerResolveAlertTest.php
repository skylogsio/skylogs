<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TeamTestData;

describe('AlertingController ResolveAlert', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Queue::fake();

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->createdAdmin = false;
        $this->admin = User::query()->where('username', 'admin')->first();

        if ($this->admin === null) {
            TeamTestData::ensureRoles();
            $this->admin = User::create([
                'username' => 'admin',
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]);
            $this->admin->assignRole(Constants::ROLE_OWNER->value);
            $this->createdAdmin = true;
        }

        $this->apiAlert = AlertRule::create([
            'name' => 'Resolve API '.uniqid(),
            'type' => AlertRuleType::API->value,
            'userId' => $this->owner->id,
            'state' => AlertRule::CRITICAL,
        ]);

        AlertInstance::create([
            'alertRuleId' => $this->apiAlert->_id,
            'alertRuleName' => $this->apiAlert->name,
            'instance' => 'host-1',
            'state' => AlertInstance::FIRE,
        ]);

        $this->elasticAlert = AlertRule::create([
            'name' => 'Resolve Elastic '.uniqid(),
            'type' => AlertRuleType::ELASTIC->value,
            'userId' => $this->owner->id,
            'state' => AlertRule::CRITICAL,
        ]);
    });

    afterEach(function () {
        AlertInstance::query()->where('alertRuleId', $this->apiAlert->_id)->delete();
        AlertRule::query()->whereIn('_id', [$this->apiAlert->_id, $this->elasticAlert->_id])->delete();
        TeamTestData::deleteUser($this->owner);

        if ($this->createdAdmin && $this->admin) {
            TeamTestData::deleteUser($this->admin);
        }
    });

    it('resolves firing instances for api alert rules', function () {
        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule/resolve/'.$this->apiAlert->_id)
            ->assertSuccessful()
            ->assertJson(['status' => true]);

        $instance = AlertInstance::query()
            ->where('alertRuleId', $this->apiAlert->_id)
            ->first();

        expect((int) $instance->state)->toBe(AlertInstance::RESOLVED);
    });

    it('rejects manual resolve for non-api alert rules', function () {
        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule/resolve/'.$this->elasticAlert->_id)
            ->assertUnprocessable();

        $this->elasticAlert->refresh();

        expect($this->elasticAlert->state)->toBe(AlertRule::CRITICAL);
    });
});
