<?php

namespace App\Http\Resources\Ha;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{
 *     collection: string,
 *     documents: array<int, array<string, mixed>>,
 *     nextCursor: array{updatedAt: int, id: string}|null,
 *     hasMore: bool
 * } $resource
 */
class HaHistoryPageResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'collection' => $this->resource['collection'],
            'documents' => $this->resource['documents'],
            'nextCursor' => $this->resource['nextCursor'],
            'hasMore' => $this->resource['hasMore'],
        ];
    }
}
