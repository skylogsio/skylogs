<?php

/*
|--------------------------------------------------------------------------
| Pest bootstrap
|--------------------------------------------------------------------------
|
| Binds Laravel's TestCase to Feature and Unit Pest tests. Class-based
| PHPUnit tests in these directories are unchanged.
|
*/

use App\Enums\EndpointType;
use App\Services\Notification\NotificationMetricsRecorder;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

function allowNotificationMetrics(): void
{
    if (app()->bound(NotificationMetricsRecorder::class)) {
        return;
    }

    $recorder = Mockery::mock(NotificationMetricsRecorder::class);
    $recorder->shouldReceive('recordAttempt')->byDefault();
    $recorder->shouldReceive('recordChainRun')->byDefault();
    app()->instance(NotificationMetricsRecorder::class, $recorder);
}

/**
 * @param  list<array{profileId: ?string, priority: ?int, provider: string, outcome: string}>  $attempts
 */
function expectProfileMetrics(EndpointType $type, array $attempts, string $chainOutcome): void
{
    $recorder = Mockery::mock(NotificationMetricsRecorder::class);

    foreach ($attempts as $attempt) {
        $recorder->shouldReceive('recordAttempt')
            ->once()
            ->ordered()
            ->with(
                $type,
                $attempt['profileId'],
                $attempt['priority'],
                $attempt['provider'],
                $attempt['outcome'],
                Mockery::on(fn (float $seconds): bool => $seconds >= 0),
            );
    }

    $recorder->shouldReceive('recordChainRun')->once()->with($type, $chainOutcome);
    app()->instance(NotificationMetricsRecorder::class, $recorder);
}

/**
 * @return list<string>
 */
function laravelPaginatorStructure(): array
{
    return [
        'current_page',
        'data',
        'last_page',
        'per_page',
        'total',
    ];
}
