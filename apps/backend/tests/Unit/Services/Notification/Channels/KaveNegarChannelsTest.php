<?php

use App\Enums\EndpointType;
use App\Models\Config\ConfigCall;
use App\Models\Config\ConfigSms;
use App\Models\Endpoint;
use App\Models\EndpointOTP;
use App\Services\ConfigCallService;
use App\Services\ConfigSmsService;
use App\Services\Notification\Channels\CallChannel;
use App\Services\Notification\Channels\SmsChannel;
use App\Support\NotifyMessagePayload;
use Illuminate\Support\Facades\Http;

function smsChannel(ConfigSms ...$configs): SmsChannel
{
    allowNotificationMetrics();

    $service = Mockery::mock(ConfigSmsService::class);
    $service->shouldReceive('chain')->andReturn(collect($configs));
    app()->instance(ConfigSmsService::class, $service);

    return app(SmsChannel::class);
}

/**
 * @param  list<ConfigCall>  $configs
 * @param  list<ConfigSms>  $smsConfigs
 */
function callChannel(array $configs = [], array $smsConfigs = []): CallChannel
{
    allowNotificationMetrics();

    $service = Mockery::mock(ConfigCallService::class);
    $service->shouldReceive('chain')->andReturn(collect($configs));
    app()->instance(ConfigCallService::class, $service);

    $sms = Mockery::mock(ConfigSmsService::class);
    $sms->shouldReceive('chain')->andReturn(collect($smsConfigs));
    app()->instance(ConfigSmsService::class, $sms);

    return app(CallChannel::class);
}

/**
 * @return array<string, mixed>
 */
function kaveNegarAccepted(int $messageId = 8792343): array
{
    return [
        'return' => ['status' => 200, 'message' => 'تایید شد'],
        'entries' => [['messageid' => $messageId, 'receptor' => '09120000000', 'status' => 1]],
    ];
}

