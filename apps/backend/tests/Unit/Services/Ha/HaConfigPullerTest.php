<?php

use App\Exceptions\Ha\LeaderUnavailableException;
use App\Services\Ha\HaConfigPuller;
use App\Services\Ha\HaConfigSyncService;
use App\Services\Ha\HaConfigVersionStore;
use App\Services\Ha\HaLeaderService;
use App\Services\Ha\LeaderConfigClient;

function haConfigPuller(bool $isLeader, ?string $address = 'http://skylogs-back-1:80'): HaConfigPuller
{
    $leader = Mockery::mock(HaLeaderService::class);
    $leader->shouldReceive('isLeader')->andReturn($isLeader);
    $leader->shouldReceive('leaderAddress')->andReturn($address);

    return new HaConfigPuller($leader, test()->client, test()->sync, test()->versions);
}

beforeEach(function () {
    config(['ha.enabled' => true, 'ha.config_sync.enabled' => true]);

    $this->client = Mockery::mock(LeaderConfigClient::class);
    $this->sync = Mockery::mock(HaConfigSyncService::class);
    $this->versions = Mockery::mock(HaConfigVersionStore::class);
});

describe('HaConfigPuller', function () {
    it('applies a newer snapshot and remembers the version it reached', function () {
        $snapshot = ['version' => 9, 'changed' => true, 'collections' => ['users' => []]];

        $this->versions->shouldReceive('lastAppliedLeaderVersion')->once()->andReturn(4);
        $this->client->shouldReceive('snapshot')->with('http://skylogs-back-1:80', 4)->once()->andReturn($snapshot);
        $this->sync->shouldReceive('apply')->with($snapshot)->once()->andReturn(['users' => ['written' => 2, 'deleted' => 0]]);
        $this->versions->shouldReceive('recordAppliedLeaderVersion')->with(9)->once();

        expect(haConfigPuller(isLeader: false)->pull())->toBe([
            'status' => 'applied',
            'version' => 9,
            'applied' => ['users' => ['written' => 2, 'deleted' => 0]],
        ]);
    });

    it('writes nothing when the leader reports the same version', function () {
        $this->versions->shouldReceive('lastAppliedLeaderVersion')->andReturn(9);
        $this->client->shouldReceive('snapshot')->andReturn(['version' => 9, 'changed' => false, 'collections' => []]);
        $this->sync->shouldNotReceive('apply');
        $this->versions->shouldNotReceive('recordAppliedLeaderVersion');

        expect(haConfigPuller(isLeader: false)->pull())->toBe(['status' => 'upToDate', 'version' => 9]);
    });

    /*
     | Recording the version before the apply finished would make the follower
     | skip the very snapshot it failed to write.
     */
    it('leaves the version behind when the apply throws', function () {
        $this->versions->shouldReceive('lastAppliedLeaderVersion')->andReturn(4);
        $this->client->shouldReceive('snapshot')->andReturn(['version' => 9, 'changed' => true, 'collections' => []]);
        $this->sync->shouldReceive('apply')->andThrow(new RuntimeException('write failed'));
        $this->versions->shouldNotReceive('recordAppliedLeaderVersion');

        expect(fn () => haConfigPuller(isLeader: false)->pull())->toThrow(RuntimeException::class);
    });

    it('does nothing on the leader, which is the source', function () {
        $this->client->shouldNotReceive('snapshot');

        expect(haConfigPuller(isLeader: true)->pull())->toBe(['status' => 'leader']);
    });

    it('reports an election in progress rather than pulling from nowhere', function () {
        $this->client->shouldNotReceive('snapshot');

        expect(fn () => haConfigPuller(isLeader: false, address: null)->pull())
            ->toThrow(LeaderUnavailableException::class);
    });

    it('stands down entirely on a single node install', function () {
        config(['ha.enabled' => false]);

        $this->client->shouldNotReceive('snapshot');

        expect(haConfigPuller(isLeader: false)->pull())->toBe(['status' => 'disabled']);
    });

    it('stands down when config sync alone is switched off', function () {
        config(['ha.config_sync.enabled' => false]);

        $this->client->shouldNotReceive('snapshot');

        expect(haConfigPuller(isLeader: false)->pull())->toBe(['status' => 'disabled']);
    });
});
