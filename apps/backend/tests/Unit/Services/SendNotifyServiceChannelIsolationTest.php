<?php

use App\Services\SendNotifyService;

describe('SendNotifyService channel isolation', function () {
    it('returns the sender result when a channel succeeds', function () {
        $result = invokeTrySendChannel(fn () => ['sent' => true]);

        expect($result)->toBe(['sent' => true]);
    });

    it('returns null when a channel has no endpoints to send', function () {
        $result = invokeTrySendChannel(fn () => null);

        expect($result)->toBeNull();
    });

    it('captures throwable messages instead of bubbling exceptions', function () {
        $result = invokeTrySendChannel(function () {
            throw new RuntimeException('cURL error 6: Could not resolve host: discord');
        });

        expect($result)->toBe('cURL error 6: Could not resolve host: discord');
    });

    it('allows later channels to run after an earlier channel failure', function () {
        $discordResult = invokeTrySendChannel(function () {
            throw new RuntimeException('Discord failed');
        });

        $telegramResult = invokeTrySendChannel(fn () => [['ok' => true]]);

        expect($discordResult)->toBe('Discord failed')
            ->and($telegramResult)->toBe([['ok' => true]]);
    });
});

/**
 * @param  callable(): mixed  $sender
 */
function invokeTrySendChannel(callable $sender): mixed
{
    $method = new ReflectionMethod(SendNotifyService::class, 'trySendChannel');
    $method->setAccessible(true);

    return $method->invoke(app(SendNotifyService::class), $sender);
}
