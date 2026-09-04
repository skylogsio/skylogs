<?php

namespace App\Services\Ha;

use App\Models\AlertRule;
use App\Models\HaStateVersion;
use DateTimeInterface;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Operation\FindOneAndUpdate;

/**
 * Owns the per key version counters that order replicated writes.
 *
 * A follower rejects any apply whose version is not newer than the one it
 * already holds, which is what makes replays and duplicate deliveries harmless.
 */
class HaStateVersionStore
{
    /**
     * Reserve the next version for a key. Atomic, so two workers publishing the
     * same slot at the same moment still produce strictly ordered versions.
     *
     * The last published state is recorded alongside it so that the slots a
     * rule currently believes to be firing can be found again without reading
     * every check document.
     */
    public function next(string $key, ?string $state = null): int
    {
        $document = HaStateVersion::raw(fn (Collection $collection) => $collection->findOneAndUpdate(
            ['key' => $key],
            [
                '$inc' => ['version' => 1],
                '$set' => [
                    'nodeId' => $this->nodeId(),
                    'state' => $state,
                    'updatedAt' => new UTCDateTime,
                ],
                '$setOnInsert' => ['createdAt' => new UTCDateTime],
            ],
            [
                'upsert' => true,
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ],
        ));

        return (int) ($document['version'] ?? 1);
    }

    public function current(string $key): int
    {
        return (int) (HaStateVersion::where('key', $key)->value('version') ?? 0);
    }

    /**
     * The version and the node that produced it. The node is what breaks a tie
     * between two writers that reached the same version during a split.
     *
     * @return array{version: int, nodeId: string}
     */
    public function entry(string $key): array
    {
        $document = HaStateVersion::where('key', $key)->first();

        return [
            'version' => (int) ($document->version ?? 0),
            'nodeId' => (string) ($document->nodeId ?? ''),
        ];
    }

    /**
     * Adopt a version produced elsewhere: a follower recording what it applied,
     * or a freshly promoted leader inheriting the log's counters so that its
     * next publish does not restart from one and lose to its own history.
     */
    public function record(string $key, int $version, string $nodeId, ?string $state = null): void
    {
        HaStateVersion::raw(fn (Collection $collection) => $collection->updateOne(
            ['key' => $key],
            [
                '$set' => [
                    'version' => $version,
                    'nodeId' => $nodeId,
                    'state' => $state,
                    'updatedAt' => new UTCDateTime,
                ],
                '$setOnInsert' => ['createdAt' => new UTCDateTime],
            ],
            ['upsert' => true],
        ));
    }

    /**
     * @return array<int, string>
     */
    public function allKeys(): array
    {
        return $this->pluckKeys(HaStateVersion::query());
    }

    /**
     * Resolved slots that have sat untouched past the retention window. They
     * are what the leader tombstones so the replicated log stays bounded.
     *
     * @return array<int, string>
     */
    public function resolvedKeysUpdatedBefore(DateTimeInterface $before): array
    {
        return $this->pluckKeys(
            HaStateVersion::where('state', AlertRule::RESOlVED)
                ->where('updatedAt', '<', $before)
        );
    }

    /**
     * @return array<int, string>
     */
    public function keysWithPrefix(string $prefix): array
    {
        return $this->pluckKeys(HaStateVersion::where('key', 'like', $prefix.'%'));
    }

    /**
     * Keys under the prefix whose last published state was not resolved. Used
     * to notice instances that disappeared without going through a model event.
     *
     * @return array<int, string>
     */
    public function unresolvedKeysWithPrefix(string $prefix): array
    {
        return $this->pluckKeys(
            HaStateVersion::where('key', 'like', $prefix.'%')
                ->where('state', '!=', AlertRule::RESOlVED)
        );
    }

    public function forget(string $key): void
    {
        HaStateVersion::where('key', $key)->delete();
    }

    /**
     * @param  Builder<HaStateVersion>  $query
     * @return array<int, string>
     */
    private function pluckKeys(Builder $query): array
    {
        return $query->pluck('key')
            ->map(fn ($key): string => (string) $key)
            ->all();
    }

    private function nodeId(): string
    {
        return (string) config('ha.node_id');
    }
}
