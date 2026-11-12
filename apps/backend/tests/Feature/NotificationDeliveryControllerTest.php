<?php

use App\Enums\Constants;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationDeliveryTrigger;
use App\Jobs\SendNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\Queue;
use MongoDB\BSON\ObjectId;
use Tests\Support\TeamTestData;

describe('NotificationDeliveryController', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->notifyId = (string) new ObjectId;

        $this->own = NotificationDelivery::factory()->sent()->create([
            'userId' => $this->member->id,
            'notifyId' => $this->notifyId,
        ]);
        $this->ownFailed = NotificationDelivery::factory()->failed()->create([
            'userId' => $this->member->id,
            'notifyId' => $this->notifyId,
        ]);
        $this->foreign = NotificationDelivery::factory()->failed()->create([
            'notifyId' => $this->notifyId,
        ]);
    });

    afterEach(function () {
        NotificationDelivery::query()->where('notifyId', $this->notifyId)->delete();
        TeamTestData::deleteUser($this->member);
        TeamTestData::deleteUser($this->manager);
    });

    it('lists only the caller\'s deliveries for non admins', function () {
        $response = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/notification-deliveries?notifyId='.$this->notifyId)
            ->assertSuccessful()
            ->assertJsonStructure(laravelPaginatorStructure());

        expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())
            ->toBe(collect([$this->own->id, $this->ownFailed->id])->sort()->values()->all());
    });

    it('lets admins see every delivery and filter by status', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/notification-deliveries?notifyId='.$this->notifyId.'&status=failed')
            ->assertSuccessful();

        expect(collect($response->json('data'))->pluck('id')->sort()->values()->all())
            ->toBe(collect([$this->ownFailed->id, $this->foreign->id])->sort()->values()->all());
    });

    it('rejects invalid filters', function () {
        $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/notification-deliveries?status=exploded&perPage=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'perPage']);
    });

    it('shows a delivery with its attempt log', function () {
        $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/notification-deliveries/'.$this->own->id)
            ->assertSuccessful()
            ->assertJsonPath('data.id', $this->own->id)
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.canRetry', false)
            ->assertJsonPath('data.attemptLog.0.httpStatus', 200)
            ->assertJsonPath('data.attemptLog.0.providerMessageId', '1');
    });

    it('forbids viewing someone else\'s delivery', function () {
        $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/notification-deliveries/'.$this->foreign->id)
            ->assertForbidden();
    });

    it('queues a manual retry for a failed delivery', function () {
        Queue::fake();

        $this->actingAs($this->member, 'api')
            ->postJson('/api/v1/notification-deliveries/'.$this->ownFailed->id.'/retry')
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.maxAttempts', 4);

        Queue::assertPushed(
            SendNotificationDeliveryJob::class,
            fn (SendNotificationDeliveryJob $job) => $job->deliveryId === $this->ownFailed->id
                && $job->trigger === NotificationDeliveryTrigger::MANUAL,
        );
    });

    it('refuses to retry a delivery that did not fail', function () {
        Queue::fake();

        $this->actingAs($this->member, 'api')
            ->postJson('/api/v1/notification-deliveries/'.$this->own->id.'/retry')
            ->assertUnprocessable();

        expect($this->own->fresh()->status)->toBe(NotificationDeliveryStatus::SENT);
        Queue::assertNothingPushed();
    });

    it('forbids retrying someone else\'s delivery', function () {
        Queue::fake();

        $this->actingAs($this->member, 'api')
            ->postJson('/api/v1/notification-deliveries/'.$this->foreign->id.'/retry')
            ->assertForbidden();

        Queue::assertNothingPushed();
    });
});
