<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Enums\NotificationDeliveryStatus;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\NotificationDelivery;
use App\Models\Notify;
use App\Models\PrometheusCheck;
use App\Models\User;
use App\Services\NotifyMessageComposer;
use App\Services\SendNotifyService;
use App\Services\UserService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\TeamTestData;

describe('sample endpoint notify with rules', function () {
    beforeEach(function () {
        config(['cache.default' => 'array', 'mail.default' => 'array', 'notification.max_attempts' => 1]);
        Http::preventStrayRequests();
        Mail::mailer('array')->getSymfonyTransport()->messages()->splice(0);

        $admin = new User;
        $admin->id = 'sample-admin';
        $users = Mockery::mock(UserService::class);
        $users->shouldReceive('admin')->andReturn($admin);
        app()->instance(UserService::class, $users);

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->muted = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->endpointIds = [];
        $this->ruleIds = [];
        $this->notifyIds = [];

        $this->telegram = sampleEndpoint($this->owner, EndpointType::TELEGRAM, [
            'chatId' => '-100900',
            'botToken' => 'sample-bot',
        ]);
        $this->email = sampleEndpoint($this->owner, EndpointType::EMAIL, [
            'value' => 'oncall@example.test',
        ]);
        $this->discord = sampleEndpoint($this->owner, EndpointType::DISCORD, [
            'value' => 'https://hooks.example.test/discord',
        ]);
        $this->sms = sampleEndpoint($this->muted, EndpointType::SMS, [
            'value' => '09120000000',
        ]);
        $this->flow = sampleEndpoint($this->owner, EndpointType::FLOW, [
            'steps' => [[
                'type' => 'endpoint',
                'endpointIds' => [$this->telegram->id],
            ]],
        ]);
    });

    afterEach(function () {
        NotificationDelivery::query()->whereIn('notifyId', $this->notifyIds)->delete();
        Notify::query()->whereIn('_id', $this->notifyIds)->delete();
        AlertRule::withoutEvents(fn () => AlertRule::query()->whereIn('_id', $this->ruleIds)->delete());
        Endpoint::withoutEvents(fn () => Endpoint::query()->whereIn('_id', $this->endpointIds)->delete());
        TeamTestData::deleteUser($this->owner);
        TeamTestData::deleteUser($this->muted);
    });

    it('sends the template only to its endpoints and adds endpoints from a matching notification rule', function () {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 11]]),
            'hooks.example.test/*' => Http::response('', 204),
        ]);

        $rule = sampleRule($this->owner, [$this->telegram->id, $this->email->id, $this->sms->id], [
            sampleNotificationRule([$this->discord->id], ['severity' => 'critical']),
            sampleTemplateRule([$this->email->id], '{{name}} severity {{label.severity}}'),
            sampleSilentRule(now()->addHour()->getTimestamp(), now()->addHours(2)->getTimestamp()),
        ], silentUserIds: [$this->muted->id]);

        $notify = sampleNotify($rule, 'critical');
        app(SendNotifyService::class)->SendMessage($notify);

        $deliveries = NotificationDelivery::query()->where('notifyId', $notify->id)->get()->keyBy('endpointId');

        expect($deliveries)->toHaveCount(3)
            ->and($deliveries->keys()->all())->not->toContain($this->sms->id);

        expect($deliveries[$this->email->id]->templateApplied)->toBeTrue()
            ->and($deliveries[$this->email->id]->status)->toBe(NotificationDeliveryStatus::SENT)
            ->and($deliveries[$this->email->id]->message['body'])->toBe('CPU Alert severity critical');

        expect($deliveries[$this->telegram->id]->templateApplied)->toBeFalse()
            ->and($deliveries[$this->telegram->id]->status)->toBe(NotificationDeliveryStatus::SENT)
            ->and($deliveries[$this->telegram->id]->message['body'])->not->toBe('CPU Alert severity critical')
            ->and($deliveries[$this->telegram->id]->message['overrides']['telegram']['meta'][0]['text'])->toBe('Acknowledge');

        expect($deliveries[$this->discord->id]->templateApplied)->toBeFalse()
            ->and($deliveries[$this->discord->id]->status)->toBe(NotificationDeliveryStatus::SENT)
            ->and($deliveries[$this->discord->id]->attemptLog[0]['response'])->toBeNull();

        $mail = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

        expect($mail->getTo()[0]->getAddress())->toBe('oncall@example.test')
            ->and($mail->getTextBody())->toBe('CPU Alert severity critical');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org')
            && $request['text'] !== 'CPU Alert severity critical'
            && $request['reply_markup']['inline_keyboard'][0][0]['text'] === 'Acknowledge');

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.test/discord'
            && $request['content'] !== 'CPU Alert severity critical');
    });

    it('does not add the extra endpoint when the notification rule misses', function () {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);

        $rule = sampleRule($this->owner, [$this->telegram->id], [
            sampleNotificationRule([$this->discord->id], ['severity' => 'critical']),
        ]);

        $notify = sampleNotify($rule, 'warning');
        app(SendNotifyService::class)->SendMessage($notify);

        $types = NotificationDelivery::query()->where('notifyId', $notify->id)->pluck('endpointType');

        expect($types->all())->toBe(['telegram']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'hooks.example.test'));
    });

    it('sends nothing while a silent window is open', function () {
        Http::fake();

        $rule = sampleRule($this->owner, [$this->telegram->id, $this->email->id], [
            sampleSilentRule(now()->subMinute()->getTimestamp(), now()->addHour()->getTimestamp()),
            sampleTemplateRule([$this->email->id], '{{name}} should not send'),
        ]);

        $notify = sampleNotify($rule, 'critical');
        app(SendNotifyService::class)->SendMessage($notify);

        expect($notify->fresh()->status)->toBe(Notify::STATUS_SILENT)
            ->and(NotificationDelivery::query()->where('notifyId', $notify->id)->count())->toBe(0);

        Http::assertNothingSent();
    });

    it('runs a flow step and stores the delivery ids on the notify', function () {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 4]]),
        ]);

        $rule = sampleRule($this->owner, [$this->flow->id, $this->telegram->id], []);
        $notify = sampleNotify($rule, 'critical');

        app(SendNotifyService::class)->SendMessage($notify);

        $flowDeliveries = NotificationDelivery::query()
            ->where('notifyId', $notify->id)
            ->where('source', 'flow')
            ->get();

        $loggedIds = $notify->fresh()->resultFlows[$this->flow->id][0]['deliveryIds'] ?? [];

        expect($flowDeliveries)->toHaveCount(1)
            ->and($flowDeliveries->first()->endpointId)->toBe($this->telegram->id)
            ->and($flowDeliveries->first()->status)->toBe(NotificationDeliveryStatus::SENT)
            ->and($loggedIds)->toBe([(string) $flowDeliveries->first()->id]);
    });
});

