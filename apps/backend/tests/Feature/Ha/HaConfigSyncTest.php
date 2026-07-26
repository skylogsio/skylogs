<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Http\Middleware\HaNodeAuth;
use App\Models\AlertRule;
use App\Models\HaConfigVersion;
use App\Models\PrometheusCheck;
use App\Services\Ha\HaConfigPuller;
use App\Services\Ha\HaConfigSyncService;
use App\Services\Ha\HaConfigVersionStore;
use App\Services\Ha\HaLeaderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use MongoDB\BSON\ObjectId;
use Tests\Support\TeamTestData;

const HA_CONFIG_SECRET = 'test-ha-config-secret';

function haConfigSyncRequest(?int $since = null, ?string $secret = HA_CONFIG_SECRET): TestResponse
{
    $headers = $secret === null ? [] : [HaNodeAuth::SECRET_HEADER => $secret];
    $query = $since === null ? [] : ['since' => $since];

    return test()->getJson(route('ha.configSync', $query), $headers);
}

function haActAsLeader(bool $isLeader): void
{
    test()->mock(HaLeaderService::class, function ($mock) use ($isLeader) {
        $mock->shouldReceive('isLeader')->andReturn($isLeader);
    });
}

function haConfigRule(array $attributes = []): AlertRule
{
    $alertRule = AlertRule::create([
        'name' => 'HA Config Alert '.uniqid(),
        'type' => AlertRuleType::PROMETHEUS->value,
        'userId' => test()->owner->id,
        ...$attributes,
    ]);

    $created = test()->createdAlertRules;
    $created[] = $alertRule;
    test()->createdAlertRules = $created;

    return $alertRule;
}

/**
 * The leader's own state, which is what a follower would receive. Building the
 * fixtures from it keeps every apply in this file non destructive: the only
 * documents the applier can delete are the ones the test itself removed.
 *
 * @return array{version: int, changed: bool, collections: array<string, array<int, array<string, mixed>>>}
 */
function haLiveSnapshot(): array
{
    return app(HaConfigSyncService::class)->snapshot(0);
}

/**
 * @param  array{collections: array<string, array<int, array<string, mixed>>>}  $snapshot
 */
function haSnapshotRule(array $snapshot, string $alertRuleId): ?array
{
    foreach ($snapshot['collections']['alertRules'] as $document) {
        if (($document['_id']['$oid'] ?? null) === $alertRuleId) {
            return $document;
        }
    }

    return null;
}

/**
 * @param  array{collections: array<string, array<int, array<string, mixed>>>}  $snapshot
 * @param  callable(array<string, mixed>): (array<string, mixed>|null)  $mutate
 * @return array{collections: array<string, array<int, array<string, mixed>>>}
 */
function haMutateRules(array $snapshot, callable $mutate): array
{
    $snapshot['collections']['alertRules'] = array_values(array_filter(array_map(
        $mutate,
        $snapshot['collections']['alertRules'],
    )));

    return $snapshot;
}

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'ha.enabled' => true,
        'ha.node_id' => 'node-2',
        'ha.node_secret' => HA_CONFIG_SECRET,
        'ha.allowed_cidrs' => [],
        'ha.config_sync.enabled' => true,
    ]);

    $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
    $this->createdAlertRules = [];

    Queue::fake();
});

afterEach(function () {
    foreach ($this->createdAlertRules as $alertRule) {
        PrometheusCheck::query()->where('alertRuleId', $alertRule->_id)->delete();
        AlertRule::query()->where('_id', $alertRule->_id)->delete();
    }

    TeamTestData::deleteUser($this->owner);
});

describe('GET /api/ha/config-sync authentication', function () {
    it('rejects a request with no secret', function () {
        haConfigSyncRequest(secret: null)->assertUnauthorized();
    });

    it('rejects a request with the wrong secret', function () {
        haConfigSyncRequest(secret: 'guessed')->assertUnauthorized();
    });

    /*
     | A follower's copy can be a whole interval behind, so serving it would let
     | stale configuration spread sideways and outlive the leader it came from.
     */
    it('refuses to answer on a node that is not the leader', function () {
        haActAsLeader(false);

        haConfigSyncRequest()->assertStatus(409);
    });
});

describe('GET /api/ha/config-sync', function () {
    beforeEach(fn () => haActAsLeader(true));

    it('answers a caller that already holds the current version without a payload', function () {
        $version = app(HaConfigVersionStore::class)->current();

        haConfigSyncRequest(since: $version)
            ->assertOk()
            ->assertJson(['version' => $version, 'changed' => false])
            ->assertJsonMissingPath('collections');
    });

    it('sends every replicated collection to a node that has never synced', function () {
        haConfigRule();

        haConfigSyncRequest(since: 0)
            ->assertOk()
            ->assertJsonPath('changed', true)
            ->assertJsonStructure(['version', 'changed', 'collections' => ['users', 'roles', 'alertRules', 'endpoints']]);
    });

    it('rejects a version that is not a number', function () {
        test()->getJson(
            route('ha.configSync', ['since' => 'latest']),
            [HaNodeAuth::SECRET_HEADER => HA_CONFIG_SECRET],
        )->assertStatus(422);
    });
});

