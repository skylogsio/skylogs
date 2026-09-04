<?php

use App\Enums\Constants;
use App\Models\AlertRule;
use Tests\Support\TeamTestData;

describe('AlertingController Index sorting', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->tag = 'index-sort-'.uniqid();

        $this->apple = AlertRule::create([
            'name' => 'Apple Alert',
            'type' => 'api',
            'userId' => $this->owner->id,
            'tags' => [$this->tag],
        ]);

        $this->zebra = AlertRule::create([
            'name' => 'Zebra Alert',
            'type' => 'api',
            'userId' => $this->owner->id,
            'tags' => [$this->tag],
            'pinUserIds' => [$this->owner->id],
        ]);

        $this->mango = AlertRule::create([
            'name' => 'Mango Alert',
            'type' => 'api',
            'userId' => $this->owner->id,
            'tags' => [$this->tag],
        ]);
    });

    afterEach(function () {
        AlertRule::query()->where('tags', $this->tag)->delete();
        TeamTestData::deleteUser($this->owner);
    });

    it('uses pinned then id order when no sort query param is sent', function () {
        $names = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'tags' => $this->tag,
                'perPage' => 50,
            ]))
            ->assertSuccessful()
            ->json('data');

        expect(collect($names)->pluck('name')->all())->toBe([
            'Zebra Alert',
            'Apple Alert',
            'Mango Alert',
        ]);
    });

    it('sorts by name ascending when sortBy is name', function () {
        $names = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'tags' => $this->tag,
                'perPage' => 50,
                'sortBy' => 'name',
            ]))
            ->assertSuccessful()
            ->json('data');

        expect(collect($names)->pluck('name')->all())->toBe([
            'Apple Alert',
            'Mango Alert',
            'Zebra Alert',
        ]);
    });

    it('sorts by name descending when sortDir is desc', function () {
        $names = $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'tags' => $this->tag,
                'perPage' => 50,
                'sortBy' => 'name',
                'sortDir' => 'desc',
            ]))
            ->assertSuccessful()
            ->json('data');

        expect(collect($names)->pluck('name')->all())->toBe([
            'Zebra Alert',
            'Mango Alert',
            'Apple Alert',
        ]);
    });

    it('rejects an unsupported sortBy field', function () {
        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/alert-rule?'.http_build_query([
                'tags' => $this->tag,
                'sortBy' => 'state',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sortBy']);
    });
});
