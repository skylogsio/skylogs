<?php

use App\Enums\DataSourceType;
use App\Jobs\CheckPrometheusJob;
use App\Models\DataSource\DataSource;
use App\Services\DataSource\DataSourceAvailability;
use App\Services\DataSourceService;
use App\Services\PrometheusInstanceService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function prometheusSource(string $id, string $url): DataSource
{
    $source = new DataSource;
    $source->setAttribute('_id', $id);
    $source->setAttribute('name', 'Prometheus '.$id);
    $source->setAttribute('url', $url);
    $source->setAttribute('type', DataSourceType::PROMETHEUS);

    return $source;
}

function prometheusFetcher(DataSource ...$sources): PrometheusInstanceService
{
    $keyed = (new Collection($sources))->keyBy(fn (DataSource $source) => (string) $source->id);

    $dataSources = Mockery::mock(DataSourceService::class);
    $dataSources->shouldReceive('get')
        ->with(DataSourceType::PROMETHEUS)
        ->andReturn($keyed);

    return new PrometheusInstanceService($dataSources);
}

describe('Prometheus triggered alert fetch', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
    });

    afterEach(function () {
        Cache::flush();
    });

    it('cools down a prometheus source after a connection failure and skips it next time', function () {
        $id = '507f1f77bcf86cd799439011';
        $attempts = 0;

        Http::fake(function ($request) use (&$attempts) {
            $attempts++;

            return Http::failedConnection()($request);
        });

        $first = prometheusFetcher(prometheusSource($id, 'https://prom.example.com'))
            ->getTriggeredForAlertSync();

        expect($first['alerts'])->toBe([])
            ->and($first['failedDataSourceIds'])->toContain($id)
            ->and(app(DataSourceAvailability::class)->isCoolingDown($id))->toBeTrue()
            ->and($attempts)->toBe(1);

        $second = prometheusFetcher(prometheusSource($id, 'https://prom.example.com'))
            ->getTriggeredForAlertSync();

        expect($second['failedDataSourceIds'])->toContain($id)
            ->and($attempts)->toBe(1);
    });

    it('does not queue a second prometheus check while the first lock is held', function () {
        Queue::fake();

        CheckPrometheusJob::dispatch();
        CheckPrometheusJob::dispatch();

        Queue::assertPushed(CheckPrometheusJob::class, 1);
    });
});
