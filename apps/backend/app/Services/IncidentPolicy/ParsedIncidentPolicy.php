<?php

namespace App\Services\IncidentPolicy;

/**
 * A structurally valid policy definition whose references are still human-readable names.
 *
 * @property-read list<string> $teamRefs
 */
class ParsedIncidentPolicy
{
    /**
     * @param  list<string>  $teamRefs
     * @param  array{alertRules: list<string>, tags: list<string>, services: list<string>, dataSourceTypes: list<string>}  $match
     * @param  array{key: list<string>, windowMinutes: int}  $grouping
     * @param  array{autoCreate: bool, autoResolveOnAlertClear: bool, titleTemplate: string|null, defaultSeverity: string, severityMap: array<string, string>}  $incident
     * @param  array<string, array<string, mixed>>  $rules  keyed by severity
     * @param  array<string, int>  $ruleIndexes  severity to its position in the document, for error paths
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ?string $ownerRef,
        public readonly array $teamRefs,
        public readonly bool $enabled,
        public readonly array $match,
        public readonly array $grouping,
        public readonly array $incident,
        public readonly array $rules,
        public readonly array $ruleIndexes = [],
        public readonly string $pathPrefix = '',
    ) {}

    /**
     * Document path of a rule, e.g. `spec.rules[0]`.
     */
    public function rulePath(string $severity, string $suffix = ''): string
    {
        $index = $this->ruleIndexes[$severity] ?? 0;

        return $this->path("spec.rules[{$index}]".($suffix === '' ? '' : '.'.$suffix));
    }

    /**
     * Full document path for an error, e.g. `documents[1].spec.match.services[0]`.
     */
    public function path(string $suffix): string
    {
        return $this->pathPrefix.$suffix;
    }
}
