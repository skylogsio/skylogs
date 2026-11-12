<?php

namespace App\Services\Ha;

use App\Exceptions\Ha\LeaderUnavailableException;
use App\Http\Middleware\HaNodeAuth;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

/**
 * Pulls one page of history/notify documents from the leader's backend.
 */
class LeaderHistoryClient
{
    /**
     * @return array{
     *     collection: string,
     *     documents: array<int, array<string, mixed>>,
     *     nextCursor: array{updatedAt: int, id: string}|null,
     *     hasMore: bool
     * }
     *
     * @throws LeaderUnavailableException
     */
    public function page(
        string $address,
        string $collection,
        ?int $afterUpdatedAt,
        ?string $afterId,
        int $limit,
    ): array {
        $url = rtrim($address, '/').'/api/ha/history-sync';

        $query = array_filter([
            'collection' => $collection,
            'afterUpdatedAt' => $afterUpdatedAt,
            'afterId' => $afterId,
            'limit' => $limit,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        try {
            $response = Http::connectTimeout((float) config('ha.history_sync.connect_timeout'))
                ->timeout((float) config('ha.history_sync.timeout'))
                ->withHeader(HaNodeAuth::SECRET_HEADER, (string) config('ha.node_secret'))
                ->acceptJson()
                ->get($url, $query);
        } catch (HttpClientException $exception) {
            throw LeaderUnavailableException::unreachable($address, $exception);
        }

        if ($response->failed()) {
            throw LeaderUnavailableException::badResponse($address, $response->status());
        }

        $payload = $response->json() ?? [];
        $nextCursor = $payload['nextCursor'] ?? null;

        return [
            'collection' => (string) ($payload['collection'] ?? $collection),
            'documents' => is_array($payload['documents'] ?? null) ? $payload['documents'] : [],
            'nextCursor' => is_array($nextCursor) ? [
                'updatedAt' => (int) ($nextCursor['updatedAt'] ?? 0),
                'id' => (string) ($nextCursor['id'] ?? ''),
            ] : null,
            'hasMore' => (bool) ($payload['hasMore'] ?? false),
        ];
    }
}