describe('HaConfigSyncService snapshot contents', function () {
    it('carries the configuration half of an alert rule', function () {
        $alertRule = haConfigRule(['name' => 'snapshot me', 'threshold' => 12]);

        $document = haSnapshotRule(haLiveSnapshot(), (string) $alertRule->_id);

        expect($document)->not->toBeNull()
            ->and($document['name'])->toBe('snapshot me')
            ->and($document['threshold'])->toBe(12);
    });

    /*
     | The single collection both replication paths write to. Letting the
     | snapshot carry these would have config sync overwrite a fire with a
     | thirty second old resolve, and Raft overwrite it straight back.
     */
    it('leaves the alert rule fields Raft owns out of the snapshot', function () {
        $alertRule = haConfigRule(['state' => AlertRule::CRITICAL, 'fireCount' => 3, 'notifyAt' => 1785000000]);

        $document = haSnapshotRule(haLiveSnapshot(), (string) $alertRule->_id);

        expect(array_intersect(array_keys($document), ['state', 'fireCount', 'notifyAt', 'acknowledgedBy']))
            ->toBe([]);
    });

    it('carries the password hash, so logins keep working after a failover', function () {
        $users = haLiveSnapshot()['collections']['users'];
        $owner = collect($users)->firstWhere('username', $this->owner->username);

        expect($owner)->not->toBeNull()
            ->and($owner['password'])->toBe($this->owner->password);
    });

    it('carries object ids and dates in a form that survives JSON', function () {
        $alertRule = haConfigRule();

        $document = haSnapshotRule(haLiveSnapshot(), (string) $alertRule->_id);

        expect($document['_id'])->toBe(['$oid' => (string) $alertRule->_id])
            ->and($document['createdAt']['$date'])->toBeInt();
    });

    it('carries nothing when the caller is already current', function () {
        $version = app(HaConfigVersionStore::class)->current();

        expect(app(HaConfigSyncService::class)->snapshot($version))
            ->toBe(['version' => $version, 'changed' => false, 'collections' => []]);
    });
});

describe('HaConfigVersionStore', function () {
    it('moves forward on every bump', function () {
        $store = app(HaConfigVersionStore::class);
        $before = $store->current();

        expect($store->bump())->toBe($before + 1)
            ->and($store->current())->toBe($before + 1);
    });

    it('remembers the leader version this node last applied', function () {
        $store = app(HaConfigVersionStore::class);

        $store->recordAppliedLeaderVersion(77);

        expect($store->lastAppliedLeaderVersion())->toBe(77);

        HaConfigVersion::query()->where('name', HaConfigVersion::APPLIED_LEADER)->delete();
    });

    /*
     | A leader that has never been written to must still look newer than a
     | follower sitting at zero, otherwise the first snapshot never ships.
     */
    it('never reports a version a fresh follower could match', function () {
        expect(app(HaConfigVersionStore::class)->current())->toBeGreaterThanOrEqual(1);
    });
});

describe('configuration version counter', function () {
    it('moves when an alert rule is renamed', function () {
        $alertRule = haConfigRule();
        $store = app(HaConfigVersionStore::class);
        $before = $store->current();

        $alertRule->name = 'renamed';
        $alertRule->save();

        expect($store->current())->toBeGreaterThan($before);
    });

    /*
     | Alert state moves every few seconds on a busy leader. Bumping on it would
     | hand every follower a full snapshot on every tick.
     */
    it('stays put when only the fields Raft owns move', function () {
        $alertRule = haConfigRule(['state' => AlertRule::RESOlVED]);
        $store = app(HaConfigVersionStore::class);
        $before = $store->current();

        $alertRule->state = AlertRule::CRITICAL;
        $alertRule->fireCount = 1;
        $alertRule->save();

        expect($store->current())->toBe($before);
    });
});

