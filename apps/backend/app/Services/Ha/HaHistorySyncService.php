<?php

namespace App\Services\Ha;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Pages history/notify documents on the leader and upserts them on a follower.
 *
 * Incremental by (updatedAt, _id): append-only timelines and later Notify.status
 * updates both advance the same cursor. Deletes are not propagated in v1.
 */
class HaHistorySyncService
{
    /**
     * @return array{
     *     collection: string,
     *     documents: array<int, array<string, mixed>>,
     *     nextCursor: array{updatedAt: int, id: string}|null,
     *     hasMore: bool
     * }
     */
    public function page(string $alias, ?int $afterUpdatedAt, ?string $afterId, int $limit): array
    {
        $model = HaHistoryCatalog::model($alias);

        if ($model === null) {
            return [
                'collection' => $alias,
                'documents' => [],
                'nextCursor' => null,
                'hasMore' => false,
            ];
        }

        $limit = max(1, $limit);

        $query = $model::query()->orderBy('updatedAt')->orderBy('_id');

        $this->applyCursor($query, $afterUpdatedAt, $afterId);

        /*
         | Fetch one extra row so hasMore is exact without a separate count.
         */
        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $page = $rows->take($limit);

        $documents = $page
            ->map(fn (Model $document): array => $this->encodeDocument($document))
            ->values()
            ->all();

        $nextCursor = null;

        if ($page->isNotEmpty()) {
            $last = $page->last();
            $nextCursor = [
                'updatedAt' => $this->timestampMs($last->getAttribute('updatedAt') ?? $last->getAttribute('createdAt')),
                'id' => (string) ($last->getAttribute('_id') ?? $last->getAttribute('id')),
            ];
        }

        return [
            'collection' => $alias,
            'documents' => $documents,
            'nextCursor' => $nextCursor,
            'hasMore' => $hasMore,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     * @return array{written: int}
     */
    public function applyPage(string $alias, array $documents): array
    {
        $model = HaHistoryCatalog::model($alias);

        if ($model === null) {
            return ['written' => 0];
        }

        $protected = HaHistoryCatalog::protectedFields();

        return HaReplicationContext::apply(function () use ($model, $documents, $protected): array {
            $written = 0;

            foreach ($documents as $document) {
                if (! is_array($document)) {
                    continue;
                }

                $id = $this->objectId($document['_id'] ?? $document['id'] ?? null);

                if (! $id) {
                    continue;
                }

                if ($this->upsert($model, $id, $protected, Arr::except($document, $protected))) {
                    $written++;
                }
            }

            return ['written' => $written];
        });
    }

    private function applyCursor(Builder $query, ?int $afterUpdatedAt, ?string $afterId): void
    {
        if ($afterUpdatedAt === null) {
            return;
        }

        $afterDate = new UTCDateTime($afterUpdatedAt);
        $afterObjectId = $afterId ? $this->objectId($afterId) : null;

        $query->where(function (Builder $inner) use ($afterDate, $afterObjectId): void {
            $inner->where('updatedAt', '>', $afterDate);

            if ($afterObjectId) {
                $inner->orWhere(function (Builder $tie) use ($afterDate, $afterObjectId): void {
                    $tie->where('updatedAt', '=', $afterDate)
                        ->where('_id', '>', $afterObjectId);
                });
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeDocument(Model $document): array
    {
        $attributes = $document->getAttributes();

        $identified = [
            '_id' => $attributes['_id'] ?? $attributes['id'] ?? null,
            ...Arr::except($attributes, ['_id', 'id']),
        ];

        return $this->encode($identified);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $protected
     * @param  array<string, mixed>  $encoded
     */
    private function upsert(string $model, ObjectId $id, array $protected, array $encoded): bool
    {
        $document = $model::where('_id', $id)->first();

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

        foreach (array_keys($document->getAttributes()) as $field) {
            if (! array_key_exists($field, $attributes) && ! in_array($field, $protected, true)) {
                $document->unset($field);
            }
        }

        /*
         | Avoid Eloquent restamping updatedAt on apply: the leader's value is
         | the watermark the next cursor must match.
         */
        $document->timestamps = false;
        $document->save();

        return true;
    }

    /**
     * @param  array<int, string>  $protected
     * @return array<string, mixed>
     */
    private function encodedFieldsOf(Model $document, array $protected): array
    {
        return Arr::except($this->encode(Arr::except($document->getAttributes(), ['_id', 'id'])), $protected);
    }

    private function encode(mixed $value): mixed
    {
        if ($value instanceof ObjectId) {
            return ['$oid' => (string) $value];
        }

        if ($value instanceof UTCDateTime) {
            return ['$date' => (int) ((string) $value)];
        }

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

    private function timestampMs(mixed $value): int
    {
        if ($value instanceof UTCDateTime) {
            return (int) ((string) $value);
        }

        if ($value instanceof DateTimeInterface) {
            return (int) $value->format('Uv');
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}