describe('SmsChannel', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
    });

    it('sends one receptor per request with the configured provider and records the message id', function () {
        Http::fake(['api.kavenegar.com/*' => Http::response(kaveNegarAccepted(555))]);

        $config = new ConfigSms([
            'provider' => 'kaveNegar',
            'apiToken' => 'sms-token',
            'senderNumber' => '10004346',
        ]);

        $result = smsChannel($config)->send(
            new Endpoint(['type' => EndpointType::SMS->value, 'value' => '09120000000']),
            NotifyMessagePayload::fromBody('full body', ['sms' => 'short sms']),
        );

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.kavenegar.com/v1/sms-token/sms/send.json')
            && $request['receptor'] === '09120000000'
            && $request['sender'] === '10004346'
            && $request['message'] === 'short sms');

        expect($result->isSent())->toBeTrue()
            ->and($result->providerMessageId)->toBe('555');
    });

    it('falls back to the legacy kavenegar variables', function () {
        config(['variables.kavenegarToken' => 'legacy-token', 'variables.kavenegarSenderNumber' => '3000']);
        Http::fake(['api.kavenegar.com/*' => Http::response(kaveNegarAccepted())]);

        $result = smsChannel()->send(
            new Endpoint(['type' => EndpointType::SMS->value, 'value' => '0912']),
            NotifyMessagePayload::fromBody('hi'),
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), '/legacy-token/sms/send.json'));
        expect($result->isSent())->toBeTrue();
    });

    it('fails without a request when sms is not configured', function () {
        config(['variables.kavenegarToken' => '']);

        $result = smsChannel()->send(
            new Endpoint(['type' => EndpointType::SMS->value, 'value' => '0912']),
            NotifyMessagePayload::fromBody('hi'),
        );

        Http::assertNothingSent();
        expect($result->isSent())->toBeFalse()
            ->and($result->retryable)->toBeFalse()
            ->and($result->error)->toBe('SMS is not configured');
    });

    it('fails without retry for an unsupported provider', function () {
        $result = smsChannel(new ConfigSms(['provider' => 'other']))->send(
            new Endpoint(['type' => EndpointType::SMS->value, 'value' => '0912']),
            NotifyMessagePayload::fromBody('hi'),
        );

        expect($result->error)->toBe('SMS provider other is not supported')
            ->and($result->retryable)->toBeFalse();
    });

    it('fails without retry when kavenegar rejects the request', function () {
        Http::fake(['api.kavenegar.com/*' => Http::response(['return' => ['status' => 418, 'message' => 'اعتبار حساب شما کافی نیست'], 'entries' => null], 418)]);

        $result = smsChannel(new ConfigSms(['provider' => 'kaveNegar', 'apiToken' => 't']))->send(
            new Endpoint(['type' => EndpointType::SMS->value, 'value' => '0912']),
            NotifyMessagePayload::fromBody('hi'),
        );

        expect($result->isSent())->toBeFalse()
            ->and($result->retryable)->toBeFalse()
            ->and($result->error)->toStartWith('KaveNegar 418:');
    });

    it('retries kavenegar server errors', function () {
        Http::fake(['api.kavenegar.com/*' => Http::response('Bad Gateway', 502)]);

        $result = smsChannel(new ConfigSms(['provider' => 'kaveNegar', 'apiToken' => 't']))->send(
            new Endpoint(['type' => EndpointType::SMS->value, 'value' => '0912']),
            NotifyMessagePayload::fromBody('hi'),
        );

        expect($result->retryable)->toBeTrue()
            ->and($result->response)->toBe('Bad Gateway');
    });

    it('sends verification codes', function () {
        Http::fake(['api.kavenegar.com/*' => Http::response(kaveNegarAccepted())]);

        $otp = new EndpointOTP(['type' => 'sms', 'value' => '0912', 'otpCode' => 12345]);

        $result = smsChannel(new ConfigSms(['provider' => 'kaveNegar', 'apiToken' => 't']))->sendVerification($otp);

        Http::assertSent(fn ($request) => str_contains($request['message'], '12345'));
        expect($result->isSent())->toBeTrue();
    });

    it('uses the next account when the first one fails for an account reason', function () {
        expectProfileMetrics(EndpointType::SMS, [
            ['profileId' => null, 'priority' => 1, 'provider' => 'kaveNegar', 'outcome' => 'failed_over'],
            ['profileId' => null, 'priority' => 2, 'provider' => 'kaveNegar', 'outcome' => 'sent'],
        ], 'sent_after_failover');

        Http::fake([
            'api.kavenegar.com/v1/dead-token/*' => Http::response(['return' => ['status' => 418, 'message' => 'credit']], 418),
            'api.kavenegar.com/v1/live-token/*' => Http::response(kaveNegarAccepted(99)),
        ]);

        $result = smsChannel(
            new ConfigSms(['provider' => 'kaveNegar', 'apiToken' => 'dead-token', 'senderNumber' => '1000', 'priority' => 1]),
            new ConfigSms(['provider' => 'kaveNegar', 'apiToken' => 'live-token', 'senderNumber' => '2000', 'priority' => 2]),
        )->send(
            new Endpoint(['type' => EndpointType::SMS->value, 'value' => '09120000000']),
            NotifyMessagePayload::fromBody('hi'),
        );

        Http::assertSentCount(2);
        expect($result->isSent())->toBeTrue()
            ->and($result->providerMessageId)->toBe('99')
            ->and($result->steps)->toHaveCount(2)
            ->and($result->steps[0]['status'])->toBe('failed')
            ->and($result->steps[0]['priority'])->toBe(1)
            ->and($result->steps[1]['status'])->toBe('sent')
            ->and($result->steps[1]['provider'])->toBe('kaveNegar');
    });

    it('stops the chain when the receptor is rejected', function () {
        expectProfileMetrics(EndpointType::SMS, [
            ['profileId' => null, 'priority' => 1, 'provider' => 'kaveNegar', 'outcome' => 'failed'],
        ], 'failed_rejected');

        Http::fake([
            'api.kavenegar.com/*' => Http::response(['return' => ['status' => 411, 'message' => 'receptor']], 411),
        ]);

        $result = smsChannel(
            new ConfigSms(['provider' => 'kaveNegar', 'apiToken' => 'first', 'senderNumber' => '1000', 'priority' => 1]),
            new ConfigSms(['provider' => 'kaveNegar', 'apiToken' => 'second', 'senderNumber' => '2000', 'priority' => 2]),
        )->send(
            new Endpoint(['type' => EndpointType::SMS->value, 'value' => '0912']),
            NotifyMessagePayload::fromBody('hi'),
        );

        Http::assertSentCount(1);
        expect($result->isSent())->toBeFalse()
            ->and($result->retryable)->toBeFalse()
            ->and($result->failover)->toBeFalse()
            ->and($result->steps)->toHaveCount(1);
    });

    it('keeps a server error retryable after every profile fails', function () {
        expectProfileMetrics(EndpointType::SMS, [
            ['profileId' => null, 'priority' => 1, 'provider' => 'kaveNegar', 'outcome' => 'failed_over'],
            ['profileId' => null, 'priority' => 2, 'provider' => 'kaveNegar', 'outcome' => 'failed'],
        ], 'failed_exhausted');

        Http::fake(['api.kavenegar.com/*' => Http::response('Bad Gateway', 502)]);

        $result = smsChannel(
            new ConfigSms(['provider' => 'kaveNegar', 'apiToken' => 'first', 'senderNumber' => '1000', 'priority' => 1]),
            new ConfigSms(['provider' => 'kaveNegar', 'apiToken' => 'second', 'senderNumber' => '2000', 'priority' => 2]),
        )->send(
            new Endpoint(['type' => EndpointType::SMS->value, 'value' => '0912']),
            NotifyMessagePayload::fromBody('hi'),
        );

        Http::assertSentCount(2);
        expect($result->retryable)->toBeTrue()
            ->and($result->failover)->toBeTrue()
            ->and($result->steps)->toHaveCount(2);
    });
});

