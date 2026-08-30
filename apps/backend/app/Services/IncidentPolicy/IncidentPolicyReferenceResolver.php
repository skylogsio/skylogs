<?php

namespace App\Services\IncidentPolicy;

use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\Profile\ProfileService;
use App\Models\Team;
use App\Models\User;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Maps the human-readable references used in the DSL onto MongoDB ids.
 *
 * A reference is either a 24 character id, a name, or a `kind:name` pair such as
 * `endpoint:oncall-sms`. Missing or ambiguous targets are reported by document path
 * instead of being silently dropped.
 */
class IncidentPolicyReferenceResolver
{
    /**
     * kind => [model, name field, label]
     *
     * @var array<string, array{0: class-string<Model>, 1: string, 2: string}>
     */
    private const REFERENCES = [
        'team' => [Team::class, 'name', 'Team'],
        'user' => [User::class, 'username', 'User'],
        'alertRule' => [AlertRule::class, 'name', 'Alert rule'],
        'service' => [ProfileService::class, 'name', 'Service'],
        'endpoint' => [Endpoint::class, 'name', 'Endpoint'],
    ];

    public function __construct(private readonly RunbookNameResolver $runbookResolver) {}

    /**
     * @return array{attributes: array<string, mixed>|null, errors: list<IncidentPolicyDslError>}
     */
    public function resolve(ParsedIncidentPolicy $policy): array
    {
        $errors = [];

        $teamIds = $this->resolveMany($policy->teamRefs, 'team', $policy->path('metadata.teams'), $errors);
        $ownerId = $policy->ownerRef === null
            ? null
            : $this->resolveOne($policy->ownerRef, 'user', $policy->path('metadata.owner'), $errors);

        $attributes = [
            'name' => $policy->name,
            'description' => $policy->description,
            'enabled' => $policy->enabled,
            'ownerId' => $ownerId,
            'teamIds' => $teamIds,
            'match' => [
                'alertRuleIds' => $this->resolveMany(
                    $policy->match['alertRules'],
                    'alertRule',
                    $policy->path('spec.match.alertRules'),
                    $errors,
                ),
                'tags' => $policy->match['tags'],
                'serviceIds' => $this->resolveMany(
                    $policy->match['services'],
                    'service',
                    $policy->path('spec.match.services'),
                    $errors,
                ),
                'dataSourceTypes' => $policy->match['dataSourceTypes'],
            ],
            'grouping' => $policy->grouping,
            'incident' => $policy->incident,
            'rules' => $this->resolveRules($policy, $errors),
        ];

        return [
            'attributes' => $errors === [] ? $attributes : null,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<IncidentPolicyDslError>  $errors
     * @return array<string, array<string, mixed>>
     */
    private function resolveRules(ParsedIncidentPolicy $policy, array &$errors): array
    {
        $rules = [];

        foreach ($policy->rules as $severity => $rule) {
            $rules[$severity] = [
                'ackWithinMinutes' => $rule['ackWithinMinutes'],
                'resolveWithinMinutes' => $rule['resolveWithinMinutes'],
                'requireCommander' => $rule['requireCommander'],
                'notifyEndpointIds' => $this->resolveMany(
                    $rule['notifyChannels'],
                    'endpoint',
                    $policy->rulePath($severity, 'notify.channels'),
                    $errors,
                ),
                'escalation' => [
                    'useLayers' => $rule['escalation']['useLayers'],
                ],
                'communication' => $rule['communication'],
                'postmortem' => $rule['postmortem'],
                'runbookNames' => $rule['runbookNames'],
                'runbookIds' => $this->runbookResolver->idsFor($rule['runbookNames']),
            ];
        }

        return $rules;
    }

    /**
     * @param  list<string>  $references
     * @param  list<IncidentPolicyDslError>  $errors
     * @return list<string>
     */
    private function resolveMany(array $references, string $kind, string $path, array &$errors, bool $indexed = true): array
    {
        if ($references === []) {
            return [];
        }

        [$modelClass, $nameField, $label] = self::REFERENCES[$kind];

        $lookups = [];

        foreach ($references as $index => $reference) {
            $referencePath = $indexed ? $path."[{$index}]" : $path;
            $value = $this->stripKind($reference, $kind, $label, $referencePath, $errors);

            if ($value !== null) {
                $lookups[$referencePath] = $value;
            }
        }

        if ($lookups === []) {
            return [];
        }

        $ids = array_values(array_filter($lookups, fn (string $value) => $this->isObjectId($value)));
        $names = array_values(array_filter($lookups, fn (string $value) => ! $this->isObjectId($value)));

        $models = $modelClass::query()
            ->where(function ($query) use ($ids, $names, $nameField) {
                if ($ids !== []) {
                    $query->whereIn('_id', $ids);
                }

                if ($names !== []) {
                    $query->orWhereIn($nameField, $names);
                }
            })
            ->get();

        $byId = [];
        $byName = [];

        foreach ($models as $model) {
            $byId[(string) $model->id] = (string) $model->id;
            $name = (string) ($model->{$nameField} ?? '');

            if ($name !== '') {
                $byName[$name][] = (string) $model->id;
            }
        }

        $resolved = [];

        foreach ($lookups as $referencePath => $value) {
            if ($this->isObjectId($value)) {
                if (! isset($byId[$value])) {
                    $errors[] = new IncidentPolicyDslError($referencePath, "{$label} '{$value}' not found.");

                    continue;
                }

                $resolved[] = $byId[$value];

                continue;
            }

            $matches = $byName[$value] ?? [];

            if ($matches === []) {
                $errors[] = new IncidentPolicyDslError($referencePath, "{$label} '{$value}' not found.");

                continue;
            }

            if (count($matches) > 1) {
                $errors[] = new IncidentPolicyDslError(
                    $referencePath,
                    "{$label} '{$value}' is ambiguous, ".count($matches).' records share that name. Reference it by id instead.',
                );

                continue;
            }

            $resolved[] = $matches[0];
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param  list<IncidentPolicyDslError>  $errors
     */
    private function stripKind(string $reference, string $kind, string $label, string $path, array &$errors): ?string
    {
        $reference = trim($reference);

        if (! str_contains($reference, ':')) {
            return $reference === '' ? null : $reference;
        }

        [$prefix, $value] = explode(':', $reference, 2);

        if ($prefix !== $kind) {
            $errors[] = new IncidentPolicyDslError(
                $path,
                "Expected a {$label} reference but got '{$prefix}:'. Use '{$kind}:name' or a bare name.",
            );

            return null;
        }

        $value = trim($value);

        if ($value === '') {
            $errors[] = new IncidentPolicyDslError($path, "Missing {$label} name after '{$kind}:'.");

            return null;
        }

        return $value;
    }

    private function isObjectId(string $value): bool
    {
        return preg_match('/^[0-9a-fA-F]{24}$/', $value) === 1;
    }
}
