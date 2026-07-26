<?php

namespace App\Services\Ha;

use App\Models\HaConfigVersion;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Operation\FindOneAndUpdate;

/**
 * The counter that keeps configuration sync cheap.
 *
 * The snapshot itself is a full copy, which is what makes it idempotent and
 * free of a change log; what stops it being expensive is that a follower whose
 * counter matches the leader's is answered with nothing at all.
 */
class HaConfigVersionStore
{
    /**
     * The leader's current configuration version.
     *
     * Never zero: a follower that has applied nothing starts at zero, so a
     * leader that has never been written to still looks newer and the first
     * snapshot is delivered.
     */
    public function current(): int
    {
        return max(1, $this->read(HaConfigVersion::LOCAL));
    }

    /**
     * Atomic, because several requests can write replicated configuration at
     * the same moment and a lost bump means a follower never learns of a change.
     */
    public function bump(): int
    {
        $document = HaConfigVersion::raw(fn (Collection $collection) => $collection->findOneAndUpdate(
            ['name' => HaConfigVersion::LOCAL],
            [
                '$inc' => ['version' => 1],
                '$set' => ['updatedAt' => new UTCDateTime],
                '$setOnInsert' => ['createdAt' => new UTCDateTime],
            ],
            [
                'upsert' => true,
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ],
        ));

        return (int) ($document['version'] ?? 1);
    }

    public function lastAppliedLeaderVersion(): int
    {
        return $this->read(HaConfigVersion::APPLIED_LEADER);
    }

    public function recordAppliedLeaderVersion(int $version): void
    {
        HaConfigVersion::raw(fn (Collection $collection) => $collection->updateOne(
            ['name' => HaConfigVersion::APPLIED_LEADER],
            [
                '$set' => ['version' => $version, 'updatedAt' => new UTCDateTime],
                '$setOnInsert' => ['createdAt' => new UTCDateTime],
            ],
            ['upsert' => true],
        ));
    }

    private function read(string $name): int
    {
        return (int) (HaConfigVersion::where('name', $name)->value('version') ?? 0);
    }
}
