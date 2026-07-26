<?php

use App\Exceptions\Ha\RaftUnavailableException;
use App\Jobs\Ha\PublishAlertStateJob;
use App\Queue\Middleware\EnsureLeader;
use App\Services\Ha\RaftClient;
use Illuminate\Support\Facades\Http;

function haPublishJob(?array $value = ['state' => 'critical']): PublishAlertStateJob
{
    return new PublishAlertStateJob('alert:6512ab000000000000000001:prometheus:9f8a1c', $value);
}

describe('PublishAlertStateJob', function () {
    beforeEach(function () {
        config([
            'ha.enabled' => true,
            'ha.node_id' => 'node-1',
            'ha.raft.url' => 'http://raft.test:8090',
            'ha.raft.retry_attempts' => 1,
            'ha.raft.retry_sleep_milliseconds' => 0,
        ]);
    });

    it('runs on its own queue so a slow sidecar cannot delay evaluation', function () {
        expect(haPublishJob()->queue)->toBe('ha');
    });

    it('is dropped once this node is no longer the leader', function () {
        expect(haPublishJob()->middleware())->toHaveCount(1)
            ->and(haPublishJob()->middleware()[0])->toBeInstanceOf(EnsureLeader::class);
    });

    it('retries a handful of times with a growing backoff', function () {
        $job = haPublishJob();

        expect($job->tries)->toBe(5)
            ->and($job->backoff())->toBe([1, 5, 15, 60]);
    });

    it('saves the key to the sidecar', function () {
        Http::fake(['raft.test:8090/save' => Http::response(['ok' => true, 'index' => 1234])]);

        haPublishJob()->handle(app(RaftClient::class));

        Http::assertSent(fn ($request): bool => $request->url() === 'http://raft.test:8090/save'
            && $request->data() === ['alert:6512ab000000000000000001:prometheus:9f8a1c' => ['state' => 'critical']]);
    });

    it('sends a null value as a tombstone', function () {
        Http::fake(['raft.test:8090/save' => Http::response(['ok' => true])]);

        haPublishJob(null)->handle(app(RaftClient::class));

        Http::assertSent(fn ($request): bool => $request->data() === ['alert:6512ab000000000000000001:prometheus:9f8a1c' => null]);
    });

    it('fails so the queue retries it when the sidecar is unreachable', function () {
        Http::fake(['raft.test:8090/save' => Http::failedConnection()]);

        haPublishJob()->handle(app(RaftClient::class));
    })->throws(RaftUnavailableException::class);

    it('fails so the queue retries it when the sidecar rejects a non leader write', function () {
        Http::fake(['raft.test:8090/save' => Http::response(['error' => 'not_leader'], 409)]);

        haPublishJob()->handle(app(RaftClient::class));
    })->throws(RaftUnavailableException::class);
});
