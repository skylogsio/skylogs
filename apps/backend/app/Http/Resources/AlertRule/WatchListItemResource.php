<?php

namespace App\Http\Resources\AlertRule;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AlertRule
 */
class WatchListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        [$statusLabel, $statusCount] = $this->getStatus();
        $type = $this->type instanceof AlertRuleType ? $this->type->value : $this->type;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $type,
            'description' => $this->description ?? '',
            'tags' => $this->tags ?? [],
            'state' => $this->state,
            'statusLabel' => $statusLabel,
            'statusCount' => $statusCount,
            'isWatched' => true,
        ];
    }
}
