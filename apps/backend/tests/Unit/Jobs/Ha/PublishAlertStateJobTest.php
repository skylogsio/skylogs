<?php

use App\Exceptions\Ha\RaftUnavailableException;
use App\Jobs\Ha\PublishAlertStateJob;
use App\Queue\Middleware\EnsureLeader;
use App\Services\Ha\RaftClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function haPublishJob(?array $value = ['state' => 'critical']): PublishAlertStateJob
{
    return new PublishAlertStateJob('alert:6512ab000000000000000001:prometheus:9f8a1c', $value);
}

describe('PublishAlertStateJob', function () {
    beforeEach(function () {
        config([
            'ha.enabled' => true,
            'ha.node_id' => 'node-1',
            'ha.raft.url' => 'http://raft.test:8000',
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

    it('sets the key on the sidecar', function () {
        Http::fake(['raft.test:8000/set' => Http::response(['status' => 'ok'])]);

        haPublishJob()->handle(app(RaftClient::class));

        Http::assertSent(fn ($request): bool => $request->url() === 'http://raft.test:8000/set'
            && $request->data() === [
                'key' => 'alert:6512ab000000000000000001:prometheus:9f8a1c',
                'value' => ['state' => 'critical'],
            ]);
    });

    it('sends a null value as a tombstone', function () {
        Http::fake(['raft.test:8000/set' => Http::response(['status' => 'ok'])]);

        haPublishJob(null)->handle(app(RaftClient::class));

        Http::assertSent(fn ($request): bool => $request->data() === [
            'key' => 'alert:6512ab000000000000000001:prometheus:9f8a1c',
            'value' => null,
        ]);
    });

    it('fails so the queue retries it when the sidecar is unreachable', function () {
        Http::fake(['raft.test:8000/set' => Http::failedConnection()]);

        haPublishJob()->handle(app(RaftClient::class));
    })->throws(RaftUnavailableException::class);

    it('fails when the sidecar refuses the write because this node follows', function () {
        Http::fake(['raft.test:8000/set' => Http::response('not the leader', 500)]);

        haPublishJob()->handle(app(RaftClient::class));
    })->throws(RaftUnavailableException::class);

    it('says which of the two failures it hit', function () {
        Log::spy();

        Http::fake(['raft.test:8000/set' => Http::response('not the leader', 500)]);

        try {
            haPublishJob()->handle(app(RaftClient::class));
        } catch (RaftUnavailableException) {
            // The log line is the assertion.
        }

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Publishing alert state was refused: this node is not the Raft leader.'
                && $context['status'] === 500
        );
    });
});
