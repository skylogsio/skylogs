<?php

namespace App\Services;

use App\Models\Endpoint;
use App\Models\OnCallPlan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class OnCallResolver
{
    /**
     * Who is on call for each layer of a plan at `$at` (plan timezone).
     *
     * @return array{
     *     at: string,
     *     timezone: string,
     *     teamId: string,
     *     plan: array{id: string, name: string},
     *     layers: list<array{level: int, escalateAfterMinutes: int, onCall: array<string, mixed>|null}>
     * }
     */
    public function at(OnCallPlan $plan, ?CarbonInterface $at = null): array
    {
        $at ??= Carbon::now();
        $local = Carbon::parse($at)->timezone($plan->timezone);
        $dayOfWeek = (int) $local->format('N');
        $minutes = ($local->hour * 60) + $local->minute;

        $layers = $plan->layers ?? [];
        $userIds = $this->collectUserIds($layers);

        $users = $userIds === []
            ? collect()
            : User::query()->whereIn('_id', $userIds)->get()->keyBy(fn (User $user) => (string) $user->id);

        $onCallEndpoints = $userIds === []
            ? collect()
            : Endpoint::query()
                ->whereIn('userId', $userIds)
                ->where('onCall', true)
                ->get()
                ->keyBy(fn (Endpoint $endpoint) => (string) $endpoint->userId);

        $resolvedLayers = [];

        foreach ($this->sortedLayers($layers) as $layer) {
            $match = $this->matchLayer($layer, $dayOfWeek, $minutes);
            $onCall = null;

            if ($match !== null) {
                $userId = $match['userId'];
                $user = $users->get($userId);
                $endpoint = $onCallEndpoints->get($userId);

                $onCall = [
                    'userId' => $userId,
                    'name' => $user?->name,
                    'window' => $match['window'],
                    'endpoint' => $endpoint === null ? null : [
                        'id' => $endpoint->id,
                        'name' => $endpoint->name,
                        'type' => $endpoint->type,
                    ],
                ];
            }

            $resolvedLayers[] = [
                'level' => (int) $layer['level'],
                'escalateAfterMinutes' => (int) ($layer['escalateAfterMinutes'] ?? 15),
                'onCall' => $onCall,
            ];
        }

        return [
            'at' => $local->toIso8601String(),
            'timezone' => $plan->timezone,
            'teamId' => (string) $plan->teamId,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
            ],
            'layers' => $resolvedLayers,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $layers
     * @return list<array<string, mixed>>
     */
    private function sortedLayers(array $layers): array
    {
        usort($layers, fn (array $left, array $right): int => ((int) $left['level']) <=> ((int) $right['level']));

        return $layers;
    }

    /**
     * @param  array<string, mixed>  $layer
     * @return array{userId: string, window: array{daysOfWeek: list<int>, startTime: string, endTime: string}}|null
     */
    private function matchLayer(array $layer, int $dayOfWeek, int $minutes): ?array
    {
        foreach ($layer['entries'] ?? [] as $entry) {
            foreach ($entry['windows'] ?? [] as $window) {
                $days = array_map('intval', $window['daysOfWeek'] ?? []);

                if (! in_array($dayOfWeek, $days, true)) {
                    continue;
                }

                $start = $this->timeToMinutes((string) $window['startTime']);
                $end = $this->timeToMinutes((string) $window['endTime']);

                if ($minutes >= $start && $minutes < $end) {
                    return [
                        'userId' => (string) $entry['userId'],
                        'window' => [
                            'daysOfWeek' => $days,
                            'startTime' => (string) $window['startTime'],
                            'endTime' => (string) $window['endTime'],
                        ],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $layers
     * @return list<string>
     */
    private function collectUserIds(array $layers): array
    {
        $ids = [];

        foreach ($layers as $layer) {
            foreach ($layer['entries'] ?? [] as $entry) {
                $ids[] = (string) $entry['userId'];
            }
        }

        return array_values(array_unique($ids));
    }

    public function timeToMinutes(string $time): int
    {
        if ($time === '24:00') {
            return 24 * 60;
        }

        [$hour, $minute] = array_map('intval', explode(':', $time, 2));

        return ($hour * 60) + $minute;
    }
}
