<?php

use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\ElasticCheck;
use App\Models\GrafanaWebhookAlert;
use App\Models\HealthCheck;
use App\Models\Notify;
use App\Models\PrometheusCheck;
use App\Models\PrometheusHistory;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\Ha\HaConfigCatalog;

describe('HaConfigCatalog membership', function () {
    it('leaves every collection Raft owns out of the snapshot', function (string $model) {
        expect(HaConfigCatalog::models())->not->toContain($model);
    })->with([
        PrometheusCheck::class,
        ElasticCheck::class,
        HealthCheck::class,
        AlertInstance::class,
        PrometheusHistory::class,
        GrafanaWebhookAlert::class,
    ]);

    it('leaves node local derivations and history-sync collections out of config sync', function (string $model) {
        expect(HaConfigCatalog::models())->not->toContain($model);
    })->with([
        'status history' => StatusHistory::class,
        'notifications' => Notify::class,
        'prometheus history' => PrometheusHistory::class,
    ]);

    it('carries the collections a follower needs to serve traffic after a failover', function (string $alias) {
        expect(HaConfigCatalog::definition($alias))->not->toBeNull();
    })->with(['users', 'roles', 'permissions', 'teams', 'endpoints', 'dataSources', 'alertRules', 'skylogsInstances', 'incidents', 'onCallPlans']);

    it('does not snapshot the retired services collection', function () {
        expect(HaConfigCatalog::definition('services'))->toBeNull();
    });

    it('names the model behind an instance', function () {
        expect(HaConfigCatalog::aliasFor(new AlertRule))->toBe('alertRules')
            ->and(HaConfigCatalog::aliasFor(new User))->toBe('users')
            ->and(HaConfigCatalog::aliasFor(new PrometheusCheck))->toBeNull();
    });
});

describe('HaConfigCatalog field boundaries', function () {
    it('keeps config sync away from the alert rule fields Raft replicates', function (string $field) {
        expect(HaConfigCatalog::protectedFields('alertRules'))->toContain($field);
    })->with(HaConfigCatalog::RAFT_OWNED_ALERT_RULE_FIELDS);

    it('still replicates the alert rule fields that are pure configuration', function (string $field) {
        expect(HaConfigCatalog::protectedFields('alertRules'))->not->toContain($field);
    })->with(['name', 'type', 'userId', 'threshold', 'silentUserIds']);

    it('protects the primary key everywhere, because the applier sets it itself', function () {
        expect(HaConfigCatalog::protectedFields('users'))->toContain('_id')
            ->and(HaConfigCatalog::protectedFields('users'))->toContain('updatedAt');
    });

    /*
     | createdAt is user visible, so a follower showing every alert rule as
     | created the moment it first synced would be a visible regression.
     */
    it('replicates createdAt rather than restamping it on the follower', function () {
        expect(HaConfigCatalog::protectedFields('alertRules'))->not->toContain('createdAt');
    });

    it('returns nothing for a collection it does not know', function () {
        expect(HaConfigCatalog::definition('somethingElse'))->toBeNull()
            ->and(HaConfigCatalog::protectedFields('somethingElse'))->toBe(HaConfigCatalog::APPLIER_OWNED_FIELDS);
    });
});
