<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Http\Middleware\HaNodeAuth;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\HaHistorySyncCursor;
use App\Models\Notify;
use App\Models\PrometheusHistory;
use App\Services\Ha\HaHistoryPuller;
use App\Services\Ha\HaHistorySyncCursorStore;
use App\Services\Ha\HaHistorySyncService;
use App\Services\Ha\HaLeaderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use MongoDB\BSON\ObjectId;
use Tests\Support\TeamTestData;

const HA_HISTORY_SECRET = 'test-ha-history-secret';

function haHistorySyncRequest(array $query = [], ?string $secret = HA_HISTORY_SECRET): TestResponse
{
    $headers = $secret === null ? [] : [HaNodeAuth::SECRET_HEADER => $secret];

    return test()->getJson(route('ha.historySync', $query), $headers);
}

function haHistoryActAsLeader(bool $isLeader, ?string $address = 'http://leader.test'): void
{
    test()->mock(HaLeaderService::class, function ($mock) use ($isLeader, $address) {
        $mock->shouldReceive('isLeader')->andReturn($isLeader);
        $mock->shouldReceive('leaderAddress')->andReturn($isLeader ? null : $address);
    });
}

beforeEach(function () {
    config([
        'cache.default' => 'array',
        'ha.enabled' => true,
        'ha.node_id' => 'node-2',
        'ha.node_secret' => HA_HISTORY_SECRET,
        'ha.allowed_cidrs' => [],
        'ha.history_sync.enabled' => true,
        'ha.history_sync.page_size' => 2,
        'ha.history_sync.max_pages_per_tick' => 5,
        'ha.history_sync.connect_timeout' => 1,
        'ha.history_sync.timeout' => 5,
    ]);

    $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
    $this->createdHistoryIds = [];
    $this->createdNotifyIds = [];
    $this->createdAlertRules = [];

    Queue::fake();
});

afterEach(function () {
    foreach ($this->createdHistoryIds as $id) {
        PrometheusHistory::query()->where('_id', $id)->delete();
    }

    foreach ($this->createdNotifyIds as $id) {
        Notify::query()->where('_id', $id)->delete();
    }

    foreach ($this->createdAlertRules as $alertRule) {
        AlertRule::query()->where('_id', $alertRule->_id)->delete();
    }

    HaHistorySyncCursor::query()->delete();
    TeamTestData::deleteUser($this->owner);
});

function haHistoryAlertRule(): AlertRule
{
    $alertRule = AlertRule::create([
        'name' => 'HA History Alert '.uniqid(),
        'type' => AlertRuleType::PROMETHEUS->value,
        'userId' => test()->owner->id,
    ]);

    $created = test()->createdAlertRules;
    $created[] = $alertRule;
    test()->createdAlertRules = $created;

    return $alertRule;
}

function haCreatePrometheusHistory(AlertRule $alertRule, array $attributes = []): PrometheusHistory
{
    $history = PrometheusHistory::create([
        'alertRuleId' => $alertRule->_id,
        'state' => PrometheusHistory::FIRE,
        'alerts' => [['labels' => ['alertname' => 'NodeDown']]],
        ...$attributes,
    ]);

    $ids = test()->createdHistoryIds;
    $ids[] = $history->_id;
    test()->createdHistoryIds = $ids;

    return $history;
}

function haCreateNotify(AlertRule $alertRule, array $attributes = []): Notify
{
    $notify = Notify::create([
        'type' => SendNotifyJob::PROMETHEUS_FIRE,
        'alertRuleId' => $alertRule->_id,
        'alert' => ['labels' => ['alertname' => 'NodeDown']],
        'messages' => ['body' => 'down'],
        'status' => Notify::STATUS_CREATED,
        ...$attributes,
    ]);

    $ids = test()->createdNotifyIds;
    $ids[] = $notify->_id;
    test()->createdNotifyIds = $ids;

    return $notify;
}

describe('GET /api/ha/history-sync authentication', function () {
    it('rejects a request with no secret', function () {
        haHistoryActAsLeader(true);

        haHistorySyncRequest(['collection' => 'notifies'], secret: null)
            ->assertUnauthorized();
    });

    it('rejects a request with the wrong secret', function () {
        haHistoryActAsLeader(true);

        haHistorySyncRequest(['collection' => 'notifies'], secret: 'wrong')
            ->assertUnauthorized();
    });
});

describe('GET /api/ha/history-sync leadership', function () {
    it('returns 409 when this node is not the leader', function () {
        haHistoryActAsLeader(false);

        haHistorySyncRequest(['collection' => 'prometheusHistories'])
            ->assertStatus(409);
    });
});

describe('GET /api/ha/history-sync pages', function () {
    it('rejects an unknown collection', function () {
        haHistoryActAsLeader(true);

        haHistorySyncRequest(['collection' => 'users'])
            ->assertUnprocessable();
    });

    it('returns documents in updatedAt order with pagination', function () {
        haHistoryActAsLeader(true);

        $alertRule = haHistoryAlertRule();
        $first = haCreatePrometheusHistory($alertRule);
        $second = haCreatePrometheusHistory($alertRule);
        $third = haCreatePrometheusHistory($alertRule);

        $response = haHistorySyncRequest([
            'collection' => 'prometheusHistories',
            'limit' => 2,
        ])->assertOk()->json();

        expect($response['collection'])->toBe('prometheusHistories')
            ->and($response['hasMore'])->toBeTrue()
            ->and($response['documents'])->toHaveCount(2)
            ->and($response['nextCursor']['id'])->toBeString();

        $secondPage = haHistorySyncRequest([
            'collection' => 'prometheusHistories',
            'limit' => 2,
            'afterUpdatedAt' => $response['nextCursor']['updatedAt'],
            'afterId' => $response['nextCursor']['id'],
        ])->assertOk()->json();

        $ids = collect($response['documents'])
            ->merge($secondPage['documents'])
            ->map(fn (array $doc): string => $doc['_id']['$oid'] ?? '')
            ->filter()
            ->values()
            ->all();

        expect($ids)->toContain((string) $first->_id)
            ->and($ids)->toContain((string) $second->_id)
            ->and($ids)->toContain((string) $third->_id);
    });
});

