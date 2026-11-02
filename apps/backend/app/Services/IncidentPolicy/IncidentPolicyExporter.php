<?php

namespace App\Services\IncidentPolicy;

use App\Enums\IncidentSeverity;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\IncidentPolicy;
use App\Models\Profile\ProfileService;
use App\Models\Team;
use App\Models\User;
use Symfony\Component\Yaml\Yaml;

/**
 * Renders a stored policy back into the DSL.
 *
 * Ids become names again so the output is reviewable, and values that equal a DSL
 * default are omitted, which keeps export -> import -> export stable.
 */
class IncidentPolicyExporter
{
    public function export(IncidentPolicy $policy): string
    {
        return Yaml::dump($this->toDocument($policy), 8, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDocument(IncidentPolicy $policy): array
    {
        return [
            'apiVersion' => IncidentPolicyDslParser::API_VERSION,
            'kind' => IncidentPolicyDslParser::KIND,
            'metadata' => $this->metadata($policy),
            'spec' => $this->spec($policy),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(IncidentPolicy $policy): array
    {
        $owner = $policy->ownerId === null
            ? null
            : ($this->nameMap(User::class, 'username', [$policy->ownerId])[$policy->ownerId] ?? null);

        return $this->omitEmpty([
            'name' => $policy->name,
            'description' => $policy->description,
            'owner' => $owner === null ? null : 'user:'.$owner,
            'teams' => $this->namesFor(Team::class, 'name', $policy->teamIds ?? []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(IncidentPolicy $policy): array
    {
        $match = $policy->match ?? [];
        $grouping = $policy->grouping ?? [];
        $incident = $policy->incident ?? [];

        return $this->omitEmpty([
            'enabled' => $policy->enabled,
            'match' => $this->omitEmpty([
                'alertRules' => $this->namesFor(AlertRule::class, 'name', $match['alertRuleIds'] ?? []),
                'tags' => $match['tags'] ?? [],
                'services' => $this->namesFor(ProfileService::class, 'name', $match['serviceIds'] ?? []),
                'dataSourceTypes' => $match['dataSourceTypes'] ?? [],
            ]),
            'grouping' => empty($grouping['key']) ? null : [
                'key' => $grouping['key'],
                'windowMinutes' => $grouping['windowMinutes'] ?? IncidentPolicyDslParser::DEFAULT_GROUPING_WINDOW_MINUTES,
            ],
            'incident' => $this->omitEmpty([
                'autoCreate' => $incident['autoCreate'] ?? true,
                'autoResolveOnAlertClear' => ($incident['autoResolveOnAlertClear'] ?? false) ?: null,
                'titleTemplate' => $incident['titleTemplate'] ?? null,
                'defaultSeverity' => $incident['defaultSeverity'] ?? IncidentPolicyDslParser::DEFAULT_SEVERITY->value,
                'severityMap' => $incident['severityMap'] ?? [],
            ]),
            'rules' => $this->rules($policy),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rules(IncidentPolicy $policy): array
    {
        $stored = $policy->rules ?? [];
        $endpointIds = [];

        foreach ($stored as $rule) {
            $endpointIds = [...$endpointIds, ...($rule['notifyEndpointIds'] ?? [])];
        }

        $endpointNames = $this->nameMap(Endpoint::class, 'name', $endpointIds);
        $rules = [];

        foreach (IncidentSeverity::cases() as $severity) {
            $rule = $stored[$severity->value] ?? null;

            if ($rule === null) {
                continue;
            }

            $rules[] = $this->omitEmpty([
                'severity' => $severity->value,
                'ack' => $rule['ackWithinMinutes'] === null ? null : ['withinMinutes' => $rule['ackWithinMinutes']],
                'resolve' => $rule['resolveWithinMinutes'] === null ? null : ['withinMinutes' => $rule['resolveWithinMinutes']],
                'requireCommander' => ($rule['requireCommander'] ?? false) ?: null,
                'notify' => empty($rule['notifyEndpointIds']) ? null : [
                    'channels' => array_map(
                        fn (string $id) => 'endpoint:'.($endpointNames[$id] ?? $id),
                        $rule['notifyEndpointIds'],
                    ),
                ],
                'escalation' => ($rule['escalation']['useLayers'] ?? true) === true ? null : [
                    'useLayers' => false,
                ],
                'communication' => $this->omitEmpty([
                    'stakeholderUpdateEveryMinutes' => $rule['communication']['stakeholderUpdateEveryMinutes'] ?? null,
                    'statusPageUpdateRequired' => ($rule['communication']['statusPageUpdateRequired'] ?? false) ?: null,
                ]),
                'postmortem' => ($rule['postmortem']['required'] ?? false) === false ? null : $this->omitEmpty([
                    'required' => true,
                    'dueDays' => $rule['postmortem']['dueDays'] ?? null,
                    'reviewRequired' => ($rule['postmortem']['reviewRequired'] ?? false) ?: null,
                ]),
                'runbooks' => $rule['runbookNames'] ?? [],
            ]);
        }

        return $rules;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function namesFor(string $modelClass, string $nameField, array $ids): array
    {
        $map = $this->nameMap($modelClass, $nameField, $ids);

        return array_values(array_map(fn (string $id) => $map[$id] ?? $id, $ids));
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    private function nameMap(string $modelClass, string $nameField, array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        return $modelClass::query()
            ->whereIn('_id', $ids)
            ->get()
            ->mapWithKeys(fn ($model) => [(string) $model->id => (string) ($model->{$nameField} ?? $model->id)])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function omitEmpty(array $values): array
    {
        return array_filter(
            $values,
            fn (mixed $value) => $value !== null && $value !== '' && $value !== [],
        );
    }
}
