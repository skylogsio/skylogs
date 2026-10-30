<?php

use App\Enums\EndpointType;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationDeliveryTrigger;
use App\Jobs\SendNotificationDeliveryJob;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use App\Models\Endpoint;
use App\Models\NotificationDelivery;
use App\Models\Notify;
use App\Models\SentryWebhookAlert;
use App\Services\Ha\HaReplicationContext;
use App\Services\Notification\DeliveryTarget;
use App\Services\Notification\NotificationDeliveryService;
use App\Services\Notification\NotificationDispatcher;
use App\Services\SendNotifyService;
use App\Support\NotifyMessagePayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use MongoDB\BSON\ObjectId;

function deliveryTestEndpoint(array $attributes = []): Endpoint
{
    $endpoint = Endpoint::withoutEvents(fn () => Endpoint::create([
        'userId' => 'delivery-test-user',
        'name' => 'Delivery Test '.uniqid(),
        'type' => EndpointType::TELEGRAM->value,
        'chatId' => '-100'.uniqid(),
        'botToken' => 'bot-token',
        'accessUserIds' => [],
        'accessTeamIds' => [],
        ...$attributes,
    ]));

    test()->endpointIds = [...test()->endpointIds, $endpoint->id];

    return $endpoint;
}

function runDeliveryJob(string $deliveryId, NotificationDeliveryTrigger $trigger = NotificationDeliveryTrigger::AUTO): NotificationDelivery
{
    (new SendNotificationDeliveryJob($deliveryId, $trigger))->handle(app(NotificationDeliveryService::class));

    return NotificationDelivery::query()->where('_id', $deliveryId)->firstOrFail();
}

function deliveryTestHistory(): ApiAlertHistory
{
    $history = ApiAlertHistory::create([
        'alertRuleId' => (string) new ObjectId,
        'state' => ApiAlertHistory::FIRE,
        'deliveryCounts' => ['sent' => 0, 'failed' => 0],
    ]);

    test()->historyIds = [...test()->historyIds, $history->id];

    return $history;
}

/**
 * @return array{sent: int, failed: int}
 */
function historyDeliveryCounts(ApiAlertHistory $history): array
{
    $counts = $history->fresh()->deliveryCounts;

    return [
        'sent' => (int) ($counts['sent'] ?? 0),
        'failed' => (int) ($counts['failed'] ?? 0),
    ];
}

function dispatchToEndpoints(array $endpoints, string $body = 'CPU high', array $context = []): Collection
{
    $deliveries = app(NotificationDispatcher::class)->dispatch(
        array_map(fn (Endpoint $endpoint) => new DeliveryTarget($endpoint, NotifyMessagePayload::fromBody($body)), $endpoints),
        ['source' => NotificationDelivery::SOURCE_ALERT, ...$context],
    );

    test()->deliveryIds = [...test()->deliveryIds, ...$deliveries->pluck('id')->all()];

    return $deliveries;
}

