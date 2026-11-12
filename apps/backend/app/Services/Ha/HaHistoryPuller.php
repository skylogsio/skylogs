<?php

namespace App\Services\Ha;

use App\Exceptions\Ha\LeaderUnavailableException;

/**
 * The follower half of history/notify sync: page from the leader, upsert, advance cursors.
 */
class HaHistoryPuller
{
    public function __construct(
        private readonly HaLeaderService $leader,
        private readonly LeaderHistoryClient $client,
        private readonly HaHistorySyncService $sync,
        private readonly HaHistorySyncCursorStore $cursors,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     collections?: array<string, array{written: int, pages: int, hasMore: bool}>
     * }
     *
     * @throws LeaderUnavailableException
     */
    public function pull(): array
    {
        if (! config('ha.enabled') || ! config('ha.history_sync.enabled')) {
            return ['status' => 'disabled'];
        }

        if ($this->leader->isLeader()) {
            return ['status' => 'leader'];
        }

        $address = $this->leader->leaderAddress();

        if (! is_string($address) || $address === '') {
            throw LeaderUnavailableException::unknownAddress();
        }

        $pageSize = max(1, (int) config('ha.history_sync.page_size', 200));
        $maxPages = max(1, (int) config('ha.history_sync.max_pages_per_tick', 5));
        $summary = [];

        foreach (HaHistoryCatalog::aliases() as $alias) {
            $summary[$alias] = $this->pullCollection($address, $alias, $pageSize, $maxPages);
        }

        return ['status' => 'pulled', 'collections' => $summary];
    }

    /**
     * @return array{written: int, pages: int, hasMore: bool}
     *
     * @throws LeaderUnavailableException
     */
    private function pullCollection(string $address, string $alias, int $pageSize, int $maxPages): array
    {
        $written = 0;
        $pages = 0;
        $hasMore = false;

        for ($i = 0; $i < $maxPages; $i++) {
            $cursor = $this->cursors->get($alias);

            $page = $this->client->page(
                $address,
                $alias,
                $cursor['afterUpdatedAt'],
                $cursor['afterId'],
                $pageSize,
            );

            if ($page['documents'] === []) {
                $hasMore = false;
                break;
            }

            $result = $this->sync->applyPage($alias, $page['documents']);
            $written += $result['written'];
            $pages++;

            if ($page['nextCursor'] !== null && ($page['nextCursor']['id'] ?? '') !== '') {
                $this->cursors->record(
                    $alias,
                    (int) $page['nextCursor']['updatedAt'],
                    (string) $page['nextCursor']['id'],
                );
            }

            $hasMore = $page['hasMore'];

            if (! $hasMore) {
                break;
            }
        }

        return ['written' => $written, 'pages' => $pages, 'hasMore' => $hasMore];
    }
}
