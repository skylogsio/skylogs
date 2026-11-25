<?php

use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\User;
use App\Services\AlertRuleService;
use App\Services\EndpointService;
use Tests\Support\TeamTestData;

function createCleanupEndpoint(User $owner): Endpoint
{
    return Endpoint::create([
        'userId' => $owner->id,
        'name' => 'Cleanup '.uniqid(),
        'type' => EndpointType::TELEGRAM->value,
        'chatId' => '-100'.uniqid(),
        'isPublic' => false,
        'accessUserIds' => [],
        'accessTeamIds' => [],
    ]);
}

describe('removing deleted endpoints from alert rules', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        app(EndpointService::class)->flushCache();
        app(AlertRuleService::class)->flushCache();

        $this->owner = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->other = TeamTestData::createUser(Constants::ROLE_MEMBER);

        $this->removedEndpoint = createCleanupEndpoint($this->member);
        $this->keptEndpoint = createCleanupEndpoint($this->other);

        $removedId = (string) $this->removedEndpoint->id;
        $keptId = (string) $this->keptEndpoint->id;

        $this->alert = AlertRule::create([
            'name' => 'Endpoint cleanup '.uniqid(),
            'type' => 'api',
            'userId' => $this->owner->id,
            'userIds' => [$this->member->id, $this->other->id],
            'teamIds' => [],
            'endpointIds' => [$removedId, $keptId],
            'rules' => [[
                'id' => 'rule-'.uniqid(),
                'name' => 'Notify',
                'type' => 'notification',
                'filters' => [],
                'endpointIds' => [$removedId, $keptId],
            ]],
            'isPrivate' => true,
        ]);

        $this->untouchedAlert = AlertRule::create([
            'name' => 'Endpoint cleanup untouched '.uniqid(),
            'type' => 'api',
            'userId' => $this->owner->id,
            'userIds' => [$this->other->id],
            'teamIds' => [],
            'endpointIds' => [$keptId],
            'rules' => [[
                'id' => 'rule-'.uniqid(),
                'name' => 'Keep',
                'type' => 'notification',
                'filters' => [],
                'endpointIds' => [$keptId],
            ]],
            'isPrivate' => true,
        ]);
    });

    afterEach(function () {
        Endpoint::query()->whereIn('_id', [
            $this->removedEndpoint->id,
            $this->keptEndpoint->id,
        ])->delete();

        AlertRule::query()->whereIn('_id', [
            $this->alert->id,
            $this->untouchedAlert->id,
        ])->delete();

        foreach (['owner', 'member', 'other'] as $property) {
            TeamTestData::deleteUser($this->{$property});
        }
    });

    it('removes a deleted endpoint from alert rules and behavior rules', function () {
        $removedId = (string) $this->removedEndpoint->id;
        $keptId = (string) $this->keptEndpoint->id;

        $cached = app(AlertRuleService::class)->getAlerts()
            ->first(fn (AlertRule $alert) => (string) $alert->id === (string) $this->alert->id);

        expect(array_map(strval(...), $cached->endpointIds))->toContain($removedId);

        $this->removedEndpoint->delete();

        $this->alert->refresh();
        $this->untouchedAlert->refresh();

        $endpointIds = array_map(strval(...), $this->alert->endpointIds ?? []);
        $ruleEndpointIds = array_map(strval(...), $this->alert->rules[0]['endpointIds'] ?? []);
        $untouchedEndpointIds = array_map(strval(...), $this->untouchedAlert->endpointIds ?? []);
        $untouchedRuleEndpointIds = array_map(strval(...), $this->untouchedAlert->rules[0]['endpointIds'] ?? []);

        expect($endpointIds)->not->toContain($removedId)
            ->and($endpointIds)->toContain($keptId)
            ->and($ruleEndpointIds)->not->toContain($removedId)
            ->and($ruleEndpointIds)->toContain($keptId)
            ->and($untouchedEndpointIds)->toBe([$keptId])
            ->and($untouchedRuleEndpointIds)->toBe([$keptId]);

        $reloaded = app(AlertRuleService::class)->getAlerts()
            ->first(fn (AlertRule $alert) => (string) $alert->id === (string) $this->alert->id);

        expect(array_map(strval(...), $reloaded->endpointIds ?? []))->not->toContain($removedId);
    });

    it('removes a non-owner and their endpoints from the alert in one save', function () {
        $this->actingAs($this->owner, 'api');

        app(AlertRuleService::class)->deleteForUser($this->member, $this->alert->fresh());

        $this->alert->refresh();

        $removedId = (string) $this->removedEndpoint->id;
        $keptId = (string) $this->keptEndpoint->id;
        $userIds = array_map(strval(...), $this->alert->userIds ?? []);
        $endpointIds = array_map(strval(...), $this->alert->endpointIds ?? []);
        $ruleEndpointIds = array_map(strval(...), $this->alert->rules[0]['endpointIds'] ?? []);

        expect($userIds)->not->toContain((string) $this->member->id)
            ->and($userIds)->toContain((string) $this->other->id)
            ->and($endpointIds)->not->toContain($removedId)
            ->and($endpointIds)->toContain($keptId)
            ->and($ruleEndpointIds)->not->toContain($removedId)
            ->and($ruleEndpointIds)->toContain($keptId)
            ->and(AlertRule::query()->where('_id', $this->alert->id)->exists())->toBeTrue();
    });
});
