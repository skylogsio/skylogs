<?php

namespace App\Models;

use App\Enums\IncidentPolicySource;
use App\Enums\IncidentSeverity;
use Illuminate\Database\Eloquent\Collection;
use MongoDB\Laravel\Relations\BelongsTo;

/**
 * Response policy governing how incidents are created and handled.
 *
 * Rules are keyed by severity so a lookup for a given incident is a single map read.
 * `runbookNames` is authoritative: a policy may reference a runbook that has not been
 * written yet, and `runbookIds` mirrors only the names that currently resolve.
 *
 * @property array{
 *     alertRuleIds: list<string>,
 *     tags: list<string>,
 *     serviceIds: list<string>,
 *     dataSourceTypes: list<string>
 * } $match
 * @property array{key: list<string>, windowMinutes: int} $grouping
 * @property array{
 *     autoCreate: bool,
 *     autoResolveOnAlertClear: bool,
 *     titleTemplate: string|null,
 *     defaultSeverity: string,
 *     severityMap: array<string, string>
 * } $incident
 * @property array<string, array{
 *     ackWithinMinutes: int|null,
 *     resolveWithinMinutes: int|null,
 *     requireCommander: bool,
 *     notifyEndpointIds: list<string>,
 *     escalation: array{useLayers: bool},
 *     communication: array{stakeholderUpdateEveryMinutes: int|null, statusPageUpdateRequired: bool},
 *     postmortem: array{required: bool, dueDays: int|null, reviewRequired: bool},
 *     runbookNames: list<string>,
 *     runbookIds: list<string>
 * }> $rules
 */
class IncidentPolicy extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected $attributes = [
        'description' => '',
        'enabled' => true,
        'version' => 1,
        'source' => IncidentPolicySource::Yaml->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => IncidentPolicySource::class,
            'enabled' => 'boolean',
            'version' => 'integer',
            'match' => 'array',
            'grouping' => 'array',
            'incident' => 'array',
            'rules' => 'array',
        ];
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ownerId', '_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdBy', '_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updatedBy', '_id');
    }

    /**
     * @return Collection<int, Team>
     */
    public function resolveTeams(): Collection
    {
        if (empty($this->teamIds)) {
            return new Collection;
        }

        return Team::query()->whereIn('_id', $this->teamIds)->get();
    }

    /**
     * Severity-specific rule, or null when the policy says nothing about that severity.
     *
     * @return array<string, mixed>|null
     */
    public function ruleFor(IncidentSeverity $severity): ?array
    {
        return $this->rules[$severity->value] ?? null;
    }

    /**
     * @return list<IncidentSeverity>
     */
    public function coveredSeverities(): array
    {
        return array_values(array_filter(array_map(
            fn (string $value) => IncidentSeverity::tryFrom($value),
            array_keys($this->rules ?? []),
        )));
    }
}
