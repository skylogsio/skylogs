<?php

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\Team;
use App\Models\User;
use App\Observers\Ha\HaConfigObserver;
use App\Services\Ha\HaConfigVersionStore;
use App\Services\Ha\HaReplicationContext;
use Illuminate\Support\Facades\Event;
use Tests\Support\Factories\AlertRuleFactory;

/**
 * A model that looks as if it had just been updated, without touching Mongo:
 * getChanges() is what the observer reads and it is filled by syncChanges().
 */
function haChangedRule(array $changes): AlertRule
{
    $alertRule = AlertRuleFactory::unsaved([
        '_id' => '6512ab000000000000000001',
        'name' => 'node down',
        'type' => AlertRuleType::PROMETHEUS,
        'state' => AlertRule::RESOlVED,
        'fireCount' => 0,
    ]);

    $alertRule->exists = true;
    $alertRule->syncOriginal();

    foreach ($changes as $field => $value) {
        $alertRule->setAttribute($field, $value);
    }

    $alertRule->syncChanges();

    return $alertRule;
}

function haCreatedRule(): AlertRule
{
    $alertRule = haChangedRule([]);
    $alertRule->wasRecentlyCreated = true;

    return $alertRule;
}

function haConfigObserver(int $expectedBumps): HaConfigObserver
{
    $versions = Mockery::mock(HaConfigVersionStore::class);
    $versions->shouldReceive('bump')->times($expectedBumps);

    return new HaConfigObserver($versions);
}

beforeEach(function () {
    config(['ha.enabled' => true]);
});

describe('HaConfigObserver registration', function () {
    it('watches every collection the snapshot carries', function (string $model) {
        expect(Event::hasListeners('eloquent.created: '.$model))->toBeTrue()
            ->and(Event::hasListeners('eloquent.updated: '.$model))->toBeTrue()
            ->and(Event::hasListeners('eloquent.deleted: '.$model))->toBeTrue();
    })->with([User::class, Team::class, AlertRule::class]);
});

describe('HaConfigObserver', function () {
    it('bumps the version when configuration changes', function () {
        haConfigObserver(expectedBumps: 1)->updated(haChangedRule(['name' => 'node still down']));
    });

    it('bumps the version for a new document', function () {
        haConfigObserver(expectedBumps: 1)->created(haCreatedRule());
    });

    it('bumps the version for a deletion, so the follower drops it too', function () {
        haConfigObserver(expectedBumps: 1)->deleted(haChangedRule([]));
    });

    /*
     | The important one. Alert state moves every few seconds on a busy leader,
     | and bumping on it would hand every follower a full snapshot every tick,
     | which is exactly the cost the version counter exists to avoid.
     */
    it('ignores a save that moved only the fields Raft owns', function () {
        haConfigObserver(expectedBumps: 0)->updated(haChangedRule([
            'state' => AlertRule::CRITICAL,
            'fireCount' => 3,
            'notifyAt' => 1785000000,
        ]));
    });

    /*
     | wasRecentlyCreated stays true for the whole life of an instance, so the
     | first state transition after a rule is created arrives on a model that
     | still looks new. Reading it here would bump on that transition.
     */
    it('ignores a state change on an instance that was created in this request', function () {
        $alertRule = haCreatedRule();
        $alertRule->setAttribute('state', AlertRule::CRITICAL);
        $alertRule->syncChanges();

        haConfigObserver(expectedBumps: 0)->updated($alertRule);
    });

    it('ignores a save that changed nothing', function () {
        haConfigObserver(expectedBumps: 0)->updated(haChangedRule([]));
    });

    it('bumps when a real change rides along with a state change', function () {
        haConfigObserver(expectedBumps: 1)->updated(haChangedRule([
            'state' => AlertRule::CRITICAL,
            'name' => 'renamed',
        ]));
    });

    it('stays quiet while a snapshot from the leader is being applied', function () {
        HaReplicationContext::apply(function () {
            haConfigObserver(expectedBumps: 0)->created(haCreatedRule());
            haConfigObserver(expectedBumps: 0)->updated(haChangedRule(['name' => 'from the leader']));
            haConfigObserver(expectedBumps: 0)->deleted(haChangedRule([]));
        });
    });

    it('stays quiet on a single node install', function () {
        config(['ha.enabled' => false]);

        haConfigObserver(expectedBumps: 0)->created(haCreatedRule());
        haConfigObserver(expectedBumps: 0)->updated(haChangedRule(['name' => 'local edit']));
    });
});
