<?php

use App\Jobs\AddChecksJob;
use App\Jobs\AutoResolveApiAlertsJob;
use App\Jobs\CheckElasticJob;
use App\Jobs\CheckHealthJob;
use App\Jobs\CheckPrometheusJob;
use App\Jobs\CheckVictoriaLogsJob;
use App\Jobs\GrafanaWebhookJob;
use App\Jobs\IntervalJob;
use App\Jobs\SyncCluster;
use App\Queue\Middleware\EnsureLeader;
use App\Services\Ha\HaLeaderService;

function fakeLeaderOnlyJob(): object
{
    return new class
    {
        public bool $deleted = false;

        public function delete(): void
        {
            $this->deleted = true;
        }
    };
}

function bindHaLeaderService(bool $shouldRunLeaderWork): void
{
    $leader = Mockery::mock(HaLeaderService::class);
    $leader->shouldReceive('shouldRunLeaderWork')->andReturn($shouldRunLeaderWork);
    $leader->shouldReceive('nodeId')->andReturn('node-1');

    app()->instance(HaLeaderService::class, $leader);
}

describe('EnsureLeader', function () {
    it('runs the job on the leader', function () {
        bindHaLeaderService(true);

        $job = fakeLeaderOnlyJob();
        $handled = false;

        (new EnsureLeader)->handle($job, function () use (&$handled) {
            $handled = true;
        });

        expect($handled)->toBeTrue()
            ->and($job->deleted)->toBeFalse();
    });

    it('deletes the job on a follower', function () {
        bindHaLeaderService(false);

        $job = fakeLeaderOnlyJob();
        $handled = false;

        (new EnsureLeader)->handle($job, function () use (&$handled) {
            $handled = true;
        });

        expect($handled)->toBeFalse()
            ->and($job->deleted)->toBeTrue();
    });

    it('guards every leader only job', function () {
        $jobs = [
            new CheckPrometheusJob,
            new AddChecksJob,
            new AutoResolveApiAlertsJob,
            new SyncCluster,
            new IntervalJob,
            new CheckElasticJob(null),
            new CheckVictoriaLogsJob(null),
            new CheckHealthJob(null),
            new GrafanaWebhookJob(null, []),
        ];

        foreach ($jobs as $job) {
            expect($job->middleware())->toHaveCount(1)
                ->and($job->middleware()[0])->toBeInstanceOf(EnsureLeader::class);
        }
    });
});
