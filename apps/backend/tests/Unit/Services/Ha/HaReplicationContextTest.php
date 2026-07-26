<?php

use App\Services\Ha\HaReplicationContext;

describe('HaReplicationContext', function () {
    it('is not applying by default', function () {
        expect(HaReplicationContext::isApplying())->toBeFalse();
    });

    it('marks the apply path and returns its result', function () {
        $result = HaReplicationContext::apply(function (): string {
            expect(HaReplicationContext::isApplying())->toBeTrue();

            return 'applied';
        });

        expect($result)->toBe('applied')
            ->and(HaReplicationContext::isApplying())->toBeFalse();
    });

    it('clears the flag even when the apply throws', function () {
        expect(fn () => HaReplicationContext::apply(fn () => throw new RuntimeException('boom')))
            ->toThrow(RuntimeException::class)
            ->and(HaReplicationContext::isApplying())->toBeFalse();
    });

    it('stays marked until the outermost apply finishes', function () {
        HaReplicationContext::apply(function (): void {
            HaReplicationContext::apply(fn () => null);

            expect(HaReplicationContext::isApplying())->toBeTrue();
        });

        expect(HaReplicationContext::isApplying())->toBeFalse();
    });
});
