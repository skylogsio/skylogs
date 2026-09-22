<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AlertRuleWatchListService
{
    /**
     * @var array<string, int>
     */
    private const STATE_SORT_ORDER = [
        AlertRule::CRITICAL => 0,
        AlertRule::WARNING => 1,
        AlertRule::TRIGGERED => 2,
        AlertRule::UNKNOWN => 3,
        AlertRule::RESOlVED => 4,
    ];

    public function __construct(private readonly AlertRuleService $alertRuleService) {}

    public function toggle(User $user, AlertRule $alert): bool
    {
        if (! $this->alertRuleService->hasReadAccessAlert($user, $alert)) {
            abort(403);
        }

        if ($alert->isWatched($user)) {
            $alert->unWatch($user);
        } else {
            $alert->watch($user);
        }

        return $alert->isWatched($user);
    }

    public function watch(User $user, AlertRule $alert): void
    {
        if (! $alert->isWatched($user)) {
            $alert->watch($user);
        }
    }

    public function unWatch(User $user, AlertRule $alert): void
    {
        if ($alert->isWatched($user)) {
            $alert->unWatch($user);
        }
    }

    /**
     * @return Collection<int, AlertRule>
     */
    public function watchedAlerts(User $user): Collection
    {
        $alerts = AlertRule::query()
            ->where('watchUserIds', $user->id)
            ->get();

        return $this->sortWatchedAlerts(
            $alerts->filter(
                fn (AlertRule $alert): bool => $this->alertRuleService->hasReadAccessAlert($user, $alert)
            )->values()
        );
    }

    public function paginateForUser(User $user, int $perPage, string $path): LengthAwarePaginator
    {
        $alerts = $this->watchedAlerts($user);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $alerts->forPage($page, $perPage)->values(),
            $alerts->count(),
            $perPage,
            $page,
            ['path' => $path],
        );
    }

    /**
     * @param  Collection<int, AlertRule>  $alerts
     * @return Collection<int, AlertRule>
     */
    private function sortWatchedAlerts(Collection $alerts): Collection
    {
        return $alerts
            ->sortBy(function (AlertRule $alert): string {
                [$state] = $alert->getStatus();
                $rank = self::STATE_SORT_ORDER[strtolower((string) $state)] ?? 3;

                return sprintf('%d-%s', $rank, Str::lower((string) $alert->name));
            })
            ->values();
    }
}
