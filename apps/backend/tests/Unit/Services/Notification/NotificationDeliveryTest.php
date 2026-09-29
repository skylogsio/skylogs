<?php

use App\Enums\EndpointType;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationDeliveryTrigger;
use App\Jobs\SendNotificationDeliveryJob;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\NotificationDelivery;
use App\Models\Notify;
use App\Services\Ha\HaReplicationContext;
use App\Services\Notification\DeliveryTarget;
use App\Services\Notification\NotificationDeliveryService;
use App\Services\Notification\NotificationDispatcher;
use App\Support\NotifyMessagePayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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
    });

    afterEach(function () {
        NotificationDelivery::query()->whereIn('_id', $this->deliveryIds)->delete();
        Endpoint::withoutEvents(fn () => Endpoint::query()->whereIn('_id', $this->endpointIds)->delete());
        Notify::query()->whereIn('_id', $this->notifyIds)->delete();
        AlertRule::withoutEvents(fn () => AlertRule::query()->whereIn('_id', $this->alertRuleIds)->delete());
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
});
