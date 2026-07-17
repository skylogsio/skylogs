<?php

use App\Models\AlertRule;
use App\Services\AlertStatus\AlertStatusEvent;
use App\Services\AlertStatus\AlertStatusTimelineBuilder;
use Carbon\Carbon;

describe('AlertStatusTimelineBuilder', function () {
    it('merges consecutive buckets into segments whose counts sum to the configured slot count', function () {
        $builder = new AlertStatusTimelineBuilder;

        $timeline = $builder->build(collect(), 0, 1000, 10);

        expect($timeline['bucketSeconds'])->toBe(100)
            ->and($timeline['segments'])->toHaveCount(1)
            ->and($timeline['segments'][0])->toMatchArray([
                'status' => AlertRule::UNKNOWN,
                'count' => 10,
                'fromTime' => 0,
                'toTime' => 1000,
            ])
            ->and(collect($timeline['segments'])->sum('count'))->toBe(10);
    });

    it('splits merged segments when the underlying status period changes', function () {
        $builder = new AlertStatusTimelineBuilder;

        $events = collect([
            new AlertStatusEvent('rule-1', Carbon::createFromTimestamp(0), AlertRule::RESOlVED, 0, null),
            new AlertStatusEvent('rule-1', Carbon::createFromTimestamp(700), AlertRule::CRITICAL, 2, 'Database is down'),
        ]);

        $timeline = $builder->build($events, 0, 1000, 10);
        $segments = $timeline['segments'];

        expect(collect($segments)->sum('count'))->toBe(10)
            ->and($segments)->toHaveCount(2)
            ->and($segments[0]['status'])->toBe(AlertRule::RESOlVED)
            ->and($segments[0]['count'])->toBe(7)
            ->and($segments[1]['status'])->toBe(AlertRule::CRITICAL)
            ->and($segments[1]['count'])->toBe(3)
            ->and($segments[1]['summary'])->toBe('Database is down');
    });

    it('assigns a bucket that spans unknown and warning to warning', function () {
        $builder = new AlertStatusTimelineBuilder;

        $events = collect([
            new AlertStatusEvent('rule-1', Carbon::createFromTimestamp(50), AlertRule::WARNING, 1, 'Latency is elevated'),
            new AlertStatusEvent('rule-1', Carbon::createFromTimestamp(250), AlertRule::CRITICAL, 2, 'Latency is very high'),
        ]);

        $timeline = $builder->build($events, 0, 1000, 10);
        $segments = $timeline['segments'];

        expect($segments[0]['status'])->toBe(AlertRule::WARNING)
            ->and($segments[0]['count'])->toBe(2)
            ->and($segments[1]['status'])->toBe(AlertRule::CRITICAL)
            ->and($segments[1]['count'])->toBe(8);
    });

    it('keeps consecutive critical periods separate when summaries differ', function () {
        $builder = new AlertStatusTimelineBuilder;

        $events = collect([
            new AlertStatusEvent('rule-1', Carbon::createFromTimestamp(0), AlertRule::RESOlVED, 0, null),
            new AlertStatusEvent('rule-1', Carbon::createFromTimestamp(300), AlertRule::CRITICAL, 1, 'First fire'),
            new AlertStatusEvent('rule-1', Carbon::createFromTimestamp(500), AlertRule::CRITICAL, 3, 'Updated fire'),
            new AlertStatusEvent('rule-1', Carbon::createFromTimestamp(800), AlertRule::RESOlVED, 0, null),
        ]);

        $timeline = $builder->build($events, 0, 1000, 10);
        $segments = $timeline['segments'];

        expect(collect($segments)->sum('count'))->toBe(10)
            ->and($segments)->toHaveCount(4)
            ->and($segments[0])->toMatchArray([
                'status' => AlertRule::RESOlVED,
                'count' => 3,
                'fromTime' => 0,
                'toTime' => 300,
            ])
            ->and($segments[1])->toMatchArray([
                'status' => AlertRule::CRITICAL,
                'count' => 2,
                'fromTime' => 300,
                'toTime' => 500,
                'summary' => 'First fire',
            ])
            ->and($segments[2])->toMatchArray([
                'status' => AlertRule::CRITICAL,
                'count' => 3,
                'fromTime' => 500,
                'toTime' => 800,
                'summary' => 'Updated fire',
            ])
            ->and($segments[3])->toMatchArray([
                'status' => AlertRule::RESOlVED,
                'count' => 2,
                'fromTime' => 800,
                'toTime' => 1000,
            ]);
    });

    it('buckets a single critical window into the expected 10000-second slots', function () {
        $builder = new AlertStatusTimelineBuilder;
        $fromTime = 1_782_000_000;
        $toTime = 1_783_000_000;
        $fireAt = 1_782_605_000;
        $resolveAt = 1_782_655_000;

        $events = collect([
            new AlertStatusEvent('rule-1', Carbon::createFromTimestamp($fromTime - 1), AlertRule::RESOlVED, 0, null),
            new AlertStatusEvent('rule-1', Carbon::createFromTimestamp($fireAt), AlertRule::CRITICAL, 1, 'connection refused'),
            new AlertStatusEvent('rule-1', Carbon::createFromTimestamp($resolveAt), AlertRule::RESOlVED, 0, null),
        ]);

        $timeline = $builder->build($events, $fromTime, $toTime, 100);
        $segments = $timeline['segments'];

        $userExpectedCriticalBuckets = [
            1_782_610_000,
            1_782_620_000,
            1_782_630_000,
            1_782_640_000,
            1_782_650_000,
        ];

        expect($timeline['bucketSeconds'])->toBe(10_000)
            ->and(collect($segments)->sum('count'))->toBe(100);

        foreach ($userExpectedCriticalBuckets as $bucketFrom) {
            $midpoint = $bucketFrom + 5_000;

            $status = collect($segments)->first(
                fn (array $segment): bool => $midpoint >= $segment['fromTime'] && $midpoint < $segment['toTime'],
            )['status'] ?? null;

            expect($status)->toBe(AlertRule::CRITICAL);
        }

        $criticalSegment = collect($segments)->firstWhere('status', AlertRule::CRITICAL);

        expect($criticalSegment)->not->toBeNull()
            ->and($criticalSegment['fromTime'])->toBeLessThanOrEqual(1_782_610_000)
            ->and($criticalSegment['toTime'])->toBeGreaterThanOrEqual(1_782_660_000)
            ->and($criticalSegment['count'])->toBeGreaterThanOrEqual(5);
    });
});
