<?php

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Collection;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;
use MongoDB\Laravel\Relations\HasOne;

class Incident extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected $attributes = [
        'description' => '',
        'status' => IncidentStatus::Open->value,
        'source' => IncidentSource::Manual->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'source' => IncidentSource::class,
            'startedAt' => 'datetime',
            'detectedAt' => 'datetime',
            'resolvedAt' => 'datetime',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdBy', '_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolvedBy', '_id');
    }

    public function commanderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commanderId', '_id');
    }

    public function postMortem(): HasOne
    {
        return $this->hasOne(PostMortem::class, 'incidentId', '_id');
    }

    public function timelineEntries(): HasMany
    {
        return $this->hasMany(IncidentTimelineEntry::class, 'incidentId', '_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(IncidentDocument::class, 'incidentId', '_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(IncidentActionItem::class, 'incidentId', '_id');
    }

    /**
     * @return Collection<int, Team>
     */
    public function resolveTeams(): Collection
    {
        if (empty($this->teamIds)) {
            return new Collection;
        }

        return Team::query()
            ->whereIn('_id', $this->teamIds)
            ->with('onCallPlan')
            ->get();
    }

    /**
     * @return Collection<int, AlertRule>
     */
    public function resolveAlertRules(): Collection
    {
        if (empty($this->alertRuleIds)) {
            return new Collection;
        }

        return AlertRule::query()
            ->whereIn('_id', $this->alertRuleIds)
            ->get();
    }

    /**
     * @return array{teamId: string, acknowledgedBy: string, acknowledgedAt: mixed}|null
     */
    public function acknowledgementForTeam(string $teamId): ?array
    {
        foreach ($this->acknowledgements ?? [] as $acknowledgement) {
            if ((string) ($acknowledgement['teamId'] ?? '') === $teamId) {
                return $acknowledgement;
            }
        }

        return null;
    }

    public function hasTeamAcknowledged(string $teamId): bool
    {
        return $this->acknowledgementForTeam($teamId) !== null;
    }

    public function hasAllTeamsAcknowledged(): bool
    {
        return $this->unacknowledgedTeamIds() === [];
    }

    /**
     * @return list<string>
     */
    public function unacknowledgedTeamIds(): array
    {
        return array_values(array_filter(
            array_values(array_unique(array_map('strval', $this->teamIds ?? []))),
            fn (string $teamId): bool => ! $this->hasTeamAcknowledged($teamId),
        ));
    }

    /**
     * Outstanding policy follow-through for related staff. Null once the incident is resolved.
     *
     * @return array{
     *     unacknowledgedTeamIds: list<string>,
     *     commanderRequired: bool,
     *     statusPageUpdateRequired: bool,
     *     postmortemRequired: bool
     * }|null
     */
    public function remaining(): ?array
    {
        if ($this->status === IncidentStatus::Resolved) {
            return null;
        }

        $sla = $this->policySla ?? [];

        return [
            'unacknowledgedTeamIds' => $this->unacknowledgedTeamIds(),
            'commanderRequired' => (bool) (($sla['requireCommander'] ?? false) && empty($this->commanderId)),
            'statusPageUpdateRequired' => (bool) ($sla['statusPageUpdateRequired'] ?? false),
            'postmortemRequired' => (bool) ($sla['postmortemRequired'] ?? false),
        ];
    }
}
