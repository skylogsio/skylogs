<?php

use App\Models\PrometheusCheck;
use App\Services\PrometheusService;

describe('PrometheusService::mergeFetchedPrometheusAlertsPreservingUnreachableSources', function () {
    it('returns fetched alerts unchanged when no datasource failed', function () {
        $fetched = [
            [
                'dataSourceId' => 'ds-1',
                'labels' => ['alertname' => 'HighCPU'],
                'annotations' => [],
            ],
        ];

        $merged = PrometheusService::mergeFetchedPrometheusAlertsPreservingUnreachableSources(
            $fetched,
            [
                [
                    'dataSourceId' => 'ds-1',
                    'labels' => ['alertname' => 'HighCPU'],
                    'annotations' => [],
                    'skylogsStatus' => PrometheusCheck::FIRE,
                ],
            ],
            [],
        );

        expect($merged)->toBe($fetched);
    });

    it('carries over stored firing alerts for datasources whose scrape failed', function () {
        $stored = [
            [
                'dataSourceId' => 'prom-a',
                'dataSourceName' => 'Prom A',
                'labels' => ['alertname' => 'DiskFull', 'instance' => 'n1'],
                'annotations' => ['summary' => 'disk'],
                'skylogsStatus' => PrometheusCheck::FIRE,
            ],
        ];

        $merged = PrometheusService::mergeFetchedPrometheusAlertsPreservingUnreachableSources(
            [],
            $stored,
            ['prom-a'],
        );

        expect($merged)->toHaveCount(1)
            ->and($merged[0]['labels'])->toBe($stored[0]['labels'])
            ->and($merged[0]['dataSourceId'])->toBe('prom-a');
    });

    it('does not duplicate when fetched already contains the same labels', function () {
        $labelSet = ['alertname' => 'Mem', 'pod' => 'p1'];
        $fetched = [
            [
                'dataSourceId' => 'prom-a',
                'labels' => $labelSet,
                'annotations' => [],
            ],
        ];
        $stored = [
            [
                'dataSourceId' => 'prom-a',
                'labels' => $labelSet,
                'annotations' => [],
                'skylogsStatus' => PrometheusCheck::FIRE,
            ],
        ];

        $merged = PrometheusService::mergeFetchedPrometheusAlertsPreservingUnreachableSources(
            $fetched,
            $stored,
            ['prom-a'],
        );

        expect($merged)->toHaveCount(1);
    });

    it('ignores stored resolved alerts even when the datasource failed', function () {
        $merged = PrometheusService::mergeFetchedPrometheusAlertsPreservingUnreachableSources(
            [],
            [
                [
                    'dataSourceId' => 'prom-a',
                    'labels' => ['alertname' => 'X'],
                    'annotations' => [],
                    'skylogsStatus' => PrometheusCheck::RESOLVED,
                ],
            ],
            ['prom-a'],
        );

        expect($merged)->toBeArray()->toBeEmpty();
    });
});
