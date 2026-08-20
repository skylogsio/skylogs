<?php

namespace App\Services\IncidentPolicy;

use App\Enums\IncidentSeverity;
use App\Models\Service;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Turns the incident policy YAML DSL into structurally valid definitions.
 *
 * References stay as names here; `IncidentPolicyReferenceResolver` maps them to ids.
 */
class IncidentPolicyDslParser
{
    public const API_VERSION = 'skylogs.io/v1';

    public const KIND = 'IncidentPolicy';

    public const DEFAULT_GROUPING_WINDOW_MINUTES = 15;

    public const DEFAULT_POSTMORTEM_DUE_DAYS = 5;

    public const DEFAULT_SEVERITY = IncidentSeverity::Sev3;

    /**
     * @var list<string>
     */
    public const GROUPING_KEYS = ['serviceId', 'alertRuleId', 'tag', 'dataSourceType'];

    /**
     * @var list<string>
     */
    private const MATCHERS = ['alertRules', 'tags', 'services', 'dataSourceTypes'];

    /**
     * Allowed keys per object, so a typo cannot silently disable a rule.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_KEYS = [
        'root' => ['apiVersion', 'kind', 'metadata', 'spec'],
        'metadata' => ['name', 'description', 'owner', 'teams'],
        'spec' => ['enabled', 'match', 'grouping', 'incident', 'rules'],
        'spec.match' => self::MATCHERS,
        'spec.grouping' => ['key', 'windowMinutes'],
        'spec.incident' => ['autoCreate', 'autoResolveOnAlertClear', 'titleTemplate', 'defaultSeverity', 'severityMap'],
        'rule' => ['severity', 'ack', 'resolve', 'requireCommander', 'notify', 'escalation', 'communication', 'postmortem', 'runbooks'],
        'rule.ack' => ['withinMinutes'],
        'rule.resolve' => ['withinMinutes'],
        'rule.notify' => ['channels'],
        'rule.escalation' => ['onCallPlan', 'useLayers'],
        'rule.communication' => ['stakeholderUpdateEveryMinutes', 'statusPageUpdateRequired'],
        'rule.postmortem' => ['required', 'dueDays', 'reviewRequired'],
    ];

    public function parse(string $yaml): IncidentPolicyDslParseResult
    {
        $documents = $this->splitDocuments($yaml);

        if ($documents === []) {
            return new IncidentPolicyDslParseResult([], [
                new IncidentPolicyDslError('document', 'The YAML input is empty.'),
            ]);
        }

        $multiple = count($documents) > 1;
        $policies = [];
        $errors = [];

        foreach ($documents as $index => $document) {
            $prefix = $multiple ? "documents[{$index}]." : '';
            $documentPath = $multiple ? "documents[{$index}]" : 'document';

            $decoded = $this->decode($document, $documentPath);

            if (! is_array($decoded)) {
                $errors[] = $decoded;

                continue;
            }

            $documentErrors = [
                ...$this->unknownKeyErrors($decoded, $prefix),
                ...$this->structureErrors($decoded, $prefix),
            ];

            if ($documentErrors !== []) {
                $errors = [...$errors, ...$documentErrors];

                continue;
            }

            $semanticErrors = $this->semanticErrors($decoded, $prefix);

            if ($semanticErrors !== []) {
                $errors = [...$errors, ...$semanticErrors];

                continue;
            }

            $policies[] = $this->normalize($decoded, $prefix);
        }

        return new IncidentPolicyDslParseResult(
            $policies,
            [...$errors, ...$this->duplicateNameErrors($policies)],
        );
    }

    /**
     * @return list<string>
     */
    private function splitDocuments(string $yaml): array
    {
        $chunks = preg_split('/^---[ \t]*(?:#.*)?$/m', $yaml) ?: [];

        return array_values(array_filter(
            array_map('trim', $chunks),
            fn (string $chunk) => $chunk !== '' && ! $this->isCommentOnly($chunk),
        ));
    }

