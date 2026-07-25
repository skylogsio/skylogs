<?php

use App\Exceptions\Ha\RaftUnavailableException;
use App\Services\Ha\RaftClient;
use Illuminate\Support\Facades\Http;

describe('RaftClient', function () {
    beforeEach(function () {
        config([
            'ha.raft.url' => 'http://raft.test:8090/',
            'ha.raft.connect_timeout' => 0.5,
            'ha.raft.timeout' => ['leader' => 1, 'save' => 3, 'state' => 3],
            'ha.raft.retry_attempts' => 2,
            'ha.raft.retry_sleep_milliseconds' => 0,
        ]);
    });

    it('reads the leader status', function () {
        Http::fake([
            'raft.test:8090/leader' => Http::response([
                'isLeader' => true,
                'nodeId' => 'node-1',
                'leaderId' => 'node-1',
                'leaderAddress' => 'http://skylogs-back-1:80',
                'term' => 7,
            ]),
        ]);

        expect(app(RaftClient::class)->leader())->toBe([
            'isLeader' => true,
            'nodeId' => 'node-1',
            'leaderId' => 'node-1',
            'leaderAddress' => 'http://skylogs-back-1:80',
            'term' => 7,
        ]);
    });

    it('saves a single key', function () {
        Http::fake([
            'raft.test:8090/save' => Http::response(['ok' => true, 'index' => 1234]),
        ]);

        $result = app(RaftClient::class)->save('alert:6512ab:prometheus:9f8a1c', ['state' => 'critical']);

        expect($result)->toBe(['ok' => true, 'index' => 1234]);

        Http::assertSent(fn ($request) => $request->url() === 'http://raft.test:8090/save'
            && $request->data() === ['alert:6512ab:prometheus:9f8a1c' => ['state' => 'critical']]);
    });

    it('saves a tombstone as a null value', function () {
        Http::fake([
            'raft.test:8090/save' => Http::response(['ok' => true, 'index' => 1235]),
        ]);

        app(RaftClient::class)->save('alert:6512ab:prometheus:9f8a1c', null);

        Http::assertSent(fn ($request) => $request->data() === ['alert:6512ab:prometheus:9f8a1c' => null]);
    });

    it('reads the whole replicated state', function () {
        Http::fake([
            'raft.test:8090/state' => Http::response([
                'index' => 1234,
                'data' => ['alert:6512ab:prometheus:9f8a1c' => ['state' => 'critical']],
            ]),
        ]);

        expect(app(RaftClient::class)->state())->toBe([
            'index' => 1234,
            'data' => ['alert:6512ab:prometheus:9f8a1c' => ['state' => 'critical']],
        ]);
    });

    it('reports an unreachable sidecar as a raft failure', function () {
        Http::fake([
            'raft.test:8090/leader' => Http::failedConnection(),
        ]);

        expect(fn () => app(RaftClient::class)->leader())
            ->toThrow(RaftUnavailableException::class);
    });

    it('reports a rejected save as a raft failure carrying the response', function () {
        Http::fake([
            'raft.test:8090/save' => Http::response(['error' => 'not_leader', 'leaderId' => 'node-2'], 409),
        ]);

        try {
            app(RaftClient::class)->save('alert:6512ab:prometheus:9f8a1c', ['state' => 'critical']);
        } catch (RaftUnavailableException $exception) {
            expect($exception->status)->toBe(409)
                ->and($exception->body)->toContain('not_leader')
                ->and($exception->endpoint)->toBe('/save');

            return;
        }

        $this->fail('Expected a RaftUnavailableException.');
    });

    it('retries once before giving up', function () {
        Http::fake([
            'raft.test:8090/leader' => Http::response('boom', 500),
        ]);

        expect(fn () => app(RaftClient::class)->leader())
            ->toThrow(RaftUnavailableException::class);

        Http::assertSentCount(2);
    });
});
