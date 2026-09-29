<?php

use App\Enums\EndpointType;
use App\Interfaces\NotificationChannel;
use App\Services\Notification\ChannelRegistry;

describe('ChannelRegistry', function () {
    it('has a channel for every endpoint type except flow', function () {
        $registry = app(ChannelRegistry::class);

        $expected = collect(EndpointType::cases())
            ->reject(fn (EndpointType $type) => $type === EndpointType::FLOW)
            ->map(fn (EndpointType $type) => $type->value)
            ->sort()
            ->values()
            ->all();

        expect(collect($registry->typeValues())->sort()->values()->all())->toBe($expected);

        foreach ($registry->types() as $type) {
            expect($registry->for($type))->toBeInstanceOf(NotificationChannel::class)
                ->and($registry->for($type)->type())->toBe($type);
        }
    });

    it('does not treat flow as a channel', function () {
        $registry = app(ChannelRegistry::class);

        expect($registry->has(EndpointType::FLOW))->toBeFalse()
            ->and(fn () => $registry->for('flow'))->toThrow(InvalidArgumentException::class);
    });

    it('lists the channels that need an otp before an endpoint is saved', function () {
        expect(app(ChannelRegistry::class)->verifiableTypeValues())->toBe(['sms', 'call', 'email']);
    });
});
