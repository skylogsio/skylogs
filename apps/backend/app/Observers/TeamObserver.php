<?php

namespace App\Observers;

use App\Models\Team;
use App\Services\TeamService;

class TeamObserver
{
    public function created(Team $team): void
    {
        app(TeamService::class)->flushCache();
    }

    public function updated(Team $team): void
    {
        app(TeamService::class)->flushCache();
    }

    public function deleted(Team $team): void
    {
        app(TeamService::class)->flushCache();
    }

    public function restored(Team $team): void
    {
        app(TeamService::class)->flushCache();
    }

    public function forceDeleted(Team $team): void
    {
        app(TeamService::class)->flushCache();
    }
}
