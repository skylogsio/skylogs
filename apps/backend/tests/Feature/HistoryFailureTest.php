<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use App\Models\NotificationDelivery;
use App\Models\Notify;
use App\Services\SendNotifyService;
use Illuminate\Support\Facades\Queue;
use MongoDB\BSON\ObjectId;
use Tests\Support\TeamTestData;

describe('history delivery failures', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->historyIds = [];
        $this->deliveryIds = [];
        $this->notifyIds = [];

        $this->alert = AlertRule::create([
            'name' => 'History Failure Alert',
            'type' => AlertRuleType::API,
            'userId' => $this->owner->id,
            'isPrivate' => false,
        ]);
    });

    afterEach(function () {
        NotificationDelivery::query()->whereIn('_id', $this->deliveryIds)->delete();
        Notify::query()->whereIn('_id', $this->notifyIds)->delete();
        ApiAlertHistory::query()->whereIn('_id', $this->historyIds)->delete();
        AlertRule::query()->where('_id', $this->alert->id)->delete();

        if (isset($this->privateAlert)) {
            ApiAlertHistory::query()->where('alertRuleId', $this->privateAlert->id)->delete();
            AlertRule::query()->where('_id', $this->privateAlert->id)->delete();
        }

        TeamTestData::deleteUser($this->owner);
        TeamTestData::deleteUser($this->member);
    });

    it('shows sent and failed counts on the history list', function () {
        Queue::fake();

        $history = ApiAlertHistory::create([
            'alertRuleId' => $this->alert->id,
            'alertRuleName' => $this->alert->name,
            'instance' => 'web-1',
            'state' => ApiAlertHistory::FIRE,
        ]);
        $this->historyIds[] = $history->id;

        $notify = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $this->alert,
            $this->alert->id,
            $history,
        );
        $this->notifyIds[] = $notify->id;

        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->alert->id)
            ->assertSuccessful()
            ->assertJsonPath('data.0.deliveryCounts.sent', 0)
            ->assertJsonPath('data.0.deliveryCounts.failed', 0);
    });

    it('returns only the failed endpoints and the short error', function () {
        $history = ApiAlertHistory::create([
            'alertRuleId' => $this->alert->id,
            'alertRuleName' => $this->alert->name,
            'state' => ApiAlertHistory::FIRE,
        ]);
        $this->historyIds[] = $history->id;

        $failed = NotificationDelivery::factory()->failed()->create([
            'historyId' => (string) $history->id,
            'endpointName' => 'Ops email',
            'endpointType' => EndpointType::EMAIL->value,
            'lastError' => 'SMTP connection timed out',
            'userId' => $this->owner->id,
        ]);
        $sent = NotificationDelivery::factory()->sent()->create([
            'historyId' => (string) $history->id,
            'endpointName' => 'On-call Telegram',
            'userId' => $this->owner->id,
        ]);
        $this->deliveryIds = [$failed->id, $sent->id];

        $response = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->alert->id.'/failures/'.$history->id)
            ->assertSuccessful()
            ->assertJsonCount(1, 'data');

        expect($response->json('data.0'))->toBe([
            'endpointId' => $failed->endpointId,
            'endpointName' => 'Ops email',
            'endpointType' => EndpointType::EMAIL->value,
            'lastError' => 'SMTP connection timed out',
        ]);
    });

    it('returns an empty list when nothing failed', function () {
        $history = ApiAlertHistory::create([
            'alertRuleId' => $this->alert->id,
            'state' => ApiAlertHistory::FIRE,
        ]);
        $this->historyIds[] = $history->id;

        $sent = NotificationDelivery::factory()->sent()->create([
            'historyId' => (string) $history->id,
        ]);
        $this->deliveryIds = [$sent->id];

        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->alert->id.'/failures/'.$history->id)
            ->assertSuccessful()
            ->assertJsonPath('data', []);
    });

    it('returns not found when the history row is not on that alert', function () {
        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->alert->id.'/failures/'.(string) new ObjectId)
            ->assertNotFound();
    });

    it('forbids a user who cannot read the alert', function () {
        $this->privateAlert = AlertRule::create([
            'name' => 'Private History Alert',
            'type' => AlertRuleType::API,
            'userId' => $this->owner->id,
            'isPrivate' => true,
        ]);

        $history = ApiAlertHistory::create([
            'alertRuleId' => $this->privateAlert->id,
            'state' => ApiAlertHistory::FIRE,
        ]);
        $this->historyIds[] = $history->id;

        $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/alert-rule/history/'.$this->privateAlert->id.'/failures/'.$history->id)
            ->assertForbidden();
    });
});