describe('notification delivery', function () {
    beforeEach(function () {
        config([
            'cache.default' => 'array',
            'notification.max_attempts' => 3,
            'notification.retry_backoff' => [30, 120],
        ]);
        Http::preventStrayRequests();

        $this->endpointIds = [];
        $this->deliveryIds = [];
        $this->notifyIds = [];
        $this->alertRuleIds = [];
        $this->historyIds = [];
        $this->sentryIds = [];
    });

    afterEach(function () {
        NotificationDelivery::query()->whereIn('_id', $this->deliveryIds)->delete();
        Endpoint::withoutEvents(fn () => Endpoint::query()->whereIn('_id', $this->endpointIds)->delete());
        Notify::query()->whereIn('_id', $this->notifyIds)->delete();
        AlertRule::withoutEvents(fn () => AlertRule::query()->whereIn('_id', $this->alertRuleIds)->delete());
        ApiAlertHistory::query()->whereIn('_id', $this->historyIds)->delete();
        SentryWebhookAlert::query()->whereIn('_id', $this->sentryIds)->delete();
    });

    it('records one pending delivery per endpoint with the message snapshot and queues it', function () {
        Queue::fake();

        $telegram = deliveryTestEndpoint();
        $sms = deliveryTestEndpoint(['type' => EndpointType::SMS->value, 'value' => '0912']);
        $flow = deliveryTestEndpoint(['type' => EndpointType::FLOW->value, 'steps' => []]);

        $deliveries = app(NotificationDispatcher::class)->dispatch([
            new DeliveryTarget($telegram, NotifyMessagePayload::fromBody('templated', ['telegram' => ['message' => 'templated']]), templateApplied: true),
            new DeliveryTarget($sms, NotifyMessagePayload::fromBody('plain')),
            new DeliveryTarget($flow, NotifyMessagePayload::fromBody('ignored')),
        ], ['source' => NotificationDelivery::SOURCE_ALERT, 'notifyId' => 'notify-1', 'sourceId' => 'rule-1']);
        $this->deliveryIds = $deliveries->pluck('id')->all();

        expect($deliveries)->toHaveCount(2);

        $first = NotificationDelivery::query()->where('_id', $deliveries[0]->id)->first();

        expect($first->status)->toBe(NotificationDeliveryStatus::PENDING)
            ->and($first->endpointId)->toBe($telegram->id)
            ->and($first->endpointType)->toBe('telegram')
            ->and($first->userId)->toBe('delivery-test-user')
            ->and($first->notifyId)->toBe('notify-1')
            ->and($first->sourceId)->toBe('rule-1')
            ->and($first->templateApplied)->toBeTrue()
            ->and($first->maxAttempts)->toBe(3)
            ->and($first->message)->toBe(['body' => 'templated', 'overrides' => ['telegram' => ['message' => 'templated']]])
            ->and($first)->not->toHaveKey('botToken');

        Queue::assertPushed(SendNotificationDeliveryJob::class, 2);
        Queue::assertPushedOn('sendNotifies', SendNotificationDeliveryJob::class);
    });

    it('does not record or send anything while applying replicated state', function () {
        Queue::fake();

        $deliveries = HaReplicationContext::apply(fn () => dispatchToEndpoints([deliveryTestEndpoint()]));

        expect($deliveries)->toBeEmpty();
        Queue::assertNothingPushed();
    });

    it('marks a delivery sent and logs the attempt', function () {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 5]])]);

        $delivery = dispatchToEndpoints([deliveryTestEndpoint()])->first();
        $delivery = runDeliveryJob($delivery->id);

        expect($delivery->status)->toBe(NotificationDeliveryStatus::SENT)
            ->and($delivery->attempts)->toBe(1)
            ->and($delivery->nextRetryAt)->toBeNull()
            ->and($delivery->attemptLog)->toHaveCount(1)
            ->and($delivery->attemptLog[0]['status'])->toBe('sent')
            ->and($delivery->attemptLog[0]['providerMessageId'])->toBe('5')
            ->and($delivery->attemptLog[0]['trigger'])->toBe('auto')
            ->and($delivery->attemptLog[0])->toHaveKeys(['at', 'durationMs', 'httpStatus', 'response']);

        Http::assertSentCount(1);
    });

    it('re-queues retryable failures with backoff until attempts run out', function () {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Too Many Requests'], 429)]);

        $delivery = dispatchToEndpoints([deliveryTestEndpoint()])->first();
        Queue::fake();

        $delivery = runDeliveryJob($delivery->id);

        expect($delivery->status)->toBe(NotificationDeliveryStatus::FAILED)
            ->and($delivery->attempts)->toBe(1)
            ->and($delivery->nextRetryAt)->not->toBeNull()
            ->and(now()->diffInSeconds($delivery->nextRetryAt))->toBeGreaterThan(25);
        Queue::assertPushed(SendNotificationDeliveryJob::class, fn ($job) => $job->deliveryId === $delivery->id && $job->delay !== null);

        runDeliveryJob($delivery->id);
        Queue::fake();
        $delivery = runDeliveryJob($delivery->id);

        expect($delivery->attempts)->toBe(3)
            ->and($delivery->status)->toBe(NotificationDeliveryStatus::FAILED)
            ->and($delivery->nextRetryAt)->toBeNull()
            ->and($delivery->lastError)->toBe('Too Many Requests')
            ->and($delivery->attemptLog)->toHaveCount(3);
        Queue::assertNothingPushed();
    });

    it('does not retry permanent failures', function () {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);

        $delivery = dispatchToEndpoints([deliveryTestEndpoint()])->first();
        Queue::fake();

        $delivery = runDeliveryJob($delivery->id);

        expect($delivery->status)->toBe(NotificationDeliveryStatus::FAILED)
            ->and($delivery->nextRetryAt)->toBeNull();
        Queue::assertNothingPushed();
    });

    it('fails without retry when the endpoint was deleted', function () {
        Queue::fake();

        $endpoint = deliveryTestEndpoint();
        $delivery = dispatchToEndpoints([$endpoint])->first();
        Endpoint::withoutEvents(fn () => $endpoint->delete());

        $delivery = runDeliveryJob($delivery->id);

        expect($delivery->status)->toBe(NotificationDeliveryStatus::FAILED)
            ->and($delivery->lastError)->toBe('Endpoint no longer exists')
            ->and($delivery->nextRetryAt)->toBeNull();
        Http::assertNothingSent();
    });

    it('skips deliveries once the alert rule has been acknowledged', function (string $notifyType, NotificationDeliveryStatus $expected) {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $alertRule = AlertRule::withoutEvents(fn () => AlertRule::create([
            'name' => 'Ack Rule '.uniqid(),
            'acknowledgedBy' => 'someone',
        ]));
        $this->alertRuleIds[] = $alertRule->id;

        $notify = Notify::create(['type' => $notifyType, 'alertRuleId' => $alertRule->id]);
        $this->notifyIds[] = $notify->id;

        $delivery = dispatchToEndpoints([deliveryTestEndpoint()], context: ['notifyId' => $notify->id])->first();
        $delivery = runDeliveryJob($delivery->id);

        expect($delivery->status)->toBe($expected);
    })->with([
        'alert fire' => [SendNotifyJob::API_FIRE, NotificationDeliveryStatus::SKIPPED],
        'acknowledgement message' => [SendNotifyJob::ALERT_RULE_ACKNOWLEDGED, NotificationDeliveryStatus::SENT],
        'incident page' => [SendNotifyJob::INCIDENT_POLICY_PAGE, NotificationDeliveryStatus::SENT],
    ]);

    it('never sends a delivery twice', function () {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $delivery = dispatchToEndpoints([deliveryTestEndpoint()])->first();
        runDeliveryJob($delivery->id);
        $delivery = runDeliveryJob($delivery->id);

        expect($delivery->attempts)->toBe(1);
        Http::assertSentCount(1);
    });

    it('grants one more attempt on manual retry', function () {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)
            ->push(['ok' => true, 'result' => ['message_id' => 2]]),
        ]);

        $delivery = dispatchToEndpoints([deliveryTestEndpoint()])->first();
        $delivery = runDeliveryJob($delivery->id);

        expect($delivery->status)->toBe(NotificationDeliveryStatus::FAILED);

        Queue::fake();
        $delivery = app(NotificationDeliveryService::class)->retry($delivery);

        expect($delivery->status)->toBe(NotificationDeliveryStatus::PENDING)
            ->and($delivery->maxAttempts)->toBe(4);
        Queue::assertPushed(SendNotificationDeliveryJob::class, fn ($job) => $job->trigger === NotificationDeliveryTrigger::MANUAL);

        $delivery = runDeliveryJob($delivery->id, NotificationDeliveryTrigger::MANUAL);

        expect($delivery->status)->toBe(NotificationDeliveryStatus::SENT)
            ->and($delivery->attempts)->toBe(2)
            ->and($delivery->attemptLog[1]['trigger'])->toBe('manual');
    });

    it('counts sent and failed endpoints on the linked history row', function () {
        Queue::fake();
        Http::fake([
            'api.telegram.org/*' => Http::sequence()
                ->push(['ok' => true, 'result' => ['message_id' => 1]])
                ->push(['ok' => false, 'description' => 'Bad Request: chat not found'], 400),
        ]);

        $history = deliveryTestHistory();
        $deliveries = dispatchToEndpoints([
            deliveryTestEndpoint(['name' => 'On-call Telegram']),
            deliveryTestEndpoint(['name' => 'Ops email']),
        ], context: [
            'historyId' => (string) $history->id,
            'historyAlias' => 'apiAlertHistories',
        ]);

        runDeliveryJob($deliveries[0]->id);
        runDeliveryJob($deliveries[1]->id);

        expect(historyDeliveryCounts($history))->toBe(['sent' => 1, 'failed' => 1]);
    });

    it('does not count a repeated failure twice and moves the count when a retry succeeds', function () {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::sequence()
            ->push(['ok' => false, 'description' => 'Too Many Requests'], 429)
            ->push(['ok' => false, 'description' => 'Too Many Requests'], 429)
            ->push(['ok' => true, 'result' => ['message_id' => 9]]),
        ]);

        $history = deliveryTestHistory();
        $delivery = dispatchToEndpoints([deliveryTestEndpoint()], context: [
            'historyId' => (string) $history->id,
            'historyAlias' => 'apiAlertHistories',
        ])->first();

        runDeliveryJob($delivery->id);

        expect(historyDeliveryCounts($history))->toBe(['sent' => 0, 'failed' => 1]);

        runDeliveryJob($delivery->id);

        expect(historyDeliveryCounts($history))->toBe(['sent' => 0, 'failed' => 1]);

        Queue::fake();
        $delivery = app(NotificationDeliveryService::class)->retry($delivery->fresh());

        expect($delivery->status)->toBe(NotificationDeliveryStatus::PENDING)
            ->and(historyDeliveryCounts($history))->toBe(['sent' => 0, 'failed' => 0]);

        $delivery = runDeliveryJob($delivery->id, NotificationDeliveryTrigger::MANUAL);

        expect($delivery->status)->toBe(NotificationDeliveryStatus::SENT)
            ->and(historyDeliveryCounts($history))->toBe(['sent' => 1, 'failed' => 0]);
    });

    it('does not write history counts when the delivery has no history link', function () {
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $history = deliveryTestHistory();
        $delivery = runDeliveryJob(dispatchToEndpoints([deliveryTestEndpoint()])->first()->id);

        expect($delivery->countedStatus)->toBeNull()
            ->and(historyDeliveryCounts($history))->toBe(['sent' => 0, 'failed' => 0]);
    });

    it('leaves counts unchanged when a delivery is skipped', function () {
        Queue::fake();

        $history = deliveryTestHistory();
        $alertRule = AlertRule::withoutEvents(fn () => AlertRule::create([
            'name' => 'Ack Rule '.uniqid(),
            'acknowledgedBy' => 'someone',
        ]));
        $this->alertRuleIds[] = $alertRule->id;

        $notify = Notify::create(['type' => SendNotifyJob::API_FIRE, 'alertRuleId' => $alertRule->id]);
        $this->notifyIds[] = $notify->id;

        $delivery = dispatchToEndpoints([deliveryTestEndpoint()], context: [
            'notifyId' => $notify->id,
            'historyId' => (string) $history->id,
            'historyAlias' => 'apiAlertHistories',
        ])->first();

        $delivery = runDeliveryJob($delivery->id);

        expect($delivery->status)->toBe(NotificationDeliveryStatus::SKIPPED)
            ->and(historyDeliveryCounts($history))->toBe(['sent' => 0, 'failed' => 0]);
    });

    it('links a webhook document as its own history row', function () {
        Queue::fake();

        $webhook = SentryWebhookAlert::create([
            'alertRuleName' => 'Sentry link',
            'alertRuleId' => (string) new ObjectId,
            'action' => 'created',
            'message' => 'boom',
        ]);
        $this->sentryIds[] = $webhook->id;

        $notify = app(SendNotifyService::class)->createNotify(SendNotifyJob::SENTRY_WEBHOOK, $webhook, $webhook->alertRuleId);
        $this->notifyIds[] = $notify->id;

        $counts = $webhook->fresh()->deliveryCounts;

        expect((string) $notify->historyId)->toBe((string) $webhook->id)
            ->and($notify->historyAlias)->toBe('sentryWebhookAlerts')
            ->and((int) $counts['sent'])->toBe(0)
            ->and((int) $counts['failed'])->toBe(0);
    });
});