describe('HaHistorySyncService applyPage', function () {
    it('upserts documents by the leader ObjectId without dispatching notifies', function () {
        $alertRule = haHistoryAlertRule();
        $leaderId = (string) new ObjectId;

        $documents = [[
            '_id' => ['$oid' => $leaderId],
            'type' => SendNotifyJob::PROMETHEUS_FIRE,
            'alertRuleId' => ['$oid' => (string) $alertRule->_id],
            'alert' => ['labels' => ['alertname' => 'NodeDown']],
            'messages' => ['body' => 'down'],
            'status' => Notify::STATUS_DONE,
        ]];

        $result = app(HaHistorySyncService::class)->applyPage('notifies', $documents);

        expect($result['written'])->toBe(1);

        $local = Notify::where('_id', new ObjectId($leaderId))->first();
        $ids = test()->createdNotifyIds;
        $ids[] = $local->_id;
        test()->createdNotifyIds = $ids;

        expect($local)->not->toBeNull()
            ->and((int) $local->status)->toBe(Notify::STATUS_DONE);

        Queue::assertNotPushed(SendNotifyJob::class);
    });
});

describe('HaHistoryPuller', function () {
    it('pulls pages from the leader and advances the cursor', function () {
        $alertRule = haHistoryAlertRule();
        $history = haCreatePrometheusHistory($alertRule);

        $encoded = app(HaHistorySyncService::class)->page('prometheusHistories', null, null, 10);
        $document = collect($encoded['documents'])->first(
            fn (array $doc): bool => ($doc['_id']['$oid'] ?? null) === (string) $history->_id
        );

        expect($document)->not->toBeNull();

        PrometheusHistory::query()->where('_id', $history->_id)->delete();
        test()->createdHistoryIds = array_values(array_filter(
            test()->createdHistoryIds,
            fn ($id) => (string) $id !== (string) $history->_id,
        ));

        haHistoryActAsLeader(false, 'http://leader.test');

        Http::fake([
            'leader.test/api/ha/history-sync*' => function ($request) use ($document) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $collection = $query['collection'] ?? null;

                if ($collection !== 'prometheusHistories') {
                    return Http::response([
                        'collection' => $collection,
                        'documents' => [],
                        'nextCursor' => null,
                        'hasMore' => false,
                    ]);
                }

                $afterId = $query['afterId'] ?? null;

                if ($afterId) {
                    return Http::response([
                        'collection' => 'prometheusHistories',
                        'documents' => [],
                        'nextCursor' => null,
                        'hasMore' => false,
                    ]);
                }

                return Http::response([
                    'collection' => 'prometheusHistories',
                    'documents' => [$document],
                    'nextCursor' => [
                        'updatedAt' => $document['updatedAt']['$date'] ?? 1,
                        'id' => $document['_id']['$oid'],
                    ],
                    'hasMore' => false,
                ]);
            },
        ]);

        $result = app(HaHistoryPuller::class)->pull();

        expect($result['status'])->toBe('pulled')
            ->and($result['collections']['prometheusHistories']['written'])->toBe(1);

        $restored = PrometheusHistory::where('_id', new ObjectId((string) $history->_id))->first();
        $ids = test()->createdHistoryIds;
        $ids[] = $history->_id;
        test()->createdHistoryIds = $ids;

        expect($restored)->not->toBeNull();

        $cursor = app(HaHistorySyncCursorStore::class)->get('prometheusHistories');

        expect($cursor['afterId'])->toBe((string) $history->_id);
    });

    it('picks up a notify status update via the updatedAt cursor', function () {
        $alertRule = haHistoryAlertRule();
        $notify = haCreateNotify($alertRule, ['status' => Notify::STATUS_CREATED]);

        $notify->status = Notify::STATUS_DONE;
        $notify->save();

        $page = app(HaHistorySyncService::class)->page('notifies', null, null, 50);
        $document = collect($page['documents'])->first(
            fn (array $doc): bool => ($doc['_id']['$oid'] ?? null) === (string) $notify->_id
        );

        expect($document)->not->toBeNull()
            ->and((int) $document['status'])->toBe(Notify::STATUS_DONE);

        Notify::query()->where('_id', $notify->_id)->delete();

        app(HaHistorySyncService::class)->applyPage('notifies', [$document]);

        $local = Notify::where('_id', $notify->_id)->first();
        $ids = test()->createdNotifyIds;
        $ids[] = $notify->_id;
        test()->createdNotifyIds = $ids;

        expect($local)->not->toBeNull()
            ->and((int) $local->status)->toBe(Notify::STATUS_DONE);

        Queue::assertNotPushed(SendNotifyJob::class);
    });
});
