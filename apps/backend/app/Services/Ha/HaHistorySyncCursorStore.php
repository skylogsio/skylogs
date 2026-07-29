<?php

namespace App\Services\Ha;

use App\Models\HaHistorySyncCursor;

/**
 * Per wire-alias cursor for incremental history/notify sync on this node.
 */
class HaHistorySyncCursorStore
{
    /**
     * @return array{afterUpdatedAt: int|null, afterId: string|null}
     */
    public function get(string $alias): array
    {
        $document = HaHistorySyncCursor::query()->where('name', $alias)->first();

        if (! $document) {
            return ['afterUpdatedAt' => null, 'afterId' => null];
        }

        $afterId = $document->afterId;

        return [
            'afterUpdatedAt' => $document->afterUpdatedAt !== null ? (int) $document->afterUpdatedAt : null,
            'afterId' => is_string($afterId) && $afterId !== '' ? $afterId : null,
        ];
    }

    public function record(string $alias, int $afterUpdatedAt, string $afterId): void
    {
        $document = HaHistorySyncCursor::query()->where('name', $alias)->first()
            ?? new HaHistorySyncCursor(['name' => $alias]);

        $document->name = $alias;
        $document->afterUpdatedAt = $afterUpdatedAt;
        $document->afterId = $afterId;
        $document->save();
    }
}
