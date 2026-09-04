<?php

namespace App\Services;

use App\Enums\IncidentPolicySource;
use App\Enums\IncidentSeverity;
use App\Models\IncidentPolicy;
use App\Models\Team;
use App\Models\User;
use App\Services\IncidentPolicy\IncidentPolicyDslParser;
use App\Services\IncidentPolicy\RunbookNameResolver;
use Illuminate\Database\Eloquent\Builder;

class IncidentPolicyService
{
    public function __construct(
        private readonly TeamService $teamService,
        private readonly RunbookNameResolver $runbookResolver,
    ) {}

    /**
     * View: admin, the importer, or a member of an assigned team.
     */
    public function canView(User $user, IncidentPolicy $policy): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->id === $policy->createdBy || $user->_id === $policy->createdBy) {
            return true;
        }

        $userTeamIds = array_map('strval', $this->teamService->userTeams($user)->pluck('_id')->all());
        $policyTeamIds = array_map('strval', $policy->teamIds ?? []);

        return count(array_intersect($policyTeamIds, $userTeamIds)) > 0;
    }

    /**
     * Import/delete: admin, the importer, or an owner of an assigned team.
     */
    public function canEdit(User $user, IncidentPolicy $policy): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->id === $policy->createdBy || $user->_id === $policy->createdBy) {
            return true;
        }

        return Team::query()
            ->whereIn('_id', $policy->teamIds ?? [])
            ->get()
            ->contains(fn (Team $team) => $user->id === $team->ownerId || $user->_id === $team->ownerId);
    }

    public function applyAccessFlags(User $user, IncidentPolicy $policy): IncidentPolicy
    {
        $canEdit = $this->canEdit($user, $policy);

        $policy->setAttribute('canEdit', $canEdit);
        $policy->setAttribute('canDelete', $canEdit);

        return $policy;
    }

    /**
     * Restricts a listing to the policies the user is allowed to see.
     */
    public function applyVisibility(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $userTeamIds = array_map('strval', $this->teamService->userTeams($user)->pluck('_id')->all());

        $query->where(function (Builder $builder) use ($user, $userTeamIds) {
            $builder->where('createdBy', (string) $user->id);

            foreach ($userTeamIds as $teamId) {
                $builder->orWhere('teamIds', $teamId);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(User $user, array $validated): IncidentPolicy
    {
        $policy = IncidentPolicy::create([
            ...$this->normalize($validated),
            'version' => 1,
            'source' => IncidentPolicySource::Api,
            'createdBy' => $user->id,
            'updatedBy' => $user->id,
        ]);

        $policy->load('createdByUser');

        return $this->applyAccessFlags($user, $policy);
    }

    /**
     * A JSON update always bumps the version: unlike a YAML re-import there is no
     * unchanged case to report back, the caller sent the whole definition on purpose.
     *
     * @param  array<string, mixed>  $validated
     */
    public function update(User $user, IncidentPolicy $policy, array $validated): IncidentPolicy
    {
        $policy->update([
            ...$this->normalize($validated),
            'version' => (int) $policy->version + 1,
            'source' => IncidentPolicySource::Api,
            'updatedBy' => $user->id,
        ]);

        $policy->load('createdByUser');

        return $this->applyAccessFlags($user, $policy);
    }

    /**
     * Fills in the same defaults the YAML parser applies, so a policy written through
     * either door is stored identically.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalize(array $validated): array
    {
        $match = $validated['match'];
        $grouping = $validated['grouping'] ?? [];
        $incident = $validated['incident'] ?? [];

        return [
            'name' => $validated['name'],
            'description' => (string) ($validated['description'] ?? ''),
            'enabled' => (bool) ($validated['enabled'] ?? true),
            'ownerId' => $validated['ownerId'] ?? null,
            'teamIds' => $this->stringList($validated['teamIds']),
            'match' => [
                'alertRuleIds' => $this->stringList($match['alertRuleIds'] ?? []),
                'tags' => $this->stringList($match['tags'] ?? []),
                'serviceIds' => $this->stringList($match['serviceIds'] ?? []),
                'dataSourceTypes' => $this->stringList($match['dataSourceTypes'] ?? []),
            ],
            'grouping' => [
                'key' => $this->stringList($grouping['key'] ?? []),
                'windowMinutes' => (int) ($grouping['windowMinutes'] ?? IncidentPolicyDslParser::DEFAULT_GROUPING_WINDOW_MINUTES),
            ],
            'incident' => [
                'autoCreate' => (bool) ($incident['autoCreate'] ?? true),
                'autoResolveOnAlertClear' => (bool) ($incident['autoResolveOnAlertClear'] ?? false),
                'titleTemplate' => $incident['titleTemplate'] ?? null,
                'defaultSeverity' => $incident['defaultSeverity'] ?? IncidentPolicyDslParser::DEFAULT_SEVERITY->value,
                'severityMap' => array_map('strval', $incident['severityMap'] ?? []),
            ],
            'rules' => $this->normalizeRules($validated['rules']),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $rules
     * @return array<string, array<string, mixed>>
     */
    private function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach (IncidentSeverity::cases() as $severity) {
            $rule = $rules[$severity->value] ?? null;

            if (! is_array($rule)) {
                continue;
            }

            $postmortemRequired = (bool) ($rule['postmortem']['required'] ?? false);
            $dueDays = $this->nullableInt($rule['postmortem']['dueDays'] ?? null);
            $runbookNames = $this->stringList($rule['runbookNames'] ?? []);

            $normalized[$severity->value] = [
                'ackWithinMinutes' => $this->nullableInt($rule['ackWithinMinutes'] ?? null),
                'resolveWithinMinutes' => $this->nullableInt($rule['resolveWithinMinutes'] ?? null),
                'requireCommander' => (bool) ($rule['requireCommander'] ?? false),
                'notifyEndpointIds' => $this->stringList($rule['notifyEndpointIds'] ?? []),
                'escalation' => [
                    'onCallPlanId' => $rule['escalation']['onCallPlanId'] ?? null,
                    'useLayers' => (bool) ($rule['escalation']['useLayers'] ?? true),
                ],
                'communication' => [
                    'stakeholderUpdateEveryMinutes' => $this->nullableInt($rule['communication']['stakeholderUpdateEveryMinutes'] ?? null),
                    'statusPageUpdateRequired' => (bool) ($rule['communication']['statusPageUpdateRequired'] ?? false),
                ],
                'postmortem' => [
                    'required' => $postmortemRequired,
                    'dueDays' => $postmortemRequired ? ($dueDays ?? IncidentPolicyDslParser::DEFAULT_POSTMORTEM_DUE_DAYS) : $dueDays,
                    'reviewRequired' => (bool) ($rule['postmortem']['reviewRequired'] ?? false),
                ],
                'runbookNames' => $runbookNames,
                'runbookIds' => $this->runbookResolver->idsFor($runbookNames),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        return array_values(array_unique(array_map('strval', $values)));
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
