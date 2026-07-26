<?php

namespace App\Services\Ha;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Produces the leader's configuration snapshot and applies it on a follower.
 *
 * A full snapshot rather than versioned deltas: the replicated set is small,
 * a snapshot is trivially idempotent, and deltas would need a durable change
 * log plus tombstones for a marginal saving. The saving comes from the version
 * check instead, so the steady state is one tiny request every thirty seconds.
 */
class HaConfigSyncService
{
    public function __construct(private readonly HaConfigVersionStore $versions) {}

    /**
     * @return array{version: int, changed: bool, collections: array<string, array<int, array<string, mixed>>>}
     */
    public function snapshot(int $since): array
    {
        $version = $this->versions->current();

        if ($since === $version) {
            return ['version' => $version, 'changed' => false, 'collections' => []];
        }

        $collections = [];

        foreach (HaConfigCatalog::collections() as $alias => $definition) {
            $collections[$alias] = $this->documentsOf($definition['model'], $definition['excluded']);
        }

        return ['version' => $version, 'changed' => true, 'collections' => $collections];
    }

    /**
     * Leader wins outright: the leader is the sole writer by deployment, so a
     * stray write on a follower is a symptom, not a conflict to merge, and the
     * next snapshot simply overwrites it.
     *
     * @param  array{collections?: mixed}  $snapshot
     * @return array<string, array{written: int, deleted: int}>
     */
    public function apply(array $snapshot): array
    {
        $collections = $snapshot['collections'] ?? [];

        if (! is_array($collections)) {
            return [];
        }

        /*
         | The whole apply runs as replication so that neither the alert state
         | observers nor the configuration version observer treat the leader's
         | documents as this node's own writes.
         */
        return HaReplicationContext::apply(function () use ($collections): array {
            $summary = [];

            foreach ($collections as $alias => $documents) {
                $definition = HaConfigCatalog::definition((string) $alias);

                /*
                 | An unknown collection means the leader is running a newer
                 | build. Ignoring it lets a rolling upgrade proceed instead of
                 | failing every sync until the follower catches up.
                 */
                if ($definition === null || ! is_array($documents)) {
                    continue;
                }

                $summary[(string) $alias] = $this->applyCollection((string) $alias, $definition['model'], $documents);
            }

            return $summary;
        });
    }

    /**
     * Read the hydrated attributes rather than the driver's documents.
     *
     * The driver returns a document exactly as it sits on disk, but the shape a
     * model carries in memory is not that: the query grammar renames _id to id,
     * embedded documents included, and turns every BSON date into a Carbon. A
     * follower compares and writes through Eloquent, so a snapshot in the
     * driver's shape would differ from the follower's own copy on every field
     * the grammar touches, and every sync would rewrite every document forever.
     *
     * getAttributes is what the grammar produced, before any serialisation, so
     * hidden fields such as a user's password hash are still present, which is
     * precisely what a follower needs for logins to work after a failover.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $excluded
     * @return array<int, array<string, mixed>>
     */
    private function documentsOf(string $model, array $excluded): array
    {
        return $model::query()
            ->get()
            ->map(function (Model $document) use ($excluded): array {
                $attributes = $document->getAttributes();

                /*
                 | The primary key travels as _id whatever the grammar called
                 | it locally, so the wire format does not depend on how the
                 | leader happened to load the document.
                 */
                $identified = [
                    '_id' => $attributes['_id'] ?? $attributes['id'] ?? null,
                    ...Arr::except($attributes, ['_id', 'id']),
                ];

                return Arr::except($this->encode($identified), $excluded);
            })
            ->all();
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, mixed>  $documents
     * @return array{written: int, deleted: int}
     */
    private function applyCollection(string $alias, string $model, array $documents): array
    {
        $protected = HaConfigCatalog::protectedFields($alias);
        $seen = [];
        $written = 0;

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $id = $this->objectId($document['_id'] ?? $document['id'] ?? null);

            if (! $id) {
                continue;
            }

            $seen[] = $id;

            if ($this->upsert($model, $id, $protected, Arr::except($document, $protected))) {
                $written++;
            }
        }

        return ['written' => $written, 'deleted' => $this->deleteAbsent($model, $seen)];
    }

