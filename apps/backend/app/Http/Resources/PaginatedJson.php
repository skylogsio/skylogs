<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginatedJson
{
    /**
     * Encode a page of resources in the same JSON shape as EndpointController::Index
     * (`current_page`, `data`, `last_page`, `per_page`, `total`) rather than the
     * resource-collection wrapper (`data`, `links`, `meta`).
     *
     * @param  class-string<JsonResource>  $resource
     */
    public static function make(LengthAwarePaginator $paginator, string $resource): JsonResponse
    {
        $paginator->through(
            fn (mixed $item): array => (new $resource($item))->resolve(),
        );

        return response()->json($paginator);
    }
}
