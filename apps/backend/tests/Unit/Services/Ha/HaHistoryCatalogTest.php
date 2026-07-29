<?php

use App\Models\Notify;
use App\Models\PrometheusHistory;
use App\Models\StatusHistory;
use App\Services\Ha\HaHistoryCatalog;

describe('HaHistoryCatalog membership', function () {
    it('carries the timeline and notify collections followers need after failover', function (string $alias) {
        expect(HaHistoryCatalog::model($alias))->not->toBeNull();
    })->with([
        'prometheusHistories',
        'grafanaWebhookAlerts',
        'zabbixWebhookAlerts',
        'elasticHistories',
        'victoriaLogsHistories',
        'healthHistories',
        'apiAlertHistories',
        'apiAlertStatusHistories',
        'sentryWebhookAlerts',
        'metabaseWebhookAlerts',
        'notifies',
    ]);

    it('leaves status history out because each node derives it locally', function () {
        expect(HaHistoryCatalog::aliasFor(new StatusHistory))->toBeNull();
    });

    it('names the model behind an instance', function () {
        expect(HaHistoryCatalog::aliasFor(new PrometheusHistory))->toBe('prometheusHistories')
            ->and(HaHistoryCatalog::aliasFor(new Notify))->toBe('notifies');
    });

    it('protects only the primary key fields the applier owns', function () {
        expect(HaHistoryCatalog::protectedFields())->toBe(['_id', 'id']);
    });
});
