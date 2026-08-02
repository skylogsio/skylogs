<?php

use App\Services\Ha\HaLeaderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shape of GET /leader: Raft advertise address plus the leader's Raft node id.
 * Backend URLs are resolved from HA_PEER_URLS using leaderNode.
 */
function haStatusResponse(bool $isLeader, string $leaderNode = 'node1', string $raftAddress = '192.168.56.11:7000'): array
{
    return [
        'leader' => $isLeader,
        'leaderNode' => $leaderNode,
        'address' => $raftAddress,
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
            'ha.node_id' => 'node1',
            'ha.leader_cache_seconds' => 2,
            'ha.peers' => [
                'node1' => 'http://172.28.7.11:8083',
                'node2' => 'http://172.28.7.12:8083/',
                'node3' => 'http://172.28.7.13:8083',
            ],
            'ha.raft.url' => 'http://raft.test:8000',
            'ha.raft.retry_attempts' => 2,
            'ha.raft.retry_sleep_milliseconds' => 0,
        ]);

        Cache::flush();
    });

    it('reports leadership when the sidecar says this node leads', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(true, 'node1'))]);

        expect(haLeaderService()->isLeader())->toBeTrue()
            ->and(haLeaderService()->leaderNode())->toBe('node1')
            ->and(haLeaderService()->leaderRaftAddress())->toBe('192.168.56.11:7000');
    });

    it('reports follower when another node leads', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(false, 'node2', '192.168.56.12:7000'))]);

        expect(haLeaderService()->isLeader())->toBeFalse();
    });

    it('resolves the leader backend url from leaderNode and HA_PEER_URLS', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(false, 'node2', '192.168.56.12:7000'))]);

        expect(haLeaderService()->leaderAddress())->toBe('http://172.28.7.12:8083');
    });

    it('reports no leader url while an election is running', function () {
        Http::fake(['raft.test:8000/leader' => Http::response([
            'leader' => false,
            'leaderNode' => '',
            'address' => '',
        ])]);

        expect(haLeaderService()->leaderAddress())->toBeNull()
            ->and(haLeaderService()->leaderNode())->toBeNull();
    });

    it('resolves its own url by node id while it leads', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(true, 'node1'))]);

        expect(haLeaderService()->leaderAddress())->toBe('http://172.28.7.11:8083');
    });

    it('falls back to HA_NODE_ID when leading without leaderNode', function () {
        Http::fake(['raft.test:8000/leader' => Http::response([
            'leader' => true,
            'address' => '192.168.56.11:7000',
        ])]);

        expect(haLeaderService()->leaderAddress())->toBe('http://172.28.7.11:8083');
    });

    it('returns null when leaderNode is missing from HA_PEER_URLS', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(false, 'node99', '10.0.0.99:7000'))]);

        expect(haLeaderService()->leaderAddress())->toBeNull();
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
                ->push(haStatusResponse(false, 'node2', '192.168.56.12:7000'))
                ->push(haStatusResponse(true, 'node1')),
        ]);

        expect(haLeaderService()->shouldRunLeaderWork())->toBeFalse();

        Cache::flush();

        expect(haLeaderService()->shouldRunLeaderWork())->toBeTrue();
    });

    it('records the current role', function () {
        Http::fake(['raft.test:8000/leader' => Http::response(haStatusResponse(false, 'node2', '192.168.56.12:7000'))]);

        haLeaderService()->isLeader();

        expect(Cache::get('ha:lastRole'))->toBe('follower');
    });

    it('logs a promotion when the role changes from follower to leader', function () {
        Log::spy();

        Http::fake([
            'raft.test:8000/leader' => Http::sequence()
                ->push(haStatusResponse(false, 'node2', '192.168.56.12:7000'))
                ->push(haStatusResponse(true, 'node1')),
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
        expect(haLeaderService()->nodeId())->toBe('node1');
    });
});
