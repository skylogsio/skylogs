<?php

use App\Enums\Constants;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use Tests\Support\TeamTestData;

describe('AlertRule organization access', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->outsider = TeamTestData::createUser(Constants::ROLE_MEMBER);

        $this->publicAlert = AlertRule::create([
            'name' => 'OrgAccess Public '.uniqid(),
            'type' => 'api',
            'userId' => $this->owner->id,
            'apiToken' => 'secret-public-token',
            'endpointIds' => ['endpoint-1'],
            'userIds' => ['other-user'],
            'isPrivate' => false,
        ]);

        $this->privateAlert = AlertRule::create([
            'name' => 'OrgAccess Private '.uniqid(),
            'type' => 'api',
            'userId' => $this->owner->id,
            'apiToken' => 'secret-private-token',
            'isPrivate' => true,
        ]);

        $this->ownedAlert = AlertRule::create([
            'name' => 'OrgAccess Owned '.uniqid(),
            'type' => 'api',
            'userId' => $this->outsider->id,
            'apiToken' => 'secret-owned-token',
        ]);
    });

    afterEach(function () {
        foreach (['publicAlert', 'privateAlert', 'ownedAlert'] as $property) {
            if (isset($this->{$property})) {
                ApiAlertHistory::query()->where('alertRuleId', $this->{$property}->_id)->delete();
                AlertRule::query()->where('_id', $this->{$property}->_id)->delete();
            }
        }

        foreach (['owner', 'outsider'] as $property) {
            if (isset($this->{$property})) {
                TeamTestData::deleteUser($this->{$property});
            }
        }
    });

    it('returns only assigned alerts by default', function () {
        $response = $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'alertname' => 'OrgAccess',
                'perPage' => 100,
            ]))
            ->assertSuccessful()
            ->json('data');

        $ids = collect($response)->pluck('id')->all();

        expect($ids)->toContain($this->ownedAlert->id)
            ->and($ids)->not->toContain($this->publicAlert->id)
            ->and($ids)->not->toContain($this->privateAlert->id);
    });

    it('returns organization-visible alerts when scope is organization', function () {
        $response = $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'scope' => 'organization',
                'alertname' => 'OrgAccess',
                'perPage' => 100,
            ]))
            ->assertSuccessful()
            ->json('data');

        $ids = collect($response)->pluck('id')->all();

        expect($ids)->toContain($this->ownedAlert->id)
            ->and($ids)->toContain($this->publicAlert->id)
            ->and($ids)->not->toContain($this->privateAlert->id);
    });

    it('strips sensitive fields for readonly organization alerts in the list', function () {
        $response = $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'scope' => 'organization',
                'alertname' => 'OrgAccess Public',
                'perPage' => 100,
            ]))
            ->assertSuccessful()
            ->json('data');

        $public = collect($response)->firstWhere('id', $this->publicAlert->id);

        expect($public)->not->toBeNull()
            ->and($public['accessLevel'])->toBe('readonly')
            ->and($public['hasActionAccess'])->toBeFalse()
            ->and($public)->not->toHaveKey('apiToken')
            ->and($public)->not->toHaveKey('endpointIds')
            ->and($public)->not->toHaveKey('userIds');
    });

    it('allows readonly users to view public alert details without sensitive fields', function () {
        $response = $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/alert-rule/'.$this->publicAlert->id)
            ->assertSuccessful()
            ->json();

        expect($response['accessLevel'])->toBe('readonly')
            ->and($response['hasActionAccess'])->toBeFalse()
            ->and($response)->not->toHaveKey('apiToken')
            ->and($response)->not->toHaveKey('endpointIds')
            ->and($response)->not->toHaveKey('rules');
    });

    it('forbids readonly users from viewing private alerts', function () {
        $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/alert-rule/'.$this->privateAlert->id)
            ->assertForbidden();
    });

    it('allows readonly users to read history for public alerts', function () {
        ApiAlertHistory::create([
            'alertRuleId' => $this->publicAlert->_id,
            'state' => 'fire',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->publicAlert->id)
            ->assertSuccessful();
    });

    it('forbids readonly users from reading history for private alerts', function () {
        $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->privateAlert->id)
            ->assertForbidden();
    });

    it('forbids readonly users from pinning public alerts', function () {
        $this->actingAs($this->outsider, 'api')
            ->postJson('/api/v1/alert-rule/pin/'.$this->publicAlert->id)
            ->assertForbidden();
    });

    it('does not expose api tokens to assigned member users', function () {
        $this->publicAlert->push('userIds', $this->outsider->id, true);
        $this->publicAlert->save();

        $response = $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/alert-rule/'.$this->publicAlert->id)
            ->assertSuccessful()
            ->json();

        expect($response['accessLevel'])->toBe('manage')
            ->and($response['hasActionAccess'])->toBeFalse()
            ->and($response)->not->toHaveKey('apiToken');
    });

    it('exposes api tokens to the alert owner', function () {
        $response = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/'.$this->publicAlert->id)
            ->assertSuccessful()
            ->json();

        expect($response['accessLevel'])->toBe('manage')
            ->and($response['hasActionAccess'])->toBeTrue()
            ->and($response)->toHaveKey('apiToken');
    });
});
