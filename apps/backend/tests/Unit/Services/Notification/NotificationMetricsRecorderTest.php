<?php

use App\Enums\EndpointType;
use App\Services\Notification\NotificationMetricsRecorder;

describe('NotificationMetricsRecorder', function () {
    beforeEach(function () {
        $this->nodeId = 'metrics-test-'.uniqid();
        config(['ha.node_id' => $this->nodeId]);
    });

    afterEach(function () {
        config([
            'ha.node_id' => $this->nodeId,
            'notification.metrics_redis_connection' => 'default',
        ]);
        app(NotificationMetricsRecorder::class)->forget();
    });

    it('counts attempts, durations and chain runs for this node', function () {
        $recorder = app(NotificationMetricsRecorder::class);

        $recorder->recordAttempt(EndpointType::SMS, 'abc123', 1, 'kaveNegar', 'failed_over', 0.25);
        $recorder->recordAttempt(EndpointType::SMS, 'abc123', 1, 'kaveNegar', 'failed_over', 0.75);
        $recorder->recordAttempt(EndpointType::SMS, null, null, '', 'sent', 1.5);
        $recorder->recordChainRun(EndpointType::CALL, 'sent_first');
        $recorder->recordChainRun(EndpointType::CALL, 'sent_first');

        $failedOver = collect($recorder->attemptSamples())->first(fn (array $row): bool => $row[1][5] === 'failed_over');
        $sent = collect($recorder->attemptSamples())->first(fn (array $row): bool => $row[1][5] === 'sent');

        expect($failedOver[0])->toEqual(2)
            ->and($failedOver[1])->toBe([$this->nodeId, 'sms', 'kaveNegar', '1', 'abc123', 'failed_over'])
            ->and($sent[1])->toBe([$this->nodeId, 'sms', 'none', 'none', 'none', 'sent']);

        $duration = collect($recorder->durationSumSamples())->first(fn (array $row): bool => $row[1][4] === 'abc123');
        $count = collect($recorder->durationCountSamples())->first(fn (array $row): bool => $row[1][4] === 'abc123');
        $missingProfile = collect($recorder->durationCountSamples())->first(fn (array $row): bool => $row[1][4] === 'none');

        expect($duration[0])->toEqual(1)
            ->and($duration[1])->toBe([$this->nodeId, 'sms', 'kaveNegar', '1', 'abc123'])
            ->and($count[0])->toEqual(2)
            ->and($missingProfile[1])->toBe([$this->nodeId, 'sms', 'none', 'none', 'none']);

        expect($recorder->chainRunSamples())->toBe([
            [2.0, [$this->nodeId, 'call', 'sent_first']],
        ]);

        config(['ha.node_id' => $this->nodeId.'-other']);

        expect(app(NotificationMetricsRecorder::class)->attemptSamples())->toBe([])
            ->and(app(NotificationMetricsRecorder::class)->chainRunSamples())->toBe([]);
    });

    it('swallows redis errors', function () {
        config(['notification.metrics_redis_connection' => 'missing-metrics-connection']);

        $recorder = app(NotificationMetricsRecorder::class);
        $recorder->recordAttempt(EndpointType::SMS, 'abc123', 1, 'kaveNegar', 'failed', 0.1);
        $recorder->recordChainRun(EndpointType::SMS, 'failed_rejected');

        expect($recorder->attemptSamples())->toBe([])
            ->and($recorder->durationSumSamples())->toBe([])
            ->and($recorder->durationCountSamples())->toBe([])
            ->and($recorder->chainRunSamples())->toBe([]);
    });
});
