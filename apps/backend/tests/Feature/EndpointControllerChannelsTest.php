<?php

use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Models\Endpoint;
use App\Models\EndpointOTP;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\TeamTestData;

describe('EndpointController channel driven validation', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);

        $this->user = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->phone = '0912'.random_int(1000000, 9999999);
    });

    afterEach(function () {
        Endpoint::query()->where('userId', $this->user->id)->delete();
        EndpointOTP::query()->where('value', $this->phone)->delete();
        TeamTestData::deleteUser($this->user);
    });

    it('stores telegram chat fields from the channel', function () {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'Ops Telegram',
                'type' => EndpointType::TELEGRAM->value,
                'value' => ' -100123 ',
                'threadId' => '7',
                'botToken' => 'tg-token',
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.chatId', '-100123')
            ->assertJsonPath('data.threadId', '7')
            ->assertJsonPath('data.botToken', 'tg-token');

        $endpoint = Endpoint::query()->where('_id', $response->json('data.id'))->first();

        expect($endpoint->getAttributes())->not->toHaveKey('value')
            ->and($endpoint->isPublic)->toBeFalse()
            ->and($endpoint->accessUserIds)->toBe([]);
    });

    it('rejects input the channel does not accept with the legacy status false shape', function (array $payload, string $field) {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint', ['name' => 'Bad', ...$payload])
            ->assertSuccessful()
            ->assertJsonPath('status', false)
            ->assertJsonStructure(['errors' => [$field]]);

        expect(Endpoint::query()->where('userId', $this->user->id)->exists())->toBeFalse();
    })->with([
        'webhook that is not a url' => [['type' => EndpointType::DISCORD->value, 'value' => 'not a url'], 'value'],
        'email that is not an address' => [['type' => EndpointType::EMAIL->value, 'value' => 'nope'], 'value'],
        'chat without a chat id' => [['type' => EndpointType::BALE->value], 'value'],
        'unknown type' => [['type' => 'pager', 'value' => 'x'], 'type'],
    ]);

    it('requires a valid otp before saving a phone endpoint', function () {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'My SMS',
                'type' => EndpointType::SMS->value,
                'value' => $this->phone,
                'otpCode' => '11111',
            ])
            ->assertStatus(422);

        EndpointOTP::create([
            'type' => EndpointType::SMS->value,
            'value' => $this->phone,
            'otpCode' => 22222,
            'expiredAt' => Carbon::now()->addMinutes(3),
        ]);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'My SMS',
                'type' => EndpointType::SMS->value,
                'value' => $this->phone,
                'otpCode' => '11111',
            ])
            ->assertStatus(422);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'My SMS',
                'type' => EndpointType::SMS->value,
                'value' => $this->phone,
                'otpCode' => '22222',
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.value', $this->phone);
    });

    it('does not ask for an otp when a phone endpoint keeps its number', function () {
        $endpoint = Endpoint::create([
            'userId' => $this->user->id,
            'name' => 'Old name',
            'type' => EndpointType::SMS->value,
            'value' => $this->phone,
        ]);

        $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/endpoint/{$endpoint->id}", [
                'name' => 'New name',
                'type' => EndpointType::SMS->value,
                'value' => $this->phone,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'New name');
    });

    it('creates flows and rejects malformed steps', function () {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'Broken flow',
                'type' => EndpointType::FLOW->value,
                'steps' => [['type' => 'wait', 'timeUnit' => 'y', 'duration' => 1]],
            ])
            ->assertStatus(422);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint', [
                'name' => 'Escalation',
                'type' => EndpointType::FLOW->value,
                'steps' => [['type' => 'wait', 'timeUnit' => 'm', 'duration' => 5]],
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.steps.0.duration', 5);
    });

    it('sends an otp through the channel and stores the provider result', function () {
        config(['variables.kavenegarToken' => 'otp-token']);
        Http::fake(['api.kavenegar.com/*' => Http::response([
            'return' => ['status' => 200, 'message' => 'ok'],
            'entries' => [['messageid' => 99]],
        ])]);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint/sendOTP', [
                'type' => EndpointType::SMS->value,
                'value' => $this->phone,
            ])
            ->assertSuccessful()
            ->assertJsonPath('message', 'OTP code has been sent to your endpoint');

        $otp = EndpointOTP::query()->where('value', $this->phone)->first();

        Http::assertSent(fn ($request) => $request['receptor'] === $this->phone
            && str_contains($request['message'], (string) $otp->otpCode));

        expect($otp->result['status'])->toBe('sent')
            ->and($otp->result['providerMessageId'])->toBe('99');
    });

    it('only sends otps for channels that verify ownership', function () {
        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint/sendOTP', [
                'type' => EndpointType::TELEGRAM->value,
                'value' => '123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });
});
