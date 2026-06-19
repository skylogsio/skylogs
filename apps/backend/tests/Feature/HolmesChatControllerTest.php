<?php

use App\Enums\Constants;
use Illuminate\Support\Facades\Http;
use Tests\Support\TeamTestData;

describe('HolmesChatController', function () {
    beforeEach(function () {
        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
    });

    afterEach(function () {
        foreach (['owner', 'manager'] as $property) {
            if (isset($this->{$property})) {
                TeamTestData::deleteUser($this->{$property});
            }
        }
    });

    it('rejects unauthenticated requests', function () {
        $this->postJson('/api/v1/config/holmes/chat', [
            'ask' => 'What alerts are firing?',
        ])->assertUnauthorized();
    });

    it('rejects non-owner roles', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/config/holmes/chat', [
                'ask' => 'What alerts are firing?',
            ])
            ->assertForbidden();
    });

    it('returns service unavailable when holmes base url is not configured', function () {
        config(['holmes.base_url' => '']);

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/config/holmes/chat', [
                'ask' => 'What alerts are firing?',
            ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'HolmesGPT is not configured.');
    });

    it('validates missing ask field', function () {
        config(['holmes.base_url' => 'http://holmesgpt.test']);

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/config/holmes/chat', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ask']);
    });

    it('returns holmes analysis on success', function () {
        config([
            'holmes.base_url' => 'http://holmesgpt.test',
            'holmes.api_key' => 'test-api-key',
            'holmes.model' => 'fast-model',
        ]);

        Http::fake([
            'http://holmesgpt.test/api/chat' => Http::response([
                'analysis' => 'Two critical alerts are currently firing.',
                'conversation_history' => [
                    ['role' => 'user', 'content' => 'What alerts are firing?'],
                    ['role' => 'assistant', 'content' => 'Two critical alerts are currently firing.'],
                ],
            ]),
        ]);

        $this->actingAs($this->owner, 'api')
            ->postJson('/api/v1/config/holmes/chat', [
                'ask' => 'What alerts are firing?',
            ])
            ->assertSuccessful()
            ->assertJsonPath('analysis', 'Two critical alerts are currently firing.')
            ->assertJsonPath('conversationHistory.1.content', 'Two critical alerts are currently firing.');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://holmesgpt.test/api/chat'
                && $request['ask'] === 'What alerts are firing?'
                && $request['model'] === 'fast-model'
                && $request->hasHeader('X-API-Key', 'test-api-key');
        });
    });
});
