<?php

namespace App\Http\Resources\OnCallPlan;

use App\Models\Endpoint;
use App\Models\OnCallPlan;
use App\Models\User;
use App\Services\OnCallPlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OnCallPlan
 */
class OnCallPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userIds = app(OnCallPlanService::class)->rosterUserIds($this->layers ?? []);

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

        $team = $this->team;

        return [
            'id' => $this->id,
            'teamId' => $this->teamId,
            'team' => $team === null ? null : [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'name' => $this->name,
            'timezone' => $this->timezone,
            'layers' => $this->layers ?? [],
            'roster' => array_map(function (string $userId) use ($users, $onCallEndpoints): array {
                $user = $users->get($userId);
                $endpoint = $onCallEndpoints->get($userId);

                return [
                    'userId' => $userId,
                    'name' => $user?->name,
                    'endpoint' => $endpoint === null ? null : [
                        'id' => $endpoint->id,
                        'name' => $endpoint->name,
                        'type' => $endpoint->type,
                    ],
                ];
            }, $userIds),
            'isComplete' => $this->isComplete ?? false,
            'canEdit' => $this->canEdit ?? false,
            'canDelete' => $this->canDelete ?? false,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
