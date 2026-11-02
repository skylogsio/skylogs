<?php

use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Enums\FlowEndpointStepType;
use App\Models\Endpoint;
use App\Services\EndpointService;
use Tests\Support\TeamTestData;

describe('EndpointController flow index', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        app(EndpointService::class)->flushCache();

        $this->owner = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->viewer = TeamTestData::createUser(Constants::ROLE_MEMBER);

        $this->privateEndpoint = Endpoint::create([
            'userId' => $this->owner->id,
            'name' => 'Owner Telegram '.uniqid(),
            'type' => EndpointType::TELEGRAM->value,
            'chatId' => '-100'.uniqid(),
            'accessUserIds' => [],
            'accessTeamIds' => [],
        ]);

        $this->sharedFlow = Endpoint::create([
            'userId' => $this->owner->id,
            'name' => 'Shared Flow '.uniqid(),
            'type' => EndpointType::FLOW->value,
            'accessUserIds' => [$this->viewer->id],
            'accessTeamIds' => [],
            'steps' => [
                ['type' => FlowEndpointStepType::ENDPOINT->value, 'endpointIds' => [$this->privateEndpoint->id]],
                ['type' => FlowEndpointStepType::WAIT->value, 'duration' => 5, 'timeUnit' => 'm'],
            ],
        ]);

        $this->createdEndpointIds = [$this->privateEndpoint->id, $this->sharedFlow->id];
    });

    afterEach(function () {
        Endpoint::query()->whereIn('_id', $this->createdEndpointIds)->delete();

        foreach (['owner', 'viewer'] as $property) {
            if (isset($this->{$property})) {
                TeamTestData::deleteUser($this->{$property});
            }
        }
    });

    it('lists a shared flow with its steps but without action access', function () {
        $flow = collect(
            $this->actingAs($this->viewer, 'api')
                ->getJson('/api/v1/endpoint/indexFlow')
                ->assertSuccessful()
                ->json('data')
        )->firstWhere('id', $this->sharedFlow->id);

        expect($flow)->not->toBeNull()
            ->and($flow['hasActionAccess'])->toBeFalse()
            ->and($flow['steps'])->toHaveCount(2);
    });

    it('includes the names of step endpoints the viewer cannot access, without their destinations', function () {
        $flow = collect(
            $this->actingAs($this->viewer, 'api')
                ->getJson('/api/v1/endpoint/indexFlow')
                ->assertSuccessful()
                ->json('data')
        )->firstWhere('id', $this->sharedFlow->id);

        expect($flow['stepEndpoints'])->toBe([
            [
                'id' => $this->privateEndpoint->id,
                'name' => $this->privateEndpoint->name,
                'type' => EndpointType::TELEGRAM->value,
            ],
        ]);
    });

    it('gives the owner action access to their flow', function () {
        $flow = collect(
            $this->actingAs($this->owner, 'api')
                ->getJson('/api/v1/endpoint/indexFlow')
                ->assertSuccessful()
                ->json('data')
        )->firstWhere('id', $this->sharedFlow->id);

        expect($flow['hasActionAccess'])->toBeTrue();
    });
});
