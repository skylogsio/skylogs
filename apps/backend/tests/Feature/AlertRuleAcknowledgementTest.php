<?php

use App\Enums\Constants;
use App\Models\AlertRule;
use App\Models\Notify;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TeamTestData;

function acknowledgementRule(User $owner, string $tag, array $overrides = []): AlertRule
{
    return AlertRule::create([
        'name' => 'Ack '.uniqid(),
        'type' => 'api',
        'userId' => $owner->id,
        'tags' => [$tag],
        'state' => AlertRule::CRITICAL,
        'fireCount' => 1,
        'isPrivate' => false,
        ...$overrides,
    ]);
}

describe('Alert rule acknowledgement', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-29 16:37:00'));

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->tag = 'ack-'.uniqid();
    });

    afterEach(function () {
        Carbon::setTestNow();

        $rules = AlertRule::query()->where('tags', $this->tag)->get();

        foreach ($rules as $rule) {
            Notify::query()->where('alertRuleId', $rule->_id)->delete();
            $rule->delete();
        }

        TeamTestData::deleteUser($this->member);
        TeamTestData::deleteUser($this->owner);
    });

    it('stores the acknowledge time and returns it from index and show', function () {
        $rule = acknowledgementRule($this->owner, $this->tag);

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/alert-rule/acknowledge/'.$rule->id)
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $rule->refresh();

        expect((string) $rule->acknowledgedBy)->toBe((string) $this->owner->id)
            ->and($rule->acknowledgedAt)->not->toBeNull()
            ->and($rule->acknowledgedAt->equalTo(now()))->toBeTrue();

        $show = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/'.$rule->id)
            ->assertSuccessful()
            ->json();

        expect($show['isAcknowledged'])->toBeTrue()
            ->and($show['acknowledgedBy'])->toBe((string) $this->owner->id)
            ->and($show['acknowledgedByName'])->toBe($this->owner->name)
            ->and(Carbon::parse($show['acknowledgedAt'])->equalTo(now()))->toBeTrue();

        $listed = collect($this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'tags' => $this->tag,
                'perPage' => 50,
            ]))
            ->assertSuccessful()
            ->json('data'))->firstWhere('name', $rule->name);

        expect($listed)->not->toBeNull()
            ->and($listed['isAcknowledged'])->toBeTrue()
            ->and($listed['acknowledgedBy'])->toBe((string) $this->owner->id)
            ->and($listed['acknowledgedByName'])->toBe($this->owner->name)
            ->and(Carbon::parse($listed['acknowledgedAt'])->equalTo(now()))->toBeTrue();
    });

    it('returns an empty acknowledgement when the alert is not acknowledged', function () {
        $rule = acknowledgementRule($this->owner, $this->tag, [
            'state' => AlertRule::RESOlVED,
            'fireCount' => 0,
        ]);

        $show = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/'.$rule->id)
            ->assertSuccessful()
            ->json();

        expect($show['isAcknowledged'])->toBeFalse()
            ->and($show['acknowledgedBy'])->toBeNull()
            ->and($show['acknowledgedByName'])->toBeNull()
            ->and($show['acknowledgedAt'])->toBeNull();

        $listed = collect($this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'tags' => $this->tag,
                'perPage' => 50,
            ]))
            ->assertSuccessful()
            ->json('data'))->firstWhere('name', $rule->name);

        expect($listed['isAcknowledged'])->toBeFalse()
            ->and($listed['acknowledgedBy'])->toBeNull()
            ->and($listed['acknowledgedByName'])->toBeNull()
            ->and($listed['acknowledgedAt'])->toBeNull();
    });

    it('keeps an older acknowledgement that has no stored time', function () {
        $rule = acknowledgementRule($this->owner, $this->tag, [
            'acknowledgedBy' => $this->owner->id,
        ]);

        $show = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule/'.$rule->id)
            ->assertSuccessful()
            ->json();

        expect($show['isAcknowledged'])->toBeTrue()
            ->and($show['acknowledgedBy'])->toBe((string) $this->owner->id)
            ->and($show['acknowledgedByName'])->toBe($this->owner->name)
            ->and($show['acknowledgedAt'])->toBeNull();
    });

    it('shows acknowledgement to a readonly viewer', function () {
        $rule = acknowledgementRule($this->owner, $this->tag, [
            'acknowledgedBy' => $this->owner->id,
            'acknowledgedAt' => now(),
        ]);

        $show = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/alert-rule/'.$rule->id)
            ->assertSuccessful()
            ->json();

        expect($show['accessLevel'])->toBe('readonly')
            ->and($show['isAcknowledged'])->toBeTrue()
            ->and($show['acknowledgedBy'])->toBe((string) $this->owner->id)
            ->and($show['acknowledgedByName'])->toBe($this->owner->name)
            ->and(Carbon::parse($show['acknowledgedAt'])->equalTo(now()))->toBeTrue();

        $listed = collect($this->actingAs($this->member, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'scope' => 'organization',
                'tags' => $this->tag,
                'perPage' => 50,
            ]))
            ->assertSuccessful()
            ->json('data'))->firstWhere('name', $rule->name);

        expect($listed['accessLevel'])->toBe('readonly')
            ->and($listed['isAcknowledged'])->toBeTrue()
            ->and($listed['acknowledgedBy'])->toBe((string) $this->owner->id)
            ->and($listed['acknowledgedByName'])->toBe($this->owner->name)
            ->and(Carbon::parse($listed['acknowledgedAt'])->equalTo(now()))->toBeTrue();
    });

    it('clears the acknowledge time when acknowledgement is removed', function () {
        $rule = acknowledgementRule($this->owner, $this->tag, [
            'acknowledgedBy' => $this->owner->id,
            'acknowledgedAt' => now(),
        ]);

        $rule->removeAcknowledge();
        $rule->refresh();

        expect($rule->acknowledgedBy)->toBeNull()
            ->and($rule->acknowledgedAt)->toBeNull();
    });
});
