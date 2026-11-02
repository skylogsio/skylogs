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
function createShowEndpoint(User $owner, array $overrides = []): Endpoint
{
    return Endpoint::create(array_merge([
        'userId' => $owner->id,
        'name' => 'Show '.uniqid(),
        'type' => EndpointType::TELEGRAM->value,
        'chatId' => '-100'.uniqid(),
        'isPublic' => false,
        'accessUserIds' => [],
        'accessTeamIds' => [],
    ], $overrides));
}

describe('EndpointController show', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        app(EndpointService::class)->flushCache();

        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->owner = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->admin = TeamTestData::createUser(Constants::ROLE_MANAGER);

        $this->team = TeamTestData::createTeam(
            $this->owner,
            [$this->owner->id, $this->member->id],
        );

        $this->ownedEndpoint = createShowEndpoint($this->member);
        $this->userSharedEndpoint = createShowEndpoint($this->owner, [
            'accessUserIds' => [$this->member->id],
        ]);
        $this->teamSharedEndpoint = createShowEndpoint($this->owner, [
            'accessTeamIds' => [$this->team->id],
        ]);
        $this->privateEndpoint = createShowEndpoint($this->owner);

        $this->createdEndpointIds = [
            $this->ownedEndpoint->id,
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

        foreach (['member', 'owner', 'admin'] as $property) {
            if (isset($this->{$property})) {
                TeamTestData::deleteUser($this->{$property});
            }
        }
    });

    it('lets the owner view their endpoint with action access', function () {
        $this->actingAs($this->member, 'api')
            ->getJson("/api/v1/endpoint/{$this->ownedEndpoint->id}")
            ->assertSuccessful()
            ->assertJsonPath('id', $this->ownedEndpoint->id)
            ->assertJsonPath('hasActionAccess', true);
    });

    it('lets a user view an endpoint shared {case} without action access', function (string $endpointProperty) {
        $endpoint = $this->{$endpointProperty};

        $this->actingAs($this->member, 'api')
            ->getJson("/api/v1/endpoint/{$endpoint->id}")
            ->assertSuccessful()
            ->assertJsonPath('id', $endpoint->id)
            ->assertJsonPath('chatId', $endpoint->chatId)
            ->assertJsonPath('hasActionAccess', false);
    })->with([
        'with the user directly' => 'userSharedEndpoint',
        'with one of the user teams' => 'teamSharedEndpoint',
    ]);

    it('hides endpoints that are not shared with the user', function () {
        $this->actingAs($this->member, 'api')
            ->getJson("/api/v1/endpoint/{$this->privateEndpoint->id}")
            ->assertNotFound();
    });

    it('lets admins view any endpoint', function () {
        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/endpoint/{$this->privateEndpoint->id}")
            ->assertSuccessful()
            ->assertJsonPath('id', $this->privateEndpoint->id)
            ->assertJsonPath('hasActionAccess', true);
    });
});
