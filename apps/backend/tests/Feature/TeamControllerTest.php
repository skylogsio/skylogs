<?php

use App\Enums\Constants;
use App\Http\Controllers\V1\TeamController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\Support\TeamTestData;

describe('TeamController', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->memberTeam = TeamTestData::createTeam($this->member, [$this->member->id]);
        $this->managerTeam = TeamTestData::createTeam($this->manager, [$this->manager->id, $this->member->id]);
        $this->secondTeam = TeamTestData::createTeam($this->manager, [$this->manager->id]);
    });

    afterEach(function () {
        foreach (['memberTeam', 'managerTeam', 'secondTeam'] as $property) {
            if (isset($this->{$property})) {
                TeamTestData::deleteTeam($this->{$property});
            }
        }

        foreach (['manager', 'member'] as $property) {
            if (isset($this->{$property})) {
                TeamTestData::deleteUser($this->{$property});
            }
        }
    });

    it('updates a team while keeping the same name', function () {
        $updatedDescription = 'team-update-test-'.uniqid();

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/team/{$this->managerTeam->id}", [
                'name' => $this->managerTeam->name,
                'ownerId' => $this->managerTeam->ownerId,
                'userIds' => $this->managerTeam->userIds,
                'description' => $updatedDescription,
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', $this->managerTeam->name)
            ->assertJsonPath('data.description', $updatedDescription);
    });

    it('returns validation errors when userIds is empty', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/team/{$this->managerTeam->id}", [
                'name' => $this->managerTeam->name,
                'ownerId' => $this->managerTeam->ownerId,
                'userIds' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['userIds']);
    });

    it('returns not found when updating a missing team', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson('/api/v1/team/507f1f77bcf86cd799439011', [
                'name' => 'Missing Team',
                'ownerId' => $this->manager->id,
                'userIds' => [$this->manager->id],
            ])
            ->assertNotFound();
    });

    it('rejects duplicate team names on update', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/team/{$this->managerTeam->id}", [
                'name' => $this->secondTeam->name,
                'ownerId' => $this->managerTeam->ownerId,
                'userIds' => $this->managerTeam->userIds,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('lists teams with owner members and access flags', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/team?name='.urlencode($this->managerTeam->name))
            ->assertSuccessful();

        $team = collect($response->json('data'))->firstWhere('id', $this->managerTeam->id);

        expect($team)->not->toBeNull()
            ->and($team)->toHaveKeys(['id', 'name', 'ownerId', 'userIds', 'members', 'owner', 'canCreate', 'canEdit', 'canDelete'])
            ->and($team['canCreate'])->toBeTrue()
            ->and($team['canEdit'])->toBeTrue()
            ->and($team['canDelete'])->toBeTrue()
            ->and($team['owner'])->not->toBeNull()
            ->and($team['members'])->toContain($this->member->name, $this->manager->name);
    });

    it('allows any authenticated user to list teams with owner and members', function () {
        $response = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/team?name='.urlencode($this->managerTeam->name))
            ->assertSuccessful();

        $team = collect($response->json('data'))->firstWhere('id', $this->managerTeam->id);

        expect($team)->not->toBeNull()
            ->and($team)->toHaveKeys(['id', 'name', 'ownerId', 'userIds', 'members', 'owner'])
            ->and($team['owner'])->not->toBeNull()
            ->and($team['members'])->toContain($this->member->name);
    });

    it('exposes create edit and delete flags only for manager and admin users', function () {
        $response = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/team')
            ->assertSuccessful();

        $ownedTeam = collect($response->json('data'))->firstWhere('id', $this->memberTeam->id);
        $otherTeam = collect($response->json('data'))->firstWhere('id', $this->managerTeam->id);

        expect($ownedTeam['canCreate'])->toBeFalse()
            ->and($ownedTeam['canDelete'])->toBeFalse()
            ->and($ownedTeam['canEdit'])->toBeTrue()
            ->and($otherTeam['canEdit'])->toBeFalse()
            ->and($otherTeam['canDelete'])->toBeFalse();
    });

    it('allows a team owner to update their team', function () {
        $updatedDescription = 'member-owner-update-'.uniqid();

        $this->actingAs($this->member, 'api')
            ->putJson("/api/v1/team/{$this->memberTeam->id}", [
                'name' => $this->memberTeam->name,
                'ownerId' => $this->memberTeam->ownerId,
                'userIds' => $this->memberTeam->userIds,
                'description' => $updatedDescription,
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.description', $updatedDescription)
            ->assertJsonPath('data.canEdit', true)
            ->assertJsonPath('data.canDelete', false);
    });

    it('forbids members from updating teams they do not own', function () {
        $this->actingAs($this->member, 'api')
            ->putJson("/api/v1/team/{$this->managerTeam->id}", [
                'name' => $this->managerTeam->name,
                'ownerId' => $this->managerTeam->ownerId,
                'userIds' => $this->managerTeam->userIds,
                'description' => 'forbidden-update',
            ])
            ->assertForbidden();
    });

    it('forbids members from creating teams', function () {
        $this->actingAs($this->member, 'api')
            ->postJson('/api/v1/team', [
                'name' => 'member-created-team-'.uniqid(),
                'ownerId' => $this->member->id,
                'userIds' => [$this->member->id],
            ])
            ->assertForbidden();
    });

    it('forbids members from deleting teams', function () {
        $this->actingAs($this->member, 'api')
            ->deleteJson("/api/v1/team/{$this->managerTeam->id}")
            ->assertForbidden();
    });
});

describe('TeamController validation', function () {
    it('throws validation errors through Validator::validate', function () {
        $controller = app(TeamController::class);
        $request = Request::create('/api/v1/team', 'POST', [
            'name' => '',
            'ownerId' => '',
            'userIds' => [],
        ]);

        expect(fn () => $controller->Create($request))
            ->toThrow(ValidationException::class);
    });
});
