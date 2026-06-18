<?php

use App\Enums\Constants;
use App\Services\TeamService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Tests\Support\TeamTestData;

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

describe('TeamService authorization', function () {
    beforeEach(function () {
        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->memberTeam = TeamTestData::createTeam($this->member, [$this->member->id]);
    });

    afterEach(function () {
        TeamTestData::deleteTeam($this->memberTeam);
        TeamTestData::deleteUser($this->manager);
        TeamTestData::deleteUser($this->member);
    });

    it('allows managers and owners to create and delete teams', function () {
        $service = app(TeamService::class);

        expect($service->canCreateTeam($this->manager))->toBeTrue()
            ->and($service->canDeleteTeam($this->manager))->toBeTrue()
            ->and($service->canUpdateTeam($this->manager, $this->memberTeam))->toBeTrue();
    });

    it('allows a team owner to update but not create or delete teams', function () {
        $service = app(TeamService::class);

        expect($service->canCreateTeam($this->member))->toBeFalse()
            ->and($service->canDeleteTeam($this->member))->toBeFalse()
            ->and($service->canUpdateTeam($this->member, $this->memberTeam))->toBeTrue();
    });
});

describe('TeamService userTeams', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->owner = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->ownedTeam = TeamTestData::createTeam($this->owner, [$this->owner->id, $this->member->id]);
    });

    afterEach(function () {
        TeamTestData::deleteTeam($this->ownedTeam);
        TeamTestData::deleteUser($this->owner);
        TeamTestData::deleteUser($this->member);
    });

    it('returns teams owned by or shared with the user', function () {
        $teams = app(TeamService::class)->userTeams($this->owner);

        expect($teams->pluck('id')->toArray())->toContain($this->ownedTeam->id);
    });

    it('returns teams where the user is listed in userIds', function () {
        $teams = app(TeamService::class)->userTeams($this->member);

        expect($teams->pluck('id')->toArray())->toContain($this->ownedTeam->id);
    });
});
