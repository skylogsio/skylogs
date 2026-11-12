<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use BadMethodCallException;
use Illuminate\Support\Facades\Cache;

class TeamService
{
    public function canCreateTeam(User $user): bool
    {
        return $user->isAdmin();
    }

    public function canUpdateTeam(User $user, Team $team): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->id === $team->ownerId || $user->_id === $team->ownerId;
    }

    public function canDeleteTeam(User $user): bool
    {
        return $user->isAdmin();
    }

    public function applyTeamAccess(User $user, Team $team): Team
    {
        $team->setAttribute('canCreate', $this->canCreateTeam($user));
        $team->setAttribute('canEdit', $this->canUpdateTeam($user, $team));
        $team->setAttribute('canDelete', $this->canDeleteTeam($user));

        return $team;
    }

    public function userTeams(User $user)
    {
        $tagsArray = ['team', $user->id];
        $keyName = 'team:'.$user->id;

        return Cache::tags($tagsArray)->remember($keyName, 3600, fn () => Team::query()
            ->where('ownerId', $user->id)
            ->orWhere('userIds', $user->id)
            ->get());
    }

    public function flushCache(): void
    {
        try {
            Cache::tags(['team'])->flush();
        } catch (BadMethodCallException) {
            Cache::flush();
        }
    }
}
