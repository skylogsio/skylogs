<?php

namespace App\Http\Resources\Ha;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{version: int, changed: bool, collections: array<string, mixed>} $resource
 */
class HaConfigSnapshotResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->resource['version'],
            'changed' => $this->resource['changed'],
            /*
             | Omitted entirely when nothing changed, so the common case is a
             | few bytes rather than every replicated collection.
             */
            'collections' => $this->when(
                $this->resource['changed'],
                fn (): array => $this->resource['collections'],
            ),
        ];
    }
}
