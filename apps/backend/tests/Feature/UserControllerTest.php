<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Support\TeamTestData;

describe('UserController manager access', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->otherManager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->createdUsers = [];
        $this->alertRuleIds = [];
        $this->endpointIds = [];
        $this->createdAdmin = false;

        $this->admin = User::query()->where('username', 'admin')->first();

        if ($this->admin === null) {
            TeamTestData::ensureRoles();
            $this->admin = User::create([
                'username' => 'admin',
                'name' => 'admin',
                'password' => Hash::make('password'),
            ]);
            $this->admin->assignRole(Constants::ROLE_OWNER->value);
            $this->createdAdmin = true;
        }
    });

    afterEach(function () {
        foreach ($this->endpointIds as $id) {
            Endpoint::query()->where('_id', $id)->delete();
        }

        foreach ($this->alertRuleIds as $id) {
            AlertRule::query()->where('_id', $id)->delete();
        }

        foreach ($this->createdUsers as $user) {
            TeamTestData::deleteUser($user);
        }

        foreach (['member', 'otherManager', 'manager', 'owner'] as $property) {
            if (isset($this->{$property})) {
                TeamTestData::deleteUser($this->{$property});
            }
        }

        if ($this->createdAdmin && isset($this->admin)) {
            TeamTestData::deleteUser($this->admin);
        }

        Cache::flush();
    });

    it('lets a manager update a member without changing the username or role', function () {
        $updatedName = 'Member Updated '.uniqid();

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/user/{$this->member->id}", [
                'username' => $this->member->username,
                'name' => $updatedName,
                'role' => Constants::ROLE_MEMBER->value,
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', $updatedName)
            ->assertJsonPath('data.username', $this->member->username);

        $member = $this->member->fresh();

        expect($member->name)->toBe($updatedName)
            ->and($member->hasRole(Constants::ROLE_MEMBER))->toBeTrue()
            ->and($member->hasRole(Constants::ROLE_MANAGER))->toBeFalse();
    });

    it('lets an owner update a user while keeping the same username', function () {
        $updatedName = 'Owner Edit '.uniqid();

        $this->actingAs($this->owner, 'api')
            ->putJson("/api/v1/user/{$this->member->id}", [
                'username' => $this->member->username,
                'name' => $updatedName,
                'role' => Constants::ROLE_MANAGER->value,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.name', $updatedName);

        $member = $this->member->fresh();

        expect($member->hasRole(Constants::ROLE_MANAGER))->toBeTrue()
            ->and($member->hasRole(Constants::ROLE_MEMBER))->toBeFalse();
    });

    it('forbids a manager from updating another manager or an owner', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/user/{$this->otherManager->id}", [
                'username' => $this->otherManager->username,
                'name' => 'Should Not Change',
                'role' => Constants::ROLE_MEMBER->value,
            ])
            ->assertForbidden();

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/user/{$this->owner->id}", [
                'username' => $this->owner->username,
                'name' => 'Should Not Change',
                'role' => Constants::ROLE_MEMBER->value,
            ])
            ->assertForbidden();

        expect($this->otherManager->fresh()->hasRole(Constants::ROLE_MANAGER))->toBeTrue()
            ->and($this->owner->fresh()->hasRole(Constants::ROLE_OWNER))->toBeTrue();
    });

    it('lets a manager create a member and rejects a privileged role', function () {
        $username = 'mgr-created-'.uniqid();

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/user', [
                'username' => $username,
                'name' => 'Created By Manager',
                'password' => 'password',
                'confirmPassword' => 'password',
                'role' => Constants::ROLE_MEMBER->value,
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $created = User::query()->where('username', $username)->first();
        $this->createdUsers[] = $created;

        expect($created)->not->toBeNull()
            ->and($created->hasRole(Constants::ROLE_MEMBER))->toBeTrue();

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/user', [
                'username' => 'mgr-owner-'.uniqid(),
                'name' => 'Should Fail',
                'password' => 'password',
                'confirmPassword' => 'password',
                'role' => Constants::ROLE_OWNER->value,
            ])
            ->assertForbidden();

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/user', [
                'username' => 'mgr-manager-'.uniqid(),
                'name' => 'Should Fail',
                'password' => 'password',
                'confirmPassword' => 'password',
                'role' => Constants::ROLE_MANAGER->value,
            ])
            ->assertForbidden();
    });

    it('lets a manager change a member password and not another manager password', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/user/pass/{$this->member->id}", [
                'password' => 'new-password',
                'confirmPassword' => 'new-password',
            ])
            ->assertSuccessful();

        expect(Hash::check('new-password', $this->member->fresh()->password))->toBeTrue();

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/user/pass/{$this->otherManager->id}", [
                'password' => 'new-password',
                'confirmPassword' => 'new-password',
            ])
            ->assertForbidden();
    });

    it('removes a deleted member from alert rules and moves their endpoints to admin', function () {
        $endpoint = Endpoint::create([
            'name' => 'member-endpoint-'.uniqid(),
            'type' => EndpointType::EMAIL->value,
            'userId' => $this->member->id,
            'value' => 'member@example.com',
        ]);
        $this->endpointIds[] = $endpoint->_id;

        $rule = AlertRule::create([
            'name' => 'member-rule-'.uniqid(),
            'type' => AlertRuleType::API,
            'userId' => $this->member->id,
            'userIds' => [$this->member->id, $this->manager->id],
        ]);
        $this->alertRuleIds[] = $rule->_id;

        $this->actingAs($this->manager, 'api')
            ->deleteJson("/api/v1/user/{$this->member->id}")
            ->assertSuccessful();

        $rule->refresh();
        $endpoint->refresh();

        $remainingUserIds = array_map(strval(...), $rule->userIds ?? []);

        expect(User::query()->where('_id', $this->member->id)->exists())->toBeFalse()
            ->and($remainingUserIds)->not->toContain((string) $this->member->id)
            ->and($remainingUserIds)->toContain((string) $this->manager->id)
            ->and((string) $rule->userId)->toBe((string) $this->admin->id)
            ->and((string) $endpoint->userId)->toBe((string) $this->admin->id);

        $this->member = null;
    });
});
