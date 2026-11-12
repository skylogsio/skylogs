<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Services\AlertRuleService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\TeamTestData;

describe('API alert webhook auth', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->apiToken = 'api-auth-token-'.uniqid();
        $this->alert = AlertRule::create([
            'name' => 'ApiAuth '.uniqid(),
            'type' => AlertRuleType::API,
            'userId' => $this->owner->id,
            'apiToken' => $this->apiToken,
        ]);
    });

    afterEach(function () {
        if (isset($this->alert)) {
            AlertInstance::query()->where('alertRuleId', $this->alert->_id)->delete();
            AlertRule::query()->where('_id', $this->alert->_id)->delete();
        }

        if (isset($this->owner)) {
            TeamTestData::deleteUser($this->owner);
        }

        Cache::flush();
    });

    it('fires immediately after create even when the alert-rule cache is empty', function () {
        Cache::tags(['alertRule', AlertRuleType::API->value])->forever('alertRule:api', collect());

        $this->postJson('/api/v1/fire-alert', [
            'instance' => 'host-1',
        ], [
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->assertSuccessful()
            ->assertJson([
                'status' => true,
                'message' => 'Activated',
            ]);
    });

    it('still fires when cached alert rules have no apiToken', function () {
        $cached = app(AlertRuleService::class)->getAlertsDB(AlertRuleType::API);
        foreach ($cached as $alert) {
            unset($alert->apiToken);
        }
        Cache::tags(['alertRule', AlertRuleType::API->value])->forever('alertRule:api', $cached);

        $this->postJson('/api/v1/fire-alert', [
            'instance' => 'host-2',
        ], [
            'Authorization' => 'Bearer '.$this->apiToken,
        ])->assertSuccessful()
            ->assertJsonPath('status', true);
    });

    it('rejects an unknown api token', function () {
        $this->postJson('/api/v1/fire-alert', [
            'instance' => 'host-3',
        ], [
            'Authorization' => 'Bearer wrong-token',
        ])->assertUnauthorized();
    });
});