describe('HaConfigSyncService apply', function () {
    it('writes nothing when the snapshot already matches', function () {
        haConfigRule();

        $summary = app(HaConfigSyncService::class)->apply(haLiveSnapshot());

        expect($summary['alertRules'])->toBe(['written' => 0, 'deleted' => 0])
            ->and($summary['users'])->toBe(['written' => 0, 'deleted' => 0]);
    });

    it('creates a document the leader has and this node does not', function () {
        $alertRule = haConfigRule();
        $newId = new ObjectId;

        $snapshot = haLiveSnapshot();
        $snapshot['collections']['alertRules'][] = [
            ...haSnapshotRule($snapshot, (string) $alertRule->_id),
            '_id' => ['$oid' => (string) $newId],
            'name' => 'arrived from the leader',
        ];

        app(HaConfigSyncService::class)->apply($snapshot);

        $created = AlertRule::query()->where('_id', $newId)->first();

        if ($created) {
            $this->createdAlertRules[] = $created;
        }

        expect($created)->not->toBeNull()
            ->and($created->name)->toBe('arrived from the leader');
    });

    it('updates a document the leader changed', function () {
        $alertRule = haConfigRule(['name' => 'before']);

        $snapshot = haMutateRules(haLiveSnapshot(), fn (array $document): array => ($document['_id']['$oid'] ?? null) === (string) $alertRule->_id
            ? [...$document, 'name' => 'after']
            : $document);

        app(HaConfigSyncService::class)->apply($snapshot);

        expect($alertRule->fresh()->name)->toBe('after');
    });

    it('drops a field the leader removed', function () {
        $alertRule = haConfigRule(['description' => 'temporary note']);

        $snapshot = haMutateRules(haLiveSnapshot(), function (array $document) use ($alertRule): array {
            if (($document['_id']['$oid'] ?? null) !== (string) $alertRule->_id) {
                return $document;
            }

            unset($document['description']);

            return $document;
        });

        app(HaConfigSyncService::class)->apply($snapshot);

        expect($alertRule->fresh()->description)->toBeNull();
    });

    it('deletes a document the leader no longer holds', function () {
        $alertRule = haConfigRule();

        $snapshot = haMutateRules(
            haLiveSnapshot(),
            fn (array $document): ?array => ($document['_id']['$oid'] ?? null) === (string) $alertRule->_id ? null : $document,
        );

        app(HaConfigSyncService::class)->apply($snapshot);

        expect(AlertRule::query()->where('_id', $alertRule->_id)->first())->toBeNull();
    });

    /*
     | The follower's own runtime state comes from Raft and must survive a
     | snapshot that says nothing about it.
     */
    it('leaves the alert state Raft owns untouched', function () {
        $alertRule = haConfigRule(['state' => AlertRule::CRITICAL, 'fireCount' => 4]);

        $snapshot = haMutateRules(haLiveSnapshot(), fn (array $document): array => ($document['_id']['$oid'] ?? null) === (string) $alertRule->_id
            ? [...$document, 'name' => 'renamed by the leader']
            : $document);

        app(HaConfigSyncService::class)->apply($snapshot);

        $fresh = $alertRule->fresh();

        expect($fresh->name)->toBe('renamed by the leader')
            ->and($fresh->state)->toBe(AlertRule::CRITICAL)
            ->and($fresh->fireCount)->toBe(4);
    });

    it('preserves the identity and creation time of the documents it writes', function () {
        $alertRule = haConfigRule();
        $createdAt = $alertRule->createdAt;

        $snapshot = haMutateRules(haLiveSnapshot(), fn (array $document): array => ($document['_id']['$oid'] ?? null) === (string) $alertRule->_id
            ? [...$document, 'name' => 'renamed']
            : $document);

        app(HaConfigSyncService::class)->apply($snapshot);

        expect($alertRule->fresh()->createdAt->timestamp)->toBe($createdAt->timestamp);
    });

    /*
     | The version a follower holds is the leader's. Bumping its own counter
     | while applying would make it skip the next real change.
     */
    it('does not move this node own configuration version', function () {
        $alertRule = haConfigRule();
        $store = app(HaConfigVersionStore::class);

        $snapshot = haMutateRules(haLiveSnapshot(), fn (array $document): array => ($document['_id']['$oid'] ?? null) === (string) $alertRule->_id
            ? [...$document, 'name' => 'renamed by the leader']
            : $document);

        $before = $store->current();

        app(HaConfigSyncService::class)->apply($snapshot);

        expect($store->current())->toBe($before);
    });

    it('records the leader version once the whole snapshot is down', function () {
        $alertRule = haConfigRule();

        $snapshot = haMutateRules(haLiveSnapshot(), fn (array $document): array => ($document['_id']['$oid'] ?? null) === (string) $alertRule->_id
            ? [...$document, 'name' => 'renamed by the leader']
            : $document);

        Http::fake([
            '*/api/ha/config-sync*' => Http::response([
                'version' => 4242,
                'changed' => true,
                'collections' => $snapshot['collections'],
            ]),
        ]);

        $this->mock(HaLeaderService::class, function ($mock) {
            $mock->shouldReceive('isLeader')->andReturnFalse();
            $mock->shouldReceive('leaderAddress')->andReturn('http://skylogs-back-1:80');
        });

        expect(app(HaConfigPuller::class)->pull()['status'])->toBe('applied')
            ->and($alertRule->fresh()->name)->toBe('renamed by the leader')
            ->and(app(HaConfigVersionStore::class)->lastAppliedLeaderVersion())->toBe(4242);

        HaConfigVersion::query()->where('name', HaConfigVersion::APPLIED_LEADER)->delete();
    });

    it('ignores a collection it does not recognise, so a rolling upgrade can proceed', function () {
        $snapshot = haLiveSnapshot();
        $snapshot['collections']['somethingTheLeaderAddedLater'] = [['_id' => ['$oid' => (string) new ObjectId]]];

        expect(app(HaConfigSyncService::class)->apply($snapshot))
            ->not->toHaveKey('somethingTheLeaderAddedLater');
    });
});
