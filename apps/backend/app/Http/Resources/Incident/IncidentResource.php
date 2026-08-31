<?php

namespace App\Http\Resources\Incident;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Incident
 */
class IncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $teams = $this->resolveTeams();
        $alertRules = $this->resolveAlertRules();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity,
            'status' => $this->status,
            'source' => $this->source,
            'policyId' => $this->policyId,
            'groupingKey' => $this->groupingKey,
            'startedAt' => $this->startedAt,
            'detectedAt' => $this->detectedAt,
            'resolvedAt' => $this->resolvedAt,
            'teamIds' => $this->teamIds,
            'tags' => $this->tags ?? [],
            'alertRuleIds' => $this->alertRuleIds ?? [],
            'createdBy' => $this->createdBy,
            'createdByUser' => $this->whenLoaded('createdByUser', fn () => [
                'id' => $this->createdByUser->id,
                'name' => $this->createdByUser->name,
            ]),
            'resolvedBy' => $this->resolvedBy,
            'acknowledgements' => $this->acknowledgements ?? [],
            'teams' => $teams->map(function ($team) {
                $acknowledgement = $this->acknowledgementForTeam((string) $team->id);

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'onCallPlan' => $team->onCallPlan ? [
                        'id' => $team->onCallPlan->id,
                        'name' => $team->onCallPlan->name,
                    ] : null,
                    'acknowledgement' => $acknowledgement ? [
                        'acknowledgedBy' => $acknowledgement['acknowledgedBy'],
                        'acknowledgedAt' => $acknowledgement['acknowledgedAt'],
                    ] : null,
                ];
            }),
            'alertRules' => $alertRules->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
            ]),
            'postMortem' => $this->postMortemSummary(),
            'counts' => $this->counts ?? null,
            'canEdit' => $this->canEdit ?? false,
            'canDelete' => $this->canDelete ?? false,
            'canAcknowledge' => $this->canAcknowledge ?? false,
            'canResolve' => $this->canResolve ?? false,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    /**
     * Enough of the postmortem to drive a badge and a link; the document itself is served
     * by `GET /api/v1/incident/{id}/postmortem`.
     *
     * @return array<string, mixed>|null
     */
    private function postMortemSummary(): ?array
    {
        $postMortem = $this->postMortem;

        if ($postMortem === null) {
            return null;
        }

        return [
            'id' => $postMortem->id,
            'status' => $postMortem->status,
            'authorId' => $postMortem->authorId,
            'dueAt' => $postMortem->dueAt,
            'publishedAt' => $postMortem->publishedAt,
        ];
    }
}
