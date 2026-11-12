<?php

use App\Enums\EndpointType;
use App\Enums\NotificationDeliveryStatus;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\NotificationDelivery;
use App\Models\Notify;
use App\Models\User;
use App\Services\SendNotifyService;
use App\Services\UserService;
use App\Support\NotifyMessagePayload;
use Illuminate\Support\Facades\Http;

describe('SendNotifyService per-delivery isolation', function () {
    beforeEach(function () {
        config(['cache.default' => 'array', 'notification.max_attempts' => 1]);
        Http::preventStrayRequests();

        $admin = new User;
        $admin->id = 'isolation-admin';
        $userService = Mockery::mock(UserService::class);
        $userService->shouldReceive('admin')->andReturn($admin);
        app()->instance(UserService::class, $userService);

        $this->discord = Endpoint::withoutEvents(fn () => Endpoint::create([
            'userId' => 'isolation-user',
            'name' => 'Broken Discord',
            'type' => EndpointType::DISCORD->value,
            'value' => 'https://discord.test/hook',
        ]));
        $this->telegram = Endpoint::withoutEvents(fn () => Endpoint::create([
            'userId' => 'isolation-user',
            'name' => 'Working Telegram',
            'type' => EndpointType::TELEGRAM->value,
            'chatId' => '-100',
            'botToken' => 'token',
        ]));
        $this->alertRule = AlertRule::withoutEvents(fn () => AlertRule::create([
            'name' => 'Isolation Rule',
            'userId' => 'isolation-user',
            'endpointIds' => [$this->discord->id, $this->telegram->id],
            'silentUserIds' => [],
            'state' => AlertRule::CRITICAL,
        ]));
        $this->notify = Notify::create([
            'type' => SendNotifyJob::API_FIRE,
            'alertRuleId' => $this->alertRule->id,
            'alert' => [],
            'messages' => NotifyMessagePayload::fromBody('CPU high')->toArray(),
        ]);
    });

    afterEach(function () {
        NotificationDelivery::query()->where('notifyId', $this->notify->id)->delete();
        Notify::query()->where('_id', $this->notify->id)->delete();
        AlertRule::withoutEvents(fn () => $this->alertRule->delete());
        Endpoint::withoutEvents(fn () => Endpoint::query()->whereIn('_id', [$this->discord->id, $this->telegram->id])->delete());
    });

    it('records each endpoint separately so one failure does not stop the others', function () {
        Http::fake([
            'discord.test/*' => fn () => throw new RuntimeException('cURL error 6: Could not resolve host: discord'),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 3]]),
        ]);

        app(SendNotifyService::class)->SendMessage($this->notify);

        $deliveries = NotificationDelivery::query()
            ->where('notifyId', $this->notify->id)
            ->get()
            ->keyBy('endpointType');

        expect($deliveries)->toHaveCount(2)
            ->and($deliveries['discord']->status)->toBe(NotificationDeliveryStatus::FAILED)
            ->and($deliveries['discord']->lastError)->toBe('cURL error 6: Could not resolve host: discord')
            ->and($deliveries['telegram']->status)->toBe(NotificationDeliveryStatus::SENT)
            ->and($deliveries['telegram']->source)->toBe(NotificationDelivery::SOURCE_ALERT)
            ->and($deliveries['telegram']->sourceId)->toBe($this->alertRule->id);

        $stored = $this->notify->fresh();

        expect($stored->getAttributes())->not->toHaveKeys(['resultSms', 'resultTelegram', 'resultDiscords']);
    });
});
