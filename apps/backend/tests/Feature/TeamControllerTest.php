<?php

use App\Enums\Constants;
use App\Http\Controllers\V1\TeamController;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

function teamManagerUser(): User
{
    $user = User::query()
        ->get()
        ->first(fn (User $user) => $user->hasRole(Constants::ROLE_OWNER) || $user->hasRole(Constants::ROLE_MANAGER));

    if ($user === null) {
        test()->markTestSkipped('Owner or manager user required.');
    }

    return $user;
}

describe('TeamController', function () {
    it('updates a team while keeping the same name', function () {
        $user = teamManagerUser();
        $team = Team::query()->first();

        if ($team === null) {
            $this->markTestSkipped('MongoDB team data required.');
        }

        $originalDescription = $team->description ?? '';
        $updatedDescription = 'team-update-test-'.uniqid();

        $response = $this->actingAs($user, 'api')
            ->putJson("/api/v1/team/{$team->id}", [
                'name' => $team->name,
                'ownerId' => $team->ownerId,
                'userIds' => $team->userIds ?? [],
                'description' => $updatedDescription,
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', $team->name)
            ->assertJsonPath('data.description', $updatedDescription);

        $team->update(['description' => $originalDescription]);
    });

    it('returns validation errors when userIds is empty', function () {
        $user = teamManagerUser();
        $team = Team::query()->first();

        if ($team === null) {
            $this->markTestSkipped('MongoDB team data required.');
        }

        $this->actingAs($user, 'api')
            ->putJson("/api/v1/team/{$team->id}", [
                'name' => $team->name,
                'ownerId' => $team->ownerId,
                'userIds' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['userIds']);
    });

    it('returns not found when updating a missing team', function () {
        $user = teamManagerUser();

        $this->actingAs($user, 'api')
            ->putJson('/api/v1/team/507f1f77bcf86cd799439011', [
                'name' => 'Missing Team',
                'ownerId' => $user->id,
                'userIds' => [$user->id],
            ])
            ->assertNotFound();
    });

    it('rejects duplicate team names on update', function () {
        $user = teamManagerUser();
        $teams = Team::query()->take(2)->get();

        if ($teams->count() < 2) {
            $this->markTestSkipped('At least two teams required.');
        }

        $this->actingAs($user, 'api')
            ->putJson("/api/v1/team/{$teams[0]->id}", [
                'name' => $teams[1]->name,
                'ownerId' => $teams[0]->ownerId,
                'userIds' => $teams[0]->userIds ?? [$user->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('lists teams without eager loading a members relationship', function () {
        $user = teamManagerUser();

        $this->actingAs($user, 'api')
            ->getJson('/api/v1/team')
            ->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'ownerId', 'userIds', 'members'],
                ],
            ]);
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