function sampleEndpoint(User $user, EndpointType $type, array $fields): Endpoint
{
    $endpoint = Endpoint::withoutEvents(fn () => Endpoint::create([
        'userId' => $user->id,
        'name' => $type->value.' '.uniqid(),
        'type' => $type->value,
        'accessUserIds' => [],
        'accessTeamIds' => [],
        ...$fields,
    ]));

    test()->endpointIds = [...test()->endpointIds, $endpoint->id];

    return $endpoint;
}

/**
 * @param  list<string>  $endpointIds
 * @param  list<array<string, mixed>>  $rules
 * @param  list<string>  $silentUserIds
 */
function sampleRule(User $owner, array $endpointIds, array $rules, array $silentUserIds = []): AlertRule
{
    $rule = AlertRule::withoutEvents(fn () => AlertRule::create([
        'name' => 'CPU Alert',
        'type' => AlertRuleType::PROMETHEUS->value,
        'userId' => $owner->id,
        'state' => AlertRule::CRITICAL,
        'showAcknowledgeBtn' => true,
        'endpointIds' => $endpointIds,
        'silentUserIds' => $silentUserIds,
        'rules' => $rules,
    ]));

    test()->ruleIds = [...test()->ruleIds, $rule->id];

    return $rule;
}

/**
 * @param  list<string>  $endpointIds
 * @param  array<string, string>  $filters
 * @return array<string, mixed>
 */
function sampleNotificationRule(array $endpointIds, array $filters): array
{
    return [
        'id' => 'notify-'.uniqid(),
        'type' => 'notification',
        'filters' => $filters,
        'endpointIds' => $endpointIds,
    ];
}

/**
 * @param  list<string>  $endpointIds
 * @return array<string, mixed>
 */
function sampleTemplateRule(array $endpointIds, string $template): array
{
    return [
        'id' => 'template-'.uniqid(),
        'type' => 'template',
        'endpointIds' => $endpointIds,
        'template' => $template,
    ];
}

/**
 * @return array<string, mixed>
 */
function sampleSilentRule(int $startsAt, int $endsAt): array
{
    return [
        'id' => 'silent-'.uniqid(),
        'type' => 'silent',
        'startsAt' => $startsAt,
        'endsAt' => $endsAt,
    ];
}

function sampleNotify(AlertRule $rule, string $severity): Notify
{
    $check = PrometheusCheck::withoutEvents(function () use ($rule, $severity) {
        $model = new PrometheusCheck;
        $model->forceFill([
            'state' => PrometheusCheck::FIRE,
            'alertRuleId' => $rule->id,
            'alertRuleName' => $rule->name,
            'alerts' => [[
                'skylogsStatus' => PrometheusCheck::FIRE,
                'labels' => ['severity' => $severity, 'pod' => 'api-1'],
                'annotations' => ['summary' => 'cpu'],
            ]],
        ]);

        return $model;
    });
    $check->setRelation('alertRule', $rule);

    $notify = Notify::create([
        'type' => SendNotifyJob::PROMETHEUS_FIRE,
        'alertRuleId' => $rule->id,
        'alert' => $check->toArray(),
        'messages' => NotifyMessageComposer::fromMessageable($check)->toArray(),
        'status' => Notify::STATUS_CREATED,
    ]);
    $notify->setRelation('alertRule', $rule);

    test()->notifyIds = [...test()->notifyIds, $notify->id];

    return $notify;
}
