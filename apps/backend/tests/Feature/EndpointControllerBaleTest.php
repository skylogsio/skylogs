<?php

use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Models\Endpoint;
use Tests\Support\TeamTestData;

describe('EndpointController bale type', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->user = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->createdEndpointIds = [];
    });

    afterEach(function () {
        if (! empty($this->createdEndpointIds)) {
            Endpoint::query()->whereIn('_id', $this->createdEndpointIds)->delete();
        }

        if (isset($this->user)) {
            TeamTestData::deleteUser($this->user);
        }
    });

    it('creates a bale endpoint with chatId and botToken', function () {
        $chatId = '-100'.uniqid();
        $botToken = 'bale-bot-token-'.uniqid();

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'Bale Endpoint '.uniqid(),
                'type' => EndpointType::BALE->value,
                'value' => $chatId,
                'botToken' => $botToken,
                'isPublic' => false,
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.type', EndpointType::BALE->value)
            ->assertJsonPath('data.chatId', $chatId)
            ->assertJsonPath('data.botToken', $botToken)
            ->assertJsonPath('data.userId', $this->user->id);

        $endpointId = $response->json('data.id') ?? $response->json('data._id');
        expect($endpointId)->not->toBeNull();
        $this->createdEndpointIds[] = $endpointId;

        $endpoint = Endpoint::query()->where('_id', $endpointId)->first();

        expect($endpoint)->not->toBeNull()
            ->and($endpoint->type)->toBe(EndpointType::BALE->value)
            ->and($endpoint->chatId)->toBe($chatId)
            ->and($endpoint->botToken)->toBe($botToken)
            ->and($endpoint->threadId ?? null)->toBeNull();
    });

    it('creates a bale endpoint without requiring threadId', function () {
        $chatId = '-200'.uniqid();

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'Bale No Thread '.uniqid(),
                'type' => EndpointType::BALE->value,
                'value' => $chatId,
                'botToken' => 'token-'.uniqid(),
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.type', EndpointType::BALE->value)
            ->assertJsonPath('data.chatId', $chatId);

        $endpointId = $response->json('data.id') ?? $response->json('data._id');
        $this->createdEndpointIds[] = $endpointId;

        $endpoint = Endpoint::query()->where('_id', $endpointId)->first();

        expect($endpoint->threadId ?? null)->toBeNull();
    });

    it('updates a bale endpoint chatId and botToken', function () {
        $endpoint = Endpoint::create([
            'userId' => $this->user->id,
            'name' => 'Bale Before Update '.uniqid(),
            'type' => EndpointType::BALE->value,
            'chatId' => '-300-old',
            'botToken' => 'old-token',
            'isPublic' => false,
            'accessUserIds' => [],
            'accessTeamIds' => [],
        ]);
        $this->createdEndpointIds[] = $endpoint->id;

        $newChatId = '-300-new-'.uniqid();
        $newBotToken = 'new-bale-token-'.uniqid();

        $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/endpoint/{$endpoint->id}", [
                'name' => 'Bale After Update',
                'type' => EndpointType::BALE->value,
                'value' => $newChatId,
                'botToken' => $newBotToken,
                'isPublic' => true,
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Bale After Update')
            ->assertJsonPath('data.type', EndpointType::BALE->value)
            ->assertJsonPath('data.chatId', $newChatId)
            ->assertJsonPath('data.botToken', $newBotToken)
            ->assertJsonPath('data.isPublic', true);

        $endpoint->refresh();

        expect($endpoint->name)->toBe('Bale After Update')
            ->and($endpoint->chatId)->toBe($newChatId)
            ->and($endpoint->botToken)->toBe($newBotToken)
            ->and($endpoint->isPublic)->toBeTrue();
    });

    it('lists bale endpoints in the index response', function () {
        $endpoint = Endpoint::create([
            'userId' => $this->user->id,
            'name' => 'Bale List '.uniqid(),
            'type' => EndpointType::BALE->value,
            'chatId' => '-400'.uniqid(),
            'botToken' => 'list-token',
            'isPublic' => false,
            'accessUserIds' => [],
            'accessTeamIds' => [],
        ]);
        $this->createdEndpointIds[] = $endpoint->id;

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/endpoint?name='.urlencode($endpoint->name))
            ->assertSuccessful();

        $match = collect($response->json('data'))->firstWhere('id', $endpoint->id);

        expect($match)->not->toBeNull()
            ->and($match['type'])->toBe(EndpointType::BALE->value)
            ->and($match['chatId'])->toBe($endpoint->chatId)
            ->and($match['hasActionAccess'])->toBeTrue();
    });

    it('rejects create when type is invalid', function () {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'Invalid Type',
                'type' => 'not-a-real-type',
                'value' => '123',
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', false);
    });
});