describe('CallChannel', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
    });

    it('sends the call text to the tts api without a sender', function () {
        Http::fake(['api.kavenegar.com/*' => Http::response(kaveNegarAccepted(77))]);

        $result = callChannel([new ConfigCall(['provider' => 'kaveNegar', 'apiToken' => 'call-token'])])->send(
            new Endpoint(['type' => EndpointType::CALL->value, 'value' => '0912']),
            NotifyMessagePayload::fromBody('long body', ['call' => 'Alert CPU fired']),
        );

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.kavenegar.com/v1/call-token/call/maketts.json')
            && $request['message'] === 'Alert CPU fired'
            && ! array_key_exists('sender', $request->data()));

        expect($result->providerMessageId)->toBe('77');
    });

    it('sends verification codes by sms instead of a voice call', function () {
        Http::fake(['api.kavenegar.com/*' => Http::response(kaveNegarAccepted())]);

        $otp = new EndpointOTP(['type' => EndpointType::CALL->value, 'value' => '0912', 'otpCode' => 54321]);

        $result = callChannel(
            [new ConfigCall(['provider' => 'kaveNegar', 'apiToken' => 'call-token'])],
            [new ConfigSms(['provider' => 'kaveNegar', 'apiToken' => 'sms-token', 'senderNumber' => '10004346'])],
        )->sendVerification($otp);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.kavenegar.com/v1/sms-token/sms/send.json')
            && $request['receptor'] === '0912'
            && $request['sender'] === '10004346'
            && str_contains($request['message'], '54321'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/call/maketts.json'));

        expect($result->isSent())->toBeTrue();
    });

    it('requires verification', function () {
        expect(callChannel()->requiresVerification())->toBeTrue()
            ->and(smsChannel()->requiresVerification())->toBeTrue();
    });
});
