<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Models\AlertRule;
use App\Models\DataSource\DataSource;
use App\Models\Endpoint;
use App\Models\Team;
use App\Services\AlertRuleService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\TeamTestData;

describe('manager system workflow', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->otherManager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->teamIds = [];
        $this->endpointIds = [];
        $this->alertRuleIds = [];
        $this->dataSourceIds = [];
    });

    afterEach(function () {
        foreach ($this->dataSourceIds as $id) {
            $dataSource = DataSource::query()->where('_id', $id)->first();

            if ($dataSource) {
                $dataSource->delete();
            }
        }

        foreach ($this->alertRuleIds as $id) {
            $rule = AlertRule::query()->where('_id', $id)->first();

            if ($rule) {
                app(AlertRuleService::class)->delete($rule);
            }
        }

        foreach ($this->endpointIds as $id) {
            Endpoint::query()->where('_id', $id)->delete();
        }

        foreach ($this->teamIds as $id) {
            Team::query()->where('_id', $id)->delete();
        }

        foreach (['member', 'otherManager', 'manager'] as $property) {
            if (isset($this->{$property})) {
                TeamTestData::deleteUser($this->{$property});
            }
        }

        Cache::flush();
    });

    it('updates a member and rejects an update of another manager', function () {
        $updatedName = 'Member '.uniqid();

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/user/{$this->member->id}", [
                'username' => $this->member->username,
                'name' => $updatedName,
                'role' => Constants::ROLE_MEMBER->value,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.name', $updatedName);

        expect($this->member->fresh()->hasRole(Constants::ROLE_MEMBER))->toBeTrue();

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/user/{$this->otherManager->id}", [
                'username' => $this->otherManager->username,
                'name' => 'Manager should stay',
                'role' => Constants::ROLE_MANAGER->value,
            ])
            ->assertForbidden();

        expect($this->otherManager->fresh()->name)->not->toBe('Manager should stay')
            ->and($this->otherManager->fresh()->hasRole(Constants::ROLE_MANAGER))->toBeTrue();
    });

    it('lists, creates, and updates teams', function () {
        $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/team')
            ->assertSuccessful();

        $teamName = 'manager-team-'.uniqid();

        $created = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/team', [
                'name' => $teamName,
                'ownerId' => $this->manager->id,
                'userIds' => [$this->manager->id, $this->member->id],
                'description' => 'created by manager',
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.canEdit', true);

        $teamId = $created->json('data.id') ?? $created->json('data._id');
        $this->teamIds[] = $teamId;

        $updatedDescription = 'updated by manager';

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/team/{$teamId}", [
                'name' => $teamName,
                'ownerId' => $this->member->id,
                'userIds' => [$this->member->id, $this->manager->id],
                'description' => $updatedDescription,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.description', $updatedDescription)
            ->assertJsonPath('data.ownerId', $this->member->id);
    });

    it('creates and edits an endpoint and changes its owner', function () {
        $created = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'Discord '.uniqid(),
                'type' => EndpointType::DISCORD->value,
                'value' => 'https://discord.example/'.uniqid(),
                'isPublic' => false,
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.type', EndpointType::DISCORD->value);

        $endpointId = $created->json('data.id') ?? $created->json('data._id');
        $this->endpointIds[] = $endpointId;

        $updatedName = 'Discord updated '.uniqid();
        $updatedValue = 'https://discord.example/updated-'.uniqid();

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/endpoint/{$endpointId}", [
                'name' => $updatedName,
                'type' => EndpointType::DISCORD->value,
                'value' => $updatedValue,
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', $updatedName)
            ->assertJsonPath('data.value', $updatedValue);

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/endpoint/changeOwner/{$endpointId}", [
                'userId' => $this->member->id,
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        expect((string) Endpoint::query()->where('_id', $endpointId)->first()->userId)
            ->toBe((string) $this->member->id);
    });

    it('creates a datasource', function () {
        $name = 'Manager DS '.uniqid();

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/data-source', [
                'name' => $name,
                'type' => 'prometheus',
                'url' => 'https://manager-'.uniqid().'.example.com',
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $dataSource = DataSource::query()->where('name', $name)->first();
        expect($dataSource)->not->toBeNull();
        $this->dataSourceIds[] = $dataSource->_id;

        $healthRules = AlertRule::query()
            ->where('type', AlertRuleType::HEALTH)
            ->where('target.dataSourceId', (string) $dataSource->_id)
            ->get();

        expect($healthRules)->toHaveCount(1)
            ->and((string) $healthRules->first()->userId)->toBe((string) $this->manager->id);

        $this->alertRuleIds = $healthRules->pluck('_id')->all();
    });

    it('edits an alert rule, assigns an endpoint, and assigns a user', function () {
        $endpoint = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'Notify '.uniqid(),
                'type' => EndpointType::DISCORD->value,
                'value' => 'https://discord.example/notify-'.uniqid(),
            ])
            ->assertSuccessful()
            ->json('data');

        $endpointId = $endpoint['id'] ?? $endpoint['_id'];
        $this->endpointIds[] = $endpointId;

        $team = TeamTestData::createTeam($this->manager, [$this->manager->id, $this->member->id]);
        $this->teamIds[] = $team->id;

        $ruleName = 'Manager rule '.uniqid();

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/alert-rule', [
                'name' => $ruleName,
                'type' => AlertRuleType::API->value,
                'description' => 'created by manager',
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $rule = AlertRule::query()->where('name', $ruleName)->first();
        expect($rule)->not->toBeNull();
        $this->alertRuleIds[] = $rule->_id;

        $updatedName = $ruleName.' edited';

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/alert-rule/{$rule->id}", [
                'name' => $updatedName,
                'description' => 'edited by manager',
                'endpointIds' => [$endpointId],
                'userIds' => [$this->member->id],
                'teamIds' => [$team->id],
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $rule->refresh();

        expect($rule->name)->toBe($updatedName)
            ->and($rule->description)->toBe('edited by manager')
            ->and(array_map(strval(...), $rule->endpointIds ?? []))->toContain((string) $endpointId)
            ->and(array_map(strval(...), $rule->userIds ?? []))->toContain((string) $this->member->id)
            ->and(array_map(strval(...), $rule->teamIds ?? []))->toContain((string) $team->id);

        $secondEndpoint = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'Notify two '.uniqid(),
                'type' => EndpointType::TEAMS->value,
                'value' => 'https://teams.example/'.uniqid(),
            ])
            ->assertSuccessful()
            ->json('data');

        $secondEndpointId = $secondEndpoint['id'] ?? $secondEndpoint['_id'];
        $this->endpointIds[] = $secondEndpointId;

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/alert-rule-notify/{$rule->id}", [
                'endpointIds' => [$secondEndpointId],
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/alert-rule-user/{$rule->id}", [
                'userIds' => [$this->otherManager->id],
                'teamIds' => [$team->id],
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $rule->refresh();

        expect(array_map(strval(...), $rule->endpointIds ?? []))->toContain((string) $secondEndpointId)
            ->and(array_map(strval(...), $rule->userIds ?? []))->toContain((string) $this->otherManager->id)
            ->and(array_map(strval(...), $rule->teamIds ?? []))->toContain((string) $team->id);
    });
});
