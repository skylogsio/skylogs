<?php

namespace Tests\Support;

use App\Enums\Constants;
use App\Models\Auth\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TeamTestData
{
    public static function ensureRoles(): void
    {
        foreach ([Constants::ROLE_OWNER, Constants::ROLE_MANAGER, Constants::ROLE_MEMBER] as $role) {
            Role::firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'api',
            ]);
        }
    }

    public static function createUser(Constants $role): User
    {
        self::ensureRoles();

        $user = User::create([
            'name' => 'Test '.$role->value,
            'username' => 'test-'.$role->value.'-'.uniqid(),
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($role->value);

        return $user->fresh();
    }

    /**
     * @param  list<string>  $memberIds
     */
    public static function createTeam(User $owner, array $memberIds = [], ?string $name = null): Team
    {
        if ($memberIds === []) {
            $memberIds = [$owner->id];
        }

        return Team::create([
            'name' => $name ?? 'test-team-'.uniqid(),
            'ownerId' => $owner->id,
            'userIds' => $memberIds,
            'description' => 'test description',
        ]);
    }

    public static function deleteTeam(Team $team): void
    {
        Team::query()->where('_id', $team->id)->delete();
    }

    public static function deleteUser(User $user): void
    {
        $user->roles()->detach();
        User::query()->where('_id', $user->id)->delete();
    }
}
