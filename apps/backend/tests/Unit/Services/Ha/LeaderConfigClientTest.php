<?php

use App\Exceptions\Ha\LeaderUnavailableException;
use App\Http\Middleware\HaNodeAuth;
use App\Services\Ha\LeaderConfigClient;
use Illuminate\Support\Facades\Http;

describe('LeaderConfigClient', function () {
    beforeEach(function () {
        config([
            'ha.node_secret' => 'shared-secret',
            'ha.config_sync.connect_timeout' => 2,
            'ha.config_sync.timeout' => 30,
        ]);
    });

    it('asks the leader for everything newer than the version it holds', function () {
        Http::fake([
            'skylogs-back-1/api/ha/config-sync*' => Http::response([
                'version' => 42,
                'changed' => true,
                'collections' => ['users' => [['_id' => ['$oid' => '6512ab000000000000000001']]]],
            ]),
        ]);

        $snapshot = app(LeaderConfigClient::class)->snapshot('http://skylogs-back-1:80/', 41);

        expect($snapshot['version'])->toBe(42)
            ->and($snapshot['changed'])->toBeTrue()
            ->and($snapshot['collections'])->toHaveKey('users');

        Http::assertSent(fn ($request) => $request->hasHeader(HaNodeAuth::SECRET_HEADER, 'shared-secret')
            && $request->url() === 'http://skylogs-back-1/api/ha/config-sync?since=41');
    });

    it('reads an unchanged answer without inventing collections', function () {
        Http::fake([
            '*' => Http::response(['version' => 42, 'changed' => false]),
        ]);

        expect(app(LeaderConfigClient::class)->snapshot('http://skylogs-back-1:80', 42))
            ->toBe(['version' => 42, 'changed' => false, 'collections' => []]);
    });

    it('reports an unreachable leader rather than a raw HTTP failure', function () {
        Http::fake(['*' => Http::failedConnection()]);

        expect(fn () => app(LeaderConfigClient::class)->snapshot('http://skylogs-back-1:80', 0))
            ->toThrow(LeaderUnavailableException::class);
    });

    /*
     | A node that has just lost leadership answers 409, which is exactly the
     | case the follower must survive quietly until the next tick.
     */
    it('reports a peer that is no longer the leader', function () {
        Http::fake(['*' => Http::response(['message' => 'This node is not the leader.'], 409)]);

        try {
            app(LeaderConfigClient::class)->snapshot('http://skylogs-back-1:80', 0);
        } catch (LeaderUnavailableException $exception) {
            expect($exception->status)->toBe(409)
                ->and($exception->address)->toBe('http://skylogs-back-1:80');

            return;
        }

        $this->fail('Expected a LeaderUnavailableException.');
    });
});
