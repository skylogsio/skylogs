<?php

use App\Http\Resources\Ha\HaConfigSnapshotResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;

function haSnapshotPayload(array $snapshot): array
{
    return (new HaConfigSnapshotResource($snapshot))->toArray(Request::create('/api/ha/config-sync'));
}

describe('HaConfigSnapshotResource', function () {
    it('carries the collections when the leader has moved on', function () {
        $payload = haSnapshotPayload([
            'version' => 9,
            'changed' => true,
            'collections' => ['users' => [['name' => 'root']]],
        ]);

        expect($payload['version'])->toBe(9)
            ->and($payload['changed'])->toBeTrue()
            ->and($payload['collections'])->toBe(['users' => [['name' => 'root']]]);
    });

    /*
     | The common case by far: nothing changed, so the answer must not carry a
     | copy of every replicated collection.
     */
    it('omits the collections entirely when nothing changed', function () {
        $payload = haSnapshotPayload(['version' => 9, 'changed' => false, 'collections' => []]);

        expect($payload['collections'])->toBeInstanceOf(MissingValue::class)
            ->and($payload['changed'])->toBeFalse();
    });
});
