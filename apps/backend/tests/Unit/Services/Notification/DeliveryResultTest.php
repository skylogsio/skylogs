<?php

use App\Services\Notification\DeliveryResult;
use Illuminate\Http\Client\ConnectionException;

describe('DeliveryResult', function () {
    it('truncates long provider responses when serialized', function () {
        config(['notification.response_max_length' => 20]);

        $result = DeliveryResult::sent(200, ['text' => str_repeat('a', 100)]);

        expect($result->toArray()['response'])->toBeString()
            ->and(mb_strlen($result->toArray()['response']))->toBeLessThanOrEqual(23);
    });

    it('keeps short responses as they are', function () {
        $result = DeliveryResult::failed('HTTP 400', 400, ['ok' => false]);

        expect($result->toArray())->toBe([
            'status' => 'failed',
            'retryable' => false,
            'httpStatus' => 400,
            'providerMessageId' => null,
            'response' => ['ok' => false],
            'error' => 'HTTP 400',
        ]);
    });

    it('only retries network exceptions', function () {
        expect(DeliveryResult::fromException(new ConnectionException('timeout'))->retryable)->toBeTrue()
            ->and(DeliveryResult::fromException(new RuntimeException('bug'))->retryable)->toBeFalse();
    });
});
