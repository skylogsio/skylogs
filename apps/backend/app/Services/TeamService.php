<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class TeamService
{
    public function userTeams(User $user)
    {
        $tagsArray = ['team', $user->id];
        $keyName = 'team:'.$user->id;

        return Cache::tags($tagsArray)->remember($keyName, 3600, fn () => Team::where('ownerId', $user->id)->orWhereIn('userIds', [$user->id])->get());
    }

    public function flushCache(): void
    {
        Cache::tags(['team'])->flush();
    }
}
