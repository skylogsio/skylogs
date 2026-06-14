<?php

use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

describe('TeamService cache', function () {
    it('does not throw when flushing team cache', function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        app(TeamService::class)->flushCache();

        expect(true)->toBeTrue();
    });

    it('falls back to full cache flush when tags are unsupported', function () {
        $store = Mockery::mock(Repository::class);
        $store->shouldReceive('tags')->once()->with(['team'])->andThrow(new BadMethodCallException('This cache store does not support tagging.'));
        $store->shouldReceive('flush')->once();

        Cache::swap($store);

        app(TeamService::class)->flushCache();
    });
});

describe('TeamService userTeams', function () {
    it('returns teams owned by or shared with the user', function () {
        $team = Team::query()
            ->get()
            ->first(function (Team $team) {
                return User::query()->where('_id', $team->ownerId)->exists();
            });

        if ($team === null) {
            $this->markTestSkipped('MongoDB team with a valid owner required.');
        }

        $owner = User::query()->where('_id', $team->ownerId)->firstOrFail();

        config(['cache.default' => 'array']);
        Cache::flush();

        $teams = app(TeamService::class)->userTeams($owner);

        expect($teams->pluck('id')->toArray())->toContain($team->id);
    });

    it('returns teams where the user is listed in userIds', function () {
        $team = Team::query()
            ->get()
            ->first(function (Team $team) {
                return ! empty($team->userIds)
                    && User::query()->where('_id', $team->userIds[0])->exists();
            });

        if ($team === null) {
            $this->markTestSkipped('MongoDB team with a valid member required.');
        }

        $member = User::query()->where('_id', $team->userIds[0])->firstOrFail();

        config(['cache.default' => 'array']);
        Cache::flush();

        $teams = app(TeamService::class)->userTeams($member);

        expect($teams->pluck('id')->toArray())->toContain($team->id);
    });
});
