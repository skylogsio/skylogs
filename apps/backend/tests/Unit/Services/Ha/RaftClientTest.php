<?php

use App\Exceptions\Ha\RaftUnavailableException;
use App\Services\Ha\RaftClient;
use Illuminate\Support\Facades\Http;

describe('RaftClient', function () {
    beforeEach(function () {
        config([
            'ha.raft.url' => 'http://raft.test:8000/',
            'ha.raft.connect_timeout' => 0.5,
            'ha.raft.timeout' => ['status' => 1, 'set' => 3, 'get' => 3],
            'ha.raft.retry_attempts' => 2,
            'ha.raft.retry_sleep_milliseconds' => 0,
        ]);
    });

    it('reads the cluster status of the local node', function () {
        Http::fake([
            'raft.test:8000/leader' => Http::response([
                'leader' => true,
                'leaderNode' => 'node1',
                'address' => '192.168.56.11:7000',
            ]),
        ]);

        expect(app(RaftClient::class)->status())->toBe([
            'isLeader' => true,
            'nodeId' => '',
            'leaderNode' => 'node1',
            'leaderRaftAddress' => '192.168.56.11:7000',
            'state' => null,
        ]);
    });

    it('reads a follower without treating it as a failure', function () {
        Http::fake([
            'raft.test:8000/leader' => Http::response([
                'leader' => false,
                'leaderNode' => 'node1',
                'address' => '192.168.56.11:7000',
            ]),
        ]);

        expect(app(RaftClient::class)->status())->toBe([
            'isLeader' => false,
            'nodeId' => '',
            'leaderNode' => 'node1',
            'leaderRaftAddress' => '192.168.56.11:7000',
            'state' => null,
        ]);
    });

    it('reports no leader node while an election is running', function () {
        Http::fake([
            'raft.test:8000/leader' => Http::response([
                'leader' => false,
                'leaderNode' => '',
                'address' => '',
            ]),
        ]);

        $status = app(RaftClient::class)->status();

        expect($status['leaderNode'])->toBeNull()
            ->and($status['leaderRaftAddress'])->toBeNull();
    });

    it('sets a single key as a key and value pair', function () {
        Http::fake(['raft.test:8000/set' => Http::response(['status' => 'ok'])]);

        app(RaftClient::class)->set('alert:6512ab:prometheus:9f8a1c', ['state' => 'critical']);

        Http::assertSent(fn ($request) => $request->url() === 'http://raft.test:8000/set'
            && $request->data() === [
                'key' => 'alert:6512ab:prometheus:9f8a1c',
                'value' => ['state' => 'critical'],
            ]);
    });

    it('sets a tombstone as a null value', function () {
        Http::fake(['raft.test:8000/set' => Http::response(['status' => 'ok'])]);

        app(RaftClient::class)->set('alert:6512ab:prometheus:9f8a1c', null);

        Http::assertSent(fn ($request) => $request->data() === [
            'key' => 'alert:6512ab:prometheus:9f8a1c',
            'value' => null,
        ]);
    });

    /*
     | The sidecar keeps the raw JSON text of whatever was sent, so a slot only
     | becomes an array again on this side of the wire.
     */
    it('decodes the stored json text of every key', function () {
        Http::fake([
            'raft.test:8000/get' => Http::response([
                'alert:6512ab:prometheus:9f8a1c' => '{"state":"critical","version":4}',
                'alert:6512ab:prometheus:bbb' => '{"state":"resolved","version":9}',
            ]),
        ]);

        expect(app(RaftClient::class)->getAll())->toBe([
            'alert:6512ab:prometheus:9f8a1c' => ['state' => 'critical', 'version' => 4],
            'alert:6512ab:prometheus:bbb' => ['state' => 'resolved', 'version' => 9],
        ]);
    });

    it('reads an empty store as no keys', function () {
        Http::fake(['raft.test:8000/get' => Http::response([])]);

        expect(app(RaftClient::class)->getAll())->toBe([]);
    });

    it('reads a value that is not a slot document as an absent slot', function () {
        Http::fake([
            'raft.test:8000/get' => Http::response([
                'username' => '"mobin"',
                'broken' => 'not json at all',
            ]),
        ]);

        expect(app(RaftClient::class)->getAll())->toBe(['username' => null, 'broken' => null]);
    });

    it('reports an unreachable sidecar as a raft failure', function () {
        Http::fake(['raft.test:8000/leader' => Http::failedConnection()]);

        expect(fn () => app(RaftClient::class)->status())
            ->toThrow(RaftUnavailableException::class);
    });

    it('reports a write that reached a follower as a not leader failure', function () {
        Http::fake(['raft.test:8000/set' => Http::response('not the leader', 500)]);

        try {
            app(RaftClient::class)->set('alert:6512ab:prometheus:9f8a1c', ['state' => 'critical']);
        } catch (RaftUnavailableException $exception) {
            expect($exception->status)->toBe(500)
                ->and($exception->body)->toContain('not the leader')
                ->and($exception->endpoint)->toBe('/set')
                ->and($exception->isNotLeader())->toBeTrue();

            return;
        }

        $this->fail('Expected a RaftUnavailableException.');
    });

    it('does not retry a write the sidecar refused because this node follows', function () {
        Http::fake(['raft.test:8000/set' => Http::response('not the leader', 500)]);

        expect(fn () => app(RaftClient::class)->set('alert:6512ab:prometheus:9f8a1c', ['state' => 'critical']))
            ->toThrow(RaftUnavailableException::class);

        Http::assertSentCount(1);
    });

    it('retries once before giving up', function () {
        Http::fake(['raft.test:8000/leader' => Http::response('boom', 500)]);

        expect(fn () => app(RaftClient::class)->status())
            ->toThrow(RaftUnavailableException::class);

        Http::assertSentCount(2);
    });
});
