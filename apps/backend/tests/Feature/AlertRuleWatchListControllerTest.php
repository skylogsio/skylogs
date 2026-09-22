<?php

use App\Enums\Constants;
use App\Models\AlertRule;
use Tests\Support\TeamTestData;

describe('AlertRuleWatchListController', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->tag = 'watch-'.uniqid();
        $this->alerts = [];
    });

    afterEach(function () {
        foreach ($this->alerts as $alert) {
            AlertRule::query()->where('_id', $alert->id)->delete();
        }
        TeamTestData::deleteUser($this->owner);
        TeamTestData::deleteUser($this->member);
    });

    it('toggles an alert onto and off the current user watch list', function () {
        $alert = createWatchListAlert($this->owner, $this->tag);
        $this->alerts[] = $alert;

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule/watch/'.$alert->id)
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('isWatched', true);

        expect($alert->fresh()->watchUserIds)->toContain($this->owner->id);

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule/watch/'.$alert->id)
            ->assertSuccessful()
            ->assertJsonPath('isWatched', false);

        expect($alert->fresh()->watchUserIds ?? [])->not->toContain($this->owner->id);
    });

    it('lets a member watch an organization-visible alert', function () {
        $alert = createWatchListAlert($this->owner, $this->tag, [
            'isPrivate' => false,
        ]);
        $this->alerts[] = $alert;

        $this->actingAs($this->member, 'api')
            ->postJson('/api/v1/alert-rule/watch/'.$alert->id)
            ->assertSuccessful()
            ->assertJsonPath('isWatched', true);
    });

    it('forbids watching a private alert the user cannot read', function () {
        $alert = createWatchListAlert($this->owner, $this->tag, [
            'isPrivate' => true,
        ]);
        $this->alerts[] = $alert;

        $this->actingAs($this->member, 'api')
            ->postJson('/api/v1/alert-rule/watch/'.$alert->id)
            ->assertForbidden();
    });

    it('lists watched alerts with their current states', function () {
        $critical = createWatchListAlert($this->owner, $this->tag, [
            'name' => 'CPU High',
            'state' => AlertRule::CRITICAL,
            'fireCount' => 3,
            'watchUserIds' => [$this->owner->id],
        ]);
        $resolved = createWatchListAlert($this->owner, $this->tag, [
            'name' => 'Redis Ping',
            'state' => AlertRule::RESOlVED,
            'fireCount' => 0,
            'watchUserIds' => [$this->owner->id],
        ]);
        $unwatched = createWatchListAlert($this->owner, $this->tag, [
            'name' => 'Unwatched',
        ]);
        $this->alerts = [$critical, $resolved, $unwatched];

        $response = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/watch-list')
            ->assertSuccessful()
            ->assertJsonStructure(laravelPaginatorStructure());

        $names = collect($response->json('data'))->pluck('name')->all();

        expect($names)->toBe(['CPU High', 'Redis Ping'])
            ->and($response->json('data.0.statusLabel'))->toBe(AlertRule::CRITICAL)
            ->and($response->json('data.0.statusCount'))->toBe(3)
            ->and($response->json('data.0.isWatched'))->toBeTrue()
            ->and($response->json('data.1.statusLabel'))->toBe(AlertRule::RESOlVED);
    });

    it('omits watched alerts the user can no longer read', function () {
        $alert = createWatchListAlert($this->owner, $this->tag, [
            'isPrivate' => true,
            'watchUserIds' => [$this->member->id],
        ]);
        $this->alerts[] = $alert;

        $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/alert-rule/watch-list')
            ->assertSuccessful()
            ->assertJsonPath('total', 0);
    });

    it('exposes isWatched on the alert rule list and show endpoints', function () {
        $alert = createWatchListAlert($this->owner, $this->tag, [
            'watchUserIds' => [$this->owner->id],
        ]);
        $this->alerts[] = $alert;

        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'tags' => $this->tag,
                'watchStatus' => 'watched',
            ]))
            ->assertSuccessful()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.isWatched', true);

        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/'.$alert->id)
            ->assertSuccessful()
            ->assertJsonPath('isWatched', true);
    });

    it('adds filtered alerts to the watch list with a group action', function () {
        $first = createWatchListAlert($this->owner, $this->tag, ['name' => 'First']);
        $second = createWatchListAlert($this->owner, $this->tag, ['name' => 'Second']);
        $this->alerts = [$first, $second];

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule/group-action/watch?tags='.$this->tag)
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        expect($first->fresh()->watchUserIds)->toContain($this->owner->id)
            ->and($second->fresh()->watchUserIds)->toContain($this->owner->id);

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule/group-action/unwatch?tags='.$this->tag)
            ->assertSuccessful();

        expect($first->fresh()->watchUserIds ?? [])->not->toContain($this->owner->id);
    });
});

/**
 * @param  array<string, mixed>  $attributes
 */
function createWatchListAlert($owner, string $tag, array $attributes = []): AlertRule
{
    return AlertRule::create(array_merge([
        'name' => 'watch-alert-'.uniqid(),
        'type' => 'api',
        'userId' => $owner->id,
        'tags' => [$tag],
    ], $attributes));
}
