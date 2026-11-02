<?php

use App\Enums\EndpointType;
use App\Models\Endpoint;
use App\Models\EndpointOTP;
use App\Services\ConfigEmailService;
use App\Services\Notification\Channels\EmailChannel;
use App\Support\NotifyMessagePayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;

function emailChannel(): EmailChannel
{
    $service = Mockery::mock(ConfigEmailService::class);
    $service->shouldReceive('getDefault')->andReturnNull();

    return new EmailChannel($service);
}

/**
 * @return Collection<int, SentMessage>
 */
function sentArrayMails(): Collection
{
    return Mail::mailer('array')->getSymfonyTransport()->messages();
}

describe('EmailChannel', function () {
    beforeEach(function () {
        config(['mail.default' => 'array']);
        sentArrayMails()->splice(0);
    });

    it('mails one address per endpoint', function () {
        $result = emailChannel()->send(
            new Endpoint(['type' => EndpointType::EMAIL->value, 'value' => 'ops@example.test']),
            NotifyMessagePayload::fromBody('Disk full'),
        );

        $message = sentArrayMails()->last()->getOriginalMessage();

        expect($result->isSent())->toBeTrue()
            ->and($message->getTo()[0]->getAddress())->toBe('ops@example.test')
            ->and($message->getSubject())->toBe('Skylogs Alert')
            ->and($message->getTextBody())->toBe('Disk full');
    });

    it('sends verification codes', function () {
        $otp = new EndpointOTP(['type' => 'email', 'value' => 'new@example.test', 'otpCode' => 54321]);

        $result = emailChannel()->sendVerification($otp);

        $message = sentArrayMails()->last()->getOriginalMessage();

        expect($result->isSent())->toBeTrue()
            ->and($message->getSubject())->toBe('Skylogs Endpoint Verification')
            ->and($message->getTextBody())->toContain('54321');
    });

    it('marks transport errors as retryable', function () {
        Mail::shouldReceive('raw')->andThrow(new TransportException('Connection could not be established'));

        $result = emailChannel()->send(
            new Endpoint(['type' => EndpointType::EMAIL->value, 'value' => 'ops@example.test']),
            NotifyMessagePayload::fromBody('x'),
        );

        expect($result->isSent())->toBeFalse()
            ->and($result->retryable)->toBeTrue()
            ->and($result->error)->toBe('Connection could not be established');
    });
});
