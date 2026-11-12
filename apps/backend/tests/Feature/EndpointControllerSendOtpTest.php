<?php

use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Models\EndpointOTP;
use App\Services\ConfigSmsService;
use Illuminate\Support\Facades\Http;
use Tests\Support\TeamTestData;

use function Pest\Laravel\mock;

describe('EndpointController send OTP', function () {
    beforeEach(function () {
        config([
            'cache.default' => 'array',
            'jwt.secret' => 'testing-secret-key-for-endpoint-otp',
            'variables.kavenegarToken' => 'test-token',
            'variables.kavenegarSenderNumber' => '10007891',
        ]);

        $this->user = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->phone = '0912'.random_int(1000000, 9999999);
    });

    afterEach(function () {
        EndpointOTP::query()->where('value', $this->phone)->delete();

        if (isset($this->user)) {
            TeamTestData::deleteUser($this->user);
        }
    });

    it('sends a call endpoint otp through sms', function () {
        mock(ConfigSmsService::class)
            ->shouldReceive('getDefault')
            ->andReturn(null);

        Http::preventStrayRequests();
        Http::fake([
            'api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]], 200),
        ]);

        $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/endpoint/sendOTP', [
                'type' => EndpointType::CALL->value,
                'value' => $this->phone,
            ])
            ->assertSuccessful()
            ->assertJsonPath('message', 'OTP code has been sent to your endpoint');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sms/send.json')
                && $request['receptor'] === $this->phone
                && $request['sender'] === '10007891';
        });

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/call/maketts.json');
        });

        $otp = EndpointOTP::query()
            ->where('type', EndpointType::CALL->value)
            ->where('value', $this->phone)
            ->first();

        expect($otp)->not->toBeNull()
            ->and($otp->status)->toBe(EndpointOTP::STATUS_PENDING);
    });
});