    /**
     * Written through Eloquent, not the driver, so that the observers a normal
     * write would fire — cache busting above all — fire here too.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $protected
     * @param  array<string, mixed>  $encoded  The leader's fields, still in wire form.
     */
    private function upsert(string $model, ObjectId $id, array $protected, array $encoded): bool
    {
        $document = $model::where('_id', $id)->first();

        /*
         | Compared in wire form rather than through isDirty, because the two
         | sides hold equal values as different objects: a Carbon and a
         | UTCDateTime for the same instant are never identical, and neither
         | are two ObjectIds. Encoding both sides reduces them to scalars,
         | which is the only comparison that answers the question being asked.
         |
         | Saving an unchanged document would touch updatedAt on every tick and
         | make every collection look busy to anything watching it.
         */
        if ($document && $this->encodedFieldsOf($document, $protected) == $encoded) {
            return false;
        }

        if (! $document) {
            $document = new $model;
            $document->_id = $id;
        }

        $attributes = $this->decode($encoded);

        foreach ($attributes as $field => $value) {
            $document->setAttribute($field, $value);
        }

        /*
         | A field the leader dropped has to be dropped here too, otherwise a
         | removed webhook token or a cleared threshold would live on forever
         | on the follower. Protected fields are skipped: they are absent from
         | the snapshot by design, not because the leader removed them.
         */
        foreach (array_keys($document->getAttributes()) as $field) {
            if (! array_key_exists($field, $attributes) && ! in_array($field, $protected, true)) {
                $document->unset($field);
            }
        }

        $document->save();

        return true;
    }

    /**
     * This node's own copy of a document, in the same wire form the leader
     * would have sent for it, so the two can be compared field for field.
     *
     * @param  array<int, string>  $protected
     * @return array<string, mixed>
     */
    private function encodedFieldsOf(Model $document, array $protected): array
    {
        return Arr::except($this->encode(Arr::except($document->getAttributes(), ['_id', 'id'])), $protected);
    }

    /**
     * Deletes fall out of the snapshot for free: anything the leader no longer
     * holds is gone here too.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, ObjectId>  $seen
     */
    private function deleteAbsent(string $model, array $seen): int
    {
        $query = $model::query();

        if ($seen !== []) {
            $query->whereNotIn('_id', $seen);
        }

        $stale = $query->get();

        $stale->each(fn (Model $document) => $document->delete());

        return $stale->count();
    }

    /**
     * BSON types have no JSON form, and a document round-tripped through plain
     * JSON would turn every ObjectId reference and every date into a string.
     * The tagged form below survives the wire and decodes back to the same type.
     */
    private function encode(mixed $value): mixed
    {
        if ($value instanceof ObjectId) {
            return ['$oid' => (string) $value];
        }

        if ($value instanceof UTCDateTime) {
            return ['$date' => (int) ((string) $value)];
        }

        /*
         | A hydrated model holds Carbon where the document holds a BSON date,
         | so both have to reduce to the same tag or the two sides of a
         | comparison would never agree.
         */
        if ($value instanceof DateTimeInterface) {
            return ['$date' => (int) $value->format('Uv')];
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->encode($item), $value);
        }

        return $value;
    }

    private function decode(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (isset($value['$oid']) && count($value) === 1 && is_string($value['$oid'])) {
            return $this->objectId($value['$oid']) ?? $value['$oid'];
        }

        if (isset($value['$date']) && count($value) === 1 && is_numeric($value['$date'])) {
            return new UTCDateTime((int) $value['$date']);
        }

        return array_map(fn (mixed $item): mixed => $this->decode($item), $value);
    }

    private function objectId(mixed $id): ?ObjectId
    {
        if ($id instanceof ObjectId) {
            return $id;
        }

        if (is_array($id) && is_string($id['$oid'] ?? null)) {
            $id = $id['$oid'];
        }

        if (! is_string($id) || ! preg_match('/^[0-9a-fA-F]{24}$/', $id)) {
            return null;
        }

        return new ObjectId($id);
    }
}
