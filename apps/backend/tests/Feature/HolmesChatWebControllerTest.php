<?php

use App\Enums\Constants;
use Illuminate\Support\Facades\Http;
use Tests\Support\TeamTestData;

describe('HolmesChatWebController', function () {
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

    it('shows the login page for guests', function () {
        $this->get('/holmes-chat')
            ->assertSuccessful()
            ->assertSee('Holmes Chat')
            ->assertSee('Sign in');
    });

    it('logs in owner users and shows the chat page', function () {
        $this->post('/holmes-chat/login', [
            'username' => $this->owner->username,
            'password' => 'password',
        ])->assertRedirect(route('holmes-chat.index'));

        $this->get('/holmes-chat')
            ->assertSuccessful()
            ->assertSee($this->owner->name)
            ->assertDontSee('Sign in');
    });

    it('rejects non-owner users on login', function () {
        $this->post('/holmes-chat/login', [
            'username' => $this->manager->username,
            'password' => 'password',
        ])->assertSessionHasErrors('username');
    });

    it('sends chat messages for authenticated owners', function () {
        config([
            'holmes.base_url' => 'http://holmesgpt.test',
            'holmes.api_key' => null,
            'holmes.model' => null,
        ]);

        Http::fake([
            'http://holmesgpt.test/api/chat' => Http::response([
                'analysis' => 'No critical alerts are firing.',
                'conversation_history' => [
                    ['role' => 'user', 'content' => 'Any alerts?'],
                    ['role' => 'assistant', 'content' => 'No critical alerts are firing.'],
                ],
            ]),
        ]);

        $this->withSession(['holmes_chat_user_id' => $this->owner->id])
            ->postJson('/holmes-chat/send', [
                'ask' => 'Any alerts?',
            ])
            ->assertSuccessful()
            ->assertJsonPath('analysis', 'No critical alerts are firing.');
    });

    it('rejects unauthenticated chat requests', function () {
        $this->postJson('/holmes-chat/send', [
            'ask' => 'Any alerts?',
        ])->assertUnauthorized();
    });
});
