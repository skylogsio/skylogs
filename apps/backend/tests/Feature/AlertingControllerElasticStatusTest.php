<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertRule;
use App\Models\ElasticCheck;
use App\Models\ElasticHistory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TeamTestData;

describe('AlertingController Elastic statusLabel consistency', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Queue::fake();

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->tag = 'elastic-status-'.uniqid();

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

        $this->alertRule = AlertRule::create([
            'name' => 'Elastic Status Test '.$this->tag,
            'type' => AlertRuleType::ELASTIC->value,
            'userId' => $this->owner->id,
            'state' => AlertRule::CRITICAL,
            'tags' => [$this->tag],
            'dataviewTitle' => 'logs-*',
            'queryString' => 'level:error',
            'minutes' => 5,
            'countDocument' => 10,
            'conditionType' => ElasticCheck::CONDITION_TYPE_GREATER_OR_EQUAL,
        ]);

        ElasticCheck::create([
            'alertRuleId' => $this->alertRule->_id,
            'dataviewTitle' => 'logs-*',
            'queryString' => 'level:error',
            'minutes' => 5,
            'countDocument' => 10,
            'currentCountDocument' => 15,
            'state' => ElasticCheck::FIRE,
        ]);
    });

    afterEach(function () {
        ElasticHistory::query()->where('alertRuleId', $this->alertRule->_id)->delete();
        ElasticCheck::query()->where('alertRuleId', $this->alertRule->_id)->delete();
        AlertRule::query()->where('_id', $this->alertRule->_id)->delete();
        TeamTestData::deleteUser($this->owner);
        if (! empty($this->createdAdmin) && $this->admin) {
            TeamTestData::deleteUser($this->admin);
        }
    });

    it('returns critical statusLabel on index when ElasticCheck is firing', function () {
        $item = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'tags' => $this->tag,
                'perPage' => 50,
            ]))
            ->assertSuccessful()
            ->json('data.0');

        expect($item['statusLabel'])->toBe(AlertRule::CRITICAL)
            ->and($item['state'])->toBe(AlertRule::CRITICAL);
    });

    it('returns critical statusLabel on show when ElasticCheck is firing', function () {
        $item = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/'.$this->alertRule->_id)
            ->assertSuccessful()
            ->json();

        expect($item['statusLabel'])->toBe(AlertRule::CRITICAL)
            ->and($item['state'])->toBe(AlertRule::CRITICAL);
    });

    it('includes the firing elastic rule when filtering by critical status', function () {
        $ids = collect($this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'tags' => $this->tag,
                'status' => AlertRule::CRITICAL,
                'perPage' => 50,
            ]))
            ->assertSuccessful()
            ->json('data'))
            ->pluck('id')
            ->all();

        expect($ids)->toContain((string) $this->alertRule->_id);
    });

    it('rejects manual resolve for elastic alert rules', function () {
        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule/resolve/'.$this->alertRule->_id)
            ->assertUnprocessable();

        $this->alertRule->refresh();
        $check = ElasticCheck::query()->where('alertRuleId', $this->alertRule->_id)->first();

        expect($this->alertRule->state)->toBe(AlertRule::CRITICAL)
            ->and((int) $check->state)->toBe(ElasticCheck::FIRE);
    });

    it('does not return resolved elastic checks from the triggered endpoint', function () {
        ElasticCheck::query()->where('alertRuleId', $this->alertRule->_id)->update([
            'state' => ElasticCheck::RESOLVED,
        ]);

        $response = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/triggered/'.$this->alertRule->_id)
            ->assertSuccessful()
            ->json();

        expect($response)->toBe([]);
    });
});
