<?php

use App\Services\Ha\HaLeaderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The shape of GET /leader: the leader backend URL is returned directly in the
 * address field.
 */
function haStatusResponse(bool $isLeader, string $leaderAddress = 'http://nginx_back-1:80'): array
{
    return [
        'node_id' => 'node-1',
        'is_leader' => $isLeader,
        'address' => $leaderAddress,
        'state' => $isLeader ? 'Leader' : 'Follower',
    ];
}

function haLeaderService(): HaLeaderService
{
    return app(HaLeaderService::class);
}

describe('HaLeaderService', function () {
    beforeEach(function () {
        config([
            'cache.default' => 'array',
            'ha.enabled' => true,
            'ha.node_id' => 'node-1',
            'ha.leader_cache_seconds' => 2,
            'ha.peers' => [
                'node-1' => 'http://nginx_back-1:80',
                '172.28.7.11' => 'http://nginx_back-1:80',
                '172.28.7.12:7000' => 'http://nginx_back-2:80/',
            ],
            'ha.raft.url' => 'http://raft.test:8000',
            'ha.raft.retry_attempts' => 2,
            'ha.raft.retry_sleep_milliseconds' => 0,
        ]);

        Cache::flush();
    });

    it('reports leadership when the sidecar says this node leads', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(true))]);

        expect(haLeaderService()->isLeader())->toBeTrue()
            ->and(haLeaderService()->leaderRaftAddress())->toBe('http://nginx_back-1:80');
    });

    it('reports follower when another node leads', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(false, 'http://nginx_back-2:80'))]);

        expect(haLeaderService()->isLeader())->toBeFalse();
    });

    it('uses the leader backend url returned by the sidecar', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(false, 'http://nginx_back-2:80'))]);

        expect(haLeaderService()->leaderAddress())->toBe('http://nginx_back-2:80');
    });

    it('reports no leader url while an election is running', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(false, ''))]);

        expect(haLeaderService()->leaderAddress())->toBeNull();
    });

    it('resolves its own url by node id while it leads', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(true))]);

        expect(haLeaderService()->leaderAddress())->toBe('http://nginx_back-1:80');
    });

    it('treats an unreachable sidecar as a follower', function () {
        Http::fake(['raft.test:8000/leader' => Http::failedConnection()]);

        expect(haLeaderService()->isLeader())->toBeFalse()
            ->and(haLeaderService()->leaderAddress())->toBeNull();
    });

    it('treats a failing sidecar as a follower', function () {
        Http::fake(['raft.test:8000/leader' => Http::response('boom', 500)]);

        expect(haLeaderService()->isLeader())->toBeFalse();
    });

    it('caches the sidecar answer for the configured window', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(true))]);

        haLeaderService()->isLeader();
        haLeaderService()->isLeader();
        haLeaderService()->leaderAddress();

        Http::assertSentCount(1);
    });

    it('polls the sidecar again once the cached answer expires', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(true))]);

        haLeaderService()->isLeader();

        $this->travel(3)->seconds();

        haLeaderService()->isLeader();

        Http::assertSentCount(2);
    });

    it('never calls the sidecar while ha is disabled', function () {
        config(['ha.enabled' => false]);
        Http::fake();

        expect(haLeaderService()->isLeader())->toBeFalse();

        Http::assertNothingSent();
    });

    it('runs leader work everywhere while ha is disabled', function () {
        config(['ha.enabled' => false]);

        expect(haLeaderService()->shouldRunLeaderWork())->toBeTrue();
    });

    it('runs leader work only on the leader while ha is enabled', function () {
        Http::fake([
            'raft.test:8000/leader' => Http::sequence()
                ->push(haStatusResponse(false, 'http://nginx_back-2:80'))
                ->push(haStatusResponse(true)),
        ]);

        expect(haLeaderService()->shouldRunLeaderWork())->toBeFalse();

        Cache::flush();

        expect(haLeaderService()->shouldRunLeaderWork())->toBeTrue();
    });

    it('records the current role', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(false, 'http://nginx_back-2:80'))]);

        haLeaderService()->isLeader();

        expect(Cache::get('ha:lastRole'))->toBe('follower');
    });

    it('logs a promotion when the role changes from follower to leader', function () {
        Log::spy();

        Http::fake([
            'raft.test:8000/leader' => Http::sequence()
                ->push(haStatusResponse(false, 'http://nginx_back-2:80'))
                ->push(haStatusResponse(true)),
        ]);

        haLeaderService()->isLeader();

        $this->travel(3)->seconds();

        haLeaderService()->isLeader();

        expect(Cache::get('ha:lastRole'))->toBe('leader');

        Log::shouldHaveReceived('info')->once()->withArgs(
            fn (string $message, array $context) => $message === 'HA role transition.'
                && $context['from'] === 'follower'
                && $context['to'] === 'leader'
        );
    });

    it('returns the configured node id', function () {
        expect(haLeaderService()->nodeId())->toBe('node-1');
    });
});
