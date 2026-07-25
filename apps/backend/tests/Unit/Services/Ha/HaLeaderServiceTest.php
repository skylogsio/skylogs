<?php

use App\Services\Ha\HaLeaderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function haLeaderResponse(bool $isLeader, string $leaderId = 'node-1'): array
{
    return [
        'isLeader' => $isLeader,
        'nodeId' => 'node-1',
        'leaderId' => $leaderId,
        'leaderAddress' => "http://skylogs-back-{$leaderId}:80",
        'term' => 7,
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
            'ha.raft.url' => 'http://raft.test:8090',
            'ha.raft.retry_attempts' => 2,
            'ha.raft.retry_sleep_milliseconds' => 0,
        ]);

        Cache::flush();
    });

    it('reports leadership when the sidecar says this node leads', function () {
        Http::fake([
            'raft.test:8090/leader' => Http::response(haLeaderResponse(true)),
        ]);

        expect(haLeaderService()->isLeader())->toBeTrue()
            ->and(haLeaderService()->leaderAddress())->toBe('http://skylogs-back-node-1:80');
    });

    it('reports follower when another node leads', function () {
        Http::fake([
            'raft.test:8090/leader' => Http::response(haLeaderResponse(false, 'node-2')),
        ]);

        expect(haLeaderService()->isLeader())->toBeFalse()
            ->and(haLeaderService()->leaderAddress())->toBe('http://skylogs-back-node-2:80');
    });

    it('treats an unreachable sidecar as a follower', function () {
        Http::fake([
            'raft.test:8090/leader' => Http::failedConnection(),
        ]);

        expect(haLeaderService()->isLeader())->toBeFalse()
            ->and(haLeaderService()->leaderAddress())->toBeNull();
    });

    it('treats a failing sidecar as a follower', function () {
        Http::fake([
            'raft.test:8090/leader' => Http::response('boom', 500),
        ]);

        expect(haLeaderService()->isLeader())->toBeFalse();
    });

    it('caches the sidecar answer for the configured window', function () {
        Http::fake([
            'raft.test:8090/leader' => Http::response(haLeaderResponse(true)),
        ]);

        haLeaderService()->isLeader();
        haLeaderService()->isLeader();
        haLeaderService()->leaderAddress();

        Http::assertSentCount(1);
    });

    it('polls the sidecar again once the cached answer expires', function () {
        Http::fake([
            'raft.test:8090/leader' => Http::response(haLeaderResponse(true)),
        ]);

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
            'raft.test:8090/leader' => Http::sequence()
                ->push(haLeaderResponse(false, 'node-2'))
                ->push(haLeaderResponse(true)),
        ]);

        expect(haLeaderService()->shouldRunLeaderWork())->toBeFalse();

        Cache::flush();

        expect(haLeaderService()->shouldRunLeaderWork())->toBeTrue();
    });

    it('records the current role', function () {
        Http::fake([
            'raft.test:8090/leader' => Http::response(haLeaderResponse(false, 'node-2')),
        ]);

        haLeaderService()->isLeader();

        expect(Cache::get('ha:lastRole'))->toBe('follower');
    });

    it('logs a promotion when the role changes from follower to leader', function () {
        Log::spy();

        Http::fake([
            'raft.test:8090/leader' => Http::sequence()
                ->push(haLeaderResponse(false, 'node-2'))
                ->push(haLeaderResponse(true)),
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
