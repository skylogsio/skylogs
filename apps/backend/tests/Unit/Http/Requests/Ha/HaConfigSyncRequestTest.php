<?php

use App\Http\Requests\Ha\HaConfigSyncRequest;
use Illuminate\Support\Facades\Validator;

function haConfigSyncErrors(array $query): array
{
    return Validator::make($query, (new HaConfigSyncRequest)->rules())->errors()->keys();
}

describe('HaConfigSyncRequest', function () {
    it('accepts a version the caller already holds', function () {
        expect(haConfigSyncErrors(['since' => 42]))->toBe([]);
    });

    /*
     | A node that has never synced sends nothing, and must be answered with a
     | full snapshot rather than a validation error.
     */
    it('accepts an omitted version', function () {
        expect(haConfigSyncErrors([]))->toBe([]);
    });

    it('rejects a version that is not a number', function () {
        expect(haConfigSyncErrors(['since' => 'latest']))->toContain('since');
    });

    it('rejects a negative version', function () {
        expect(haConfigSyncErrors(['since' => -1]))->toContain('since');
    });
});
