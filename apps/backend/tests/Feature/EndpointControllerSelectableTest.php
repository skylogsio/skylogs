<?php

use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Models\Endpoint;
use App\Models\User;
use App\Services\EndpointService;
use Tests\Support\TeamTestData;

/**
 * @param  array<string, mixed>  $overrides
 */
function createSelectableEndpoint(User $owner, array $overrides = []): Endpoint
{
    return Endpoint::create(array_merge([
        'userId' => $owner->id,
        'name' => 'Selectable '.uniqid(),
        'type' => EndpointType::TELEGRAM->value,
        'chatId' => '-100'.uniqid(),
        'isPublic' => false,
        'accessUserIds' => [],
        'accessTeamIds' => [],
    ], $overrides));
}

describe('EndpointController selectable endpoints', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        app(EndpointService::class)->flushCache();

        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->stranger = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->admin = TeamTestData::createUser(Constants::ROLE_MANAGER);

        $this->team = TeamTestData::createTeam(
            $this->stranger,
            [$this->stranger->id, $this->member->id],
        );

        $this->ownedEndpoint = createSelectableEndpoint($this->member);
        $this->flowEndpoint = createSelectableEndpoint($this->member, [
            'name' => 'Selectable Flow '.uniqid(),
            'type' => EndpointType::FLOW->value,
            'steps' => [],
        ]);
        $this->userSharedEndpoint = createSelectableEndpoint($this->stranger, [
            'accessUserIds' => [$this->member->id],
        ]);
        $this->teamSharedEndpoint = createSelectableEndpoint($this->stranger, [
            'accessTeamIds' => [$this->team->id],
        ]);
        $this->privateEndpoint = createSelectableEndpoint($this->stranger);

        $this->createdEndpointIds = [
            $this->ownedEndpoint->id,
            $this->flowEndpoint->id,
            $this->userSharedEndpoint->id,
            $this->teamSharedEndpoint->id,
            $this->privateEndpoint->id,
        ];
    });

    afterEach(function () {
        if (! empty($this->createdEndpointIds)) {
            Endpoint::query()->whereIn('_id', $this->createdEndpointIds)->delete();
        }

        if (isset($this->team)) {
            TeamTestData::deleteTeam($this->team);
        }

        foreach (['member', 'stranger', 'admin'] as $property) {
            if (isset($this->{$property})) {
                TeamTestData::deleteUser($this->{$property});
            }
        }
    });

    it('rejects unauthenticated requests to {path}', function (string $path) {
        $this->getJson($path)->assertUnauthorized();
    })->with([
        'canonical' => '/api/v1/endpoint/selectableEndpoints',
        'alias' => '/api/v1/alert-rule/filter-endpoints',
    ]);

    it('returns a JSON array of endpoints the member may select from {path}', function (string $path) {
        $ids = collect(
            $this->actingAs($this->member, 'api')
                ->getJson($path)
                ->assertSuccessful()
                ->assertJsonIsArray()
                ->json()
        )->pluck('id')->all();

        expect($ids)
            ->toContain($this->ownedEndpoint->id)
            ->toContain($this->flowEndpoint->id)
            ->toContain($this->userSharedEndpoint->id)
            ->toContain($this->teamSharedEndpoint->id)
            ->not->toContain($this->privateEndpoint->id);
    })->with([
        'canonical' => '/api/v1/endpoint/selectableEndpoints',
        'alias' => '/api/v1/alert-rule/filter-endpoints',
    ]);

    it('returns every endpoint for admins', function () {
        $ids = collect(
            $this->actingAs($this->admin, 'api')
                ->getJson('/api/v1/endpoint/selectableEndpoints')
                ->assertSuccessful()
                ->assertJsonIsArray()
                ->json()
        )->pluck('id')->all();

        expect($ids)
            ->toContain($this->ownedEndpoint->id)
            ->toContain($this->privateEndpoint->id);
    });

    it('serves the same payload from the alert-rule filter-endpoints alias', function () {
        $canonical = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/endpoint/selectableEndpoints')
            ->assertSuccessful()
            ->json();

        $alias = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/alert-rule/filter-endpoints')
            ->assertSuccessful()
            ->json();

        expect($alias)->toEqual($canonical);
    });
});