    private function isCommentOnly(string $chunk): bool
    {
        foreach (preg_split('/\R/', $chunk) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '' && ! str_starts_with($line, '#')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|IncidentPolicyDslError
     */
    private function decode(string $document, string $documentPath): array|IncidentPolicyDslError
    {
        try {
            $decoded = Yaml::parse($document);
        } catch (ParseException $exception) {
            return new IncidentPolicyDslError($documentPath, 'Invalid YAML: '.$exception->getMessage());
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            return new IncidentPolicyDslError(
                $documentPath,
                'Expected a mapping with apiVersion, kind, metadata and spec.',
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<IncidentPolicyDslError>
     */
    private function unknownKeyErrors(array $document, string $prefix): array
    {
        $errors = [];

        $this->collectUnknownKeys($document, 'root', $prefix, $errors);
        $this->collectUnknownKeys($document['metadata'] ?? null, 'metadata', $prefix.'metadata.', $errors);
        $this->collectUnknownKeys($document['spec'] ?? null, 'spec', $prefix.'spec.', $errors);

        foreach (['match', 'grouping', 'incident'] as $section) {
            $this->collectUnknownKeys(
                $document['spec'][$section] ?? null,
                'spec.'.$section,
                $prefix.'spec.'.$section.'.',
                $errors,
            );
        }

        foreach ($this->ruleList($document) as $index => $rule) {
            $rulePath = $prefix."spec.rules[{$index}].";
            $this->collectUnknownKeys($rule, 'rule', $rulePath, $errors);

            foreach (['ack', 'resolve', 'notify', 'escalation', 'communication', 'postmortem'] as $section) {
                $this->collectUnknownKeys(
                    is_array($rule) ? ($rule[$section] ?? null) : null,
                    'rule.'.$section,
                    $rulePath.$section.'.',
                    $errors,
                );
            }
        }

        return $errors;
    }

    /**
     * @param  list<IncidentPolicyDslError>  $errors
     */
    private function collectUnknownKeys(mixed $node, string $schemaKey, string $path, array &$errors): void
    {
        if (! is_array($node)) {
            return;
        }

        foreach (array_keys($node) as $key) {
            if (is_int($key) || in_array($key, self::ALLOWED_KEYS[$schemaKey], true)) {
                continue;
            }

            $errors[] = new IncidentPolicyDslError($path.$key, "Unknown field '{$key}'.");
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<IncidentPolicyDslError>
     */
    private function structureErrors(array $document, string $prefix): array
    {
        $validator = Validator::make($document, $this->documentRules(), [], $this->attributeLabels());

        if ($validator->passes()) {
            return [];
        }

        $errors = [];

        foreach ($validator->errors()->messages() as $key => $messages) {
            foreach ($messages as $message) {
                $errors[] = new IncidentPolicyDslError($prefix.$this->bracketPath($key), $message);
            }
        }

        return $errors;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function documentRules(): array
    {
        return [
            'apiVersion' => ['required', 'string', Rule::in([self::API_VERSION])],
            'kind' => ['required', 'string', Rule::in([self::KIND])],

            'metadata' => ['required', 'array'],
            'metadata.name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'metadata.description' => ['nullable', 'string', 'max:1000'],
            'metadata.owner' => ['nullable', 'string', 'max:120'],
            'metadata.teams' => ['required', 'array', 'min:1'],
            'metadata.teams.*' => ['required', 'string', 'max:120'],

            'spec' => ['required', 'array'],
            'spec.enabled' => ['nullable', 'boolean'],

            'spec.match' => ['required', 'array'],
            'spec.match.alertRules' => ['nullable', 'array'],
            'spec.match.alertRules.*' => ['required', 'string', 'max:255'],
            'spec.match.tags' => ['nullable', 'array'],
            'spec.match.tags.*' => ['required', 'string', 'max:120'],
            'spec.match.services' => ['nullable', 'array'],
            'spec.match.services.*' => ['required', 'string', 'max:255'],
            'spec.match.dataSourceTypes' => ['nullable', 'array'],
            'spec.match.dataSourceTypes.*' => ['required', 'string', Rule::in(array_keys(Service::$types))],

            'spec.grouping' => ['nullable', 'array'],
            'spec.grouping.key' => ['nullable', 'array'],
            'spec.grouping.key.*' => ['required', 'string', Rule::in(self::GROUPING_KEYS)],
            'spec.grouping.windowMinutes' => ['nullable', 'integer', 'min:1', 'max:1440'],

            'spec.incident' => ['nullable', 'array'],
            'spec.incident.autoCreate' => ['nullable', 'boolean'],
            'spec.incident.autoResolveOnAlertClear' => ['nullable', 'boolean'],
            'spec.incident.titleTemplate' => ['nullable', 'string', 'max:255'],
            'spec.incident.defaultSeverity' => ['nullable', Rule::enum(IncidentSeverity::class)],
            'spec.incident.severityMap' => ['nullable', 'array'],
            'spec.incident.severityMap.*' => ['required', Rule::enum(IncidentSeverity::class)],

            'spec.rules' => ['required', 'array', 'min:1'],
            'spec.rules.*' => ['required', 'array'],
            'spec.rules.*.severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'spec.rules.*.ack' => ['nullable', 'array'],
            'spec.rules.*.ack.withinMinutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'spec.rules.*.resolve' => ['nullable', 'array'],
            'spec.rules.*.resolve.withinMinutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'spec.rules.*.requireCommander' => ['nullable', 'boolean'],
            'spec.rules.*.notify' => ['nullable', 'array'],
            'spec.rules.*.notify.channels' => ['nullable', 'array'],
            'spec.rules.*.notify.channels.*' => ['required', 'string', 'max:255'],
            'spec.rules.*.escalation' => ['nullable', 'array'],
            'spec.rules.*.escalation.onCallPlan' => ['nullable', 'string', 'max:255'],
            'spec.rules.*.escalation.useLayers' => ['nullable', 'boolean'],
            'spec.rules.*.communication' => ['nullable', 'array'],
            'spec.rules.*.communication.stakeholderUpdateEveryMinutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'spec.rules.*.communication.statusPageUpdateRequired' => ['nullable', 'boolean'],
            'spec.rules.*.postmortem' => ['nullable', 'array'],
            'spec.rules.*.postmortem.required' => ['nullable', 'boolean'],
            'spec.rules.*.postmortem.dueDays' => ['nullable', 'integer', 'min:1', 'max:365'],
            'spec.rules.*.postmortem.reviewRequired' => ['nullable', 'boolean'],
            'spec.rules.*.runbooks' => ['nullable', 'array'],
            'spec.rules.*.runbooks.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Keeps validation messages readable by naming the field rather than the wildcard path.
     *
     * @return array<string, string>
     */
    private function attributeLabels(): array
    {
        $labels = [];

        foreach (array_keys($this->documentRules()) as $key) {
            $position = strrpos($key, '*.');
            $label = $position === false ? $key : substr($key, $position + 2);

            $labels[$key] = str_ends_with($label, '.*') ? substr($label, 0, -2) : $label;
        }

        return $labels;
    }

    private function bracketPath(string $key): string
    {
        return preg_replace('/\.(\d+)(?=\.|$)/', '[$1]', $key) ?? $key;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<IncidentPolicyDslError>
     */
    private function semanticErrors(array $document, string $prefix): array
    {
        $errors = [];
        $match = $document['spec']['match'];

        $hasMatcher = array_filter(
            self::MATCHERS,
            fn (string $matcher) => ! empty($match[$matcher]),
        ) !== [];

        if (! $hasMatcher) {
            $errors[] = new IncidentPolicyDslError(
                $prefix.'spec.match',
                'At least one of alertRules, tags, services or dataSourceTypes is required, otherwise the policy would match every alert.',
            );
        }

        $seenSeverities = [];

        foreach ($this->ruleList($document) as $index => $rule) {
            $severity = $rule['severity'];

            if (isset($seenSeverities[$severity])) {
                $errors[] = new IncidentPolicyDslError(
                    $prefix."spec.rules[{$index}].severity",
                    "Duplicate rule for severity '{$severity}'.",
                );
            }

            $seenSeverities[$severity] = true;

            $ackWithin = $rule['ack']['withinMinutes'] ?? null;
            $resolveWithin = $rule['resolve']['withinMinutes'] ?? null;

            if ($ackWithin !== null && $resolveWithin !== null && $resolveWithin < $ackWithin) {
                $errors[] = new IncidentPolicyDslError(
                    $prefix."spec.rules[{$index}].resolve.withinMinutes",
                    'Must be greater than or equal to ack.withinMinutes.',
                );
            }
        }

        return $errors;
    }

    /**
     * @param  list<ParsedIncidentPolicy>  $policies
     * @return list<IncidentPolicyDslError>
     */
    private function duplicateNameErrors(array $policies): array
    {
        $errors = [];
        $seen = [];

        foreach ($policies as $policy) {
            if (isset($seen[$policy->name])) {
                $errors[] = new IncidentPolicyDslError(
                    $policy->path('metadata.name'),
                    "Duplicate policy name '{$policy->name}' in the same input.",
                );
            }

            $seen[$policy->name] = true;
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<int|string, mixed>
     */
    private function ruleList(array $document): array
    {
        $rules = $document['spec']['rules'] ?? null;

        return is_array($rules) ? $rules : [];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function normalize(array $document, string $prefix): ParsedIncidentPolicy
    {
        $metadata = $document['metadata'];
        $spec = $document['spec'];
        $match = $spec['match'];
        $grouping = $spec['grouping'] ?? [];
        $incident = $spec['incident'] ?? [];

        $rules = [];
        $ruleIndexes = [];

        foreach (array_values($this->ruleList($document)) as $index => $rule) {
            $rules[$rule['severity']] = $this->normalizeRule($rule);
            $ruleIndexes[$rule['severity']] = $index;
        }

        return new ParsedIncidentPolicy(
            name: $metadata['name'],
            description: (string) ($metadata['description'] ?? ''),
            ownerRef: $metadata['owner'] ?? null,
            teamRefs: array_values($metadata['teams']),
            enabled: (bool) ($spec['enabled'] ?? true),
            match: [
                'alertRules' => $this->stringList($match['alertRules'] ?? []),
                'tags' => $this->stringList($match['tags'] ?? []),
                'services' => $this->stringList($match['services'] ?? []),
                'dataSourceTypes' => $this->stringList($match['dataSourceTypes'] ?? []),
            ],
            grouping: [
                'key' => $this->stringList($grouping['key'] ?? []),
                'windowMinutes' => (int) ($grouping['windowMinutes'] ?? self::DEFAULT_GROUPING_WINDOW_MINUTES),
            ],
            incident: [
                'autoCreate' => (bool) ($incident['autoCreate'] ?? true),
                'autoResolveOnAlertClear' => (bool) ($incident['autoResolveOnAlertClear'] ?? false),
                'titleTemplate' => $incident['titleTemplate'] ?? null,
                'defaultSeverity' => $incident['defaultSeverity'] ?? self::DEFAULT_SEVERITY->value,
                'severityMap' => array_map('strval', $incident['severityMap'] ?? []),
            ],
            rules: $rules,
            ruleIndexes: $ruleIndexes,
            pathPrefix: $prefix,
        );
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function normalizeRule(array $rule): array
    {
        $postmortemRequired = (bool) ($rule['postmortem']['required'] ?? false);
        $dueDays = $rule['postmortem']['dueDays'] ?? null;

        return [
            'ackWithinMinutes' => $rule['ack']['withinMinutes'] ?? null,
            'resolveWithinMinutes' => $rule['resolve']['withinMinutes'] ?? null,
            'requireCommander' => (bool) ($rule['requireCommander'] ?? false),
            'notifyChannels' => $this->stringList($rule['notify']['channels'] ?? []),
            'escalation' => [
                'onCallPlan' => $rule['escalation']['onCallPlan'] ?? null,
                'useLayers' => (bool) ($rule['escalation']['useLayers'] ?? true),
            ],
            'communication' => [
                'stakeholderUpdateEveryMinutes' => $rule['communication']['stakeholderUpdateEveryMinutes'] ?? null,
                'statusPageUpdateRequired' => (bool) ($rule['communication']['statusPageUpdateRequired'] ?? false),
            ],
            'postmortem' => [
                'required' => $postmortemRequired,
                'dueDays' => $postmortemRequired ? ($dueDays ?? self::DEFAULT_POSTMORTEM_DUE_DAYS) : $dueDays,
                'reviewRequired' => (bool) ($rule['postmortem']['reviewRequired'] ?? false),
            ],
            'runbookNames' => $this->stringList($rule['runbooks'] ?? []),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        return array_values(array_unique(array_map('strval', $values)));
    }
}
