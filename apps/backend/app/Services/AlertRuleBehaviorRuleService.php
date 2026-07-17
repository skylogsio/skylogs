<?php

namespace App\Services;

use App\Enums\AlertRuleBehaviorRuleType;
use App\Enums\AlertRuleType;
use App\Helpers\Utilities;
use App\Models\AlertRule;
use App\Models\Endpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AlertRuleBehaviorRuleService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function silentRules(AlertRule $alertRule): Collection
    {
        return collect($alertRule->rules ?? [])
            ->filter(fn (array $rule) => ($rule['type'] ?? null) === AlertRuleBehaviorRuleType::SILENT->value);
    }

    /**
     * Default alert-rule endpoints plus notification-rule endpoints when filters match.
     *
     * @param  array<string, mixed>  $notifyAlert
     * @return list<string>
     */
    public function resolveEndpointIds(AlertRule $alertRule, array $notifyAlert): array
    {
        $endpointIds = collect($alertRule->endpointIds ?? []);

        $notificationRules = $this->notificationRules($alertRule);
        if ($notificationRules->isEmpty()) {
            return $endpointIds->unique()->values()->all();
        }

        $contexts = $this->extractAlertContexts($alertRule, $notifyAlert);

        foreach ($notificationRules as $rule) {
            $filterEntries = $this->normalizeFilterEntries($rule['filters'] ?? []);
            if ($filterEntries === []) {
                continue;
            }

            if ($this->notificationRuleMatches($alertRule, $filterEntries, $contexts)) {
                $endpointIds = $endpointIds->merge($rule['endpointIds'] ?? []);
            }
        }

        return $endpointIds->unique()->values()->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function notificationRules(AlertRule $alertRule): Collection
    {
        return collect($alertRule->rules ?? [])
            ->filter(fn (array $rule) => ($rule['type'] ?? null) === AlertRuleBehaviorRuleType::NOTIFICATION->value);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function templateRules(AlertRule $alertRule): Collection
    {
        return collect($alertRule->rules ?? [])
            ->filter(fn (array $rule) => ($rule['type'] ?? null) === AlertRuleBehaviorRuleType::TEMPLATE->value);
    }

    /**
     * @return array<string, string> endpoint id => template text
     */
    public function resolveEndpointTemplates(AlertRule $alertRule): array
    {
        $endpointTemplates = [];

        foreach ($this->templateRules($alertRule) as $rule) {
            $template = trim((string) ($rule['template'] ?? ''));
            if ($template === '') {
                continue;
            }

            foreach ($rule['endpointIds'] ?? [] as $endpointId) {
                $endpointTemplates[(string) $endpointId] = $template;
            }
        }

        return $endpointTemplates;
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $filters
     * @return list<array{key: string, value: string}>
     */
    public function normalizeFilterEntries(array $filters): array
    {
        if ($filters === []) {
            return [];
        }

        if ($this->isFilterEntryList($filters)) {
            $entries = [];

            foreach ($filters as $filter) {
                if (! is_array($filter)) {
                    continue;
                }

                $key = trim((string) ($filter['key'] ?? ''));
                $value = trim((string) ($filter['value'] ?? ''));

                if ($key !== '' && $value !== '') {
                    $entries[] = [
                        'key' => $key,
                        'value' => $value,
                    ];
                }
            }

            return $entries;
        }

        $entries = [];

        foreach ($filters as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $key = trim((string) $key);
            $value = trim((string) $value);

            if ($key !== '' && $value !== '') {
                $entries[] = [
                    'key' => $key,
                    'value' => $value,
                ];
            }
        }

        return $entries;
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach ($this->normalizeFilterEntries($filters) as $entry) {
            $normalized[$entry['key']] = $entry['value'];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<int, array<string, mixed>>
     */
    public function formatRulesForApi(array $rules): array
    {
        $endpointIds = collect($rules)
            ->flatMap(fn (array $rule) => $rule['endpointIds'] ?? [])
            ->unique()
            ->values()
            ->all();

        $alertRuleIds = collect($rules)
            ->flatMap(fn (array $rule) => $rule['dependsOnAlertRuleIds'] ?? [])
            ->unique()
            ->values()
            ->all();

        $endpointNameById = $this->endpointNamesByIds($endpointIds);
        $alertRuleNamesByIds = $this->alertRuleNamesByIds($alertRuleIds);

        return collect($rules)->map(function (array $rule) use ($endpointNameById, $alertRuleNamesByIds) {
            $rule['name'] = trim((string) ($rule['name'] ?? ''));

            if (($rule['type'] ?? null) === AlertRuleBehaviorRuleType::SILENT->value) {
                $rule['triggerState'] = trim((string) ($rule['triggerState'] ?? ''));
                $rule['dependsOnAlertRuleIds'] = array_values($rule['dependsOnAlertRuleIds'] ?? []);
                $rule['dependsOnAlertRules'] = $this->formatAlertRulesForApi($rule['dependsOnAlertRuleIds'], $alertRuleNamesByIds);
                $rule['filters'] = $this->normalizeFilterEntries($rule['filters'] ?? []);
                $rule['startsAt'] = $this->normalizeSilentTimestamp($rule['startsAt'] ?? null);
                $rule['endsAt'] = $this->normalizeSilentTimestamp($rule['endsAt'] ?? null);

                unset($rule['template'], $rule['endpointIds']);

                return $rule;
            }

            $rule['endpointIds'] = array_values($rule['endpointIds'] ?? []);
            $rule['endpoints'] = $this->formatEndpointsForApi($rule['endpointIds'], $endpointNameById);

            if (($rule['type'] ?? null) === AlertRuleBehaviorRuleType::TEMPLATE->value) {
                $rule['template'] = (string) ($rule['template'] ?? '');
                unset($rule['filters']);

                return $rule;
            }

            $rule['filters'] = $this->normalizeFilterEntries($rule['filters'] ?? []);

            return $rule;
        })->values()->all();
    }

    /**
     * @param  list<string>  $endpointIds
     * @return array<string, string>
     */
    protected function endpointNamesByIds(array $endpointIds): array
    {
        if ($endpointIds === []) {
            return [];
        }

        return Endpoint::query()
            ->whereIn('_id', $endpointIds)
            ->get(['_id', 'name'])
            ->mapWithKeys(fn (Endpoint $endpoint) => [
                (string) $endpoint->_id => trim((string) ($endpoint->name ?? '')),
            ])
            ->all();
    }

    /**
     * @param  list<string>  $endpointIds
     * @return array<string, string>
     */
    protected function alertRuleNamesByIds(array $alertRuleIds): array
    {
        if ($alertRuleIds === []) {
            return [];
        }

        return AlertRule::query()
            ->whereIn('_id', $alertRuleIds)
            ->get(['_id', 'name'])
            ->mapWithKeys(fn (AlertRule $alertRule) => [
                (string) $alertRule->_id => trim((string) ($alertRule->name ?? '')),
            ])
            ->all();
    }

    /**
     * @param  list<string>  $endpointIds
     * @param  array<string, string>  $endpointNameById
     * @return list<array{id: string, name: string}>
     */
    protected function formatEndpointsForApi(array $endpointIds, array $endpointNameById): array
    {
        return collect($endpointIds)
            ->map(fn (string $endpointId) => [
                'id' => $endpointId,
                'name' => $endpointNameById[$endpointId] ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $alertRuleIds
     * @param  array<string, string>  $alertRuleNameById
     * @return list<array{id: string, name: string}>
     */
    protected function formatAlertRulesForApi(array $alertRuleIds, array $alertRuleNameById): array
    {
        return collect($alertRuleIds)
            ->map(fn (string $alertRuleId) => [
                'id' => $alertRuleId,
                'name' => $alertRuleNameById[$alertRuleId] ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $notifyAlert
     */
    public function resolveIsSilent(AlertRule $alertRule, ?array $notifyAlert = null, ?int $now = null): bool
    {
        foreach ($this->silentRules($alertRule) as $silentRule) {
            if ($this->silentRuleMatches($alertRule, $silentRule, $notifyAlert, $now)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>|null  $notifyAlert
     */
    public function silentRuleMatches(AlertRule $alertRule, array $rule, ?array $notifyAlert = null, ?int $now = null): bool
    {
        if (! $this->hasAnySilentTriggerConfig($rule)) {
            return false;
        }

        if ($this->hasSilentTimeConfig($rule) && ! $this->isSilentRuleWithinTimeWindow($rule, $now)) {
            return false;
        }

        if ($this->hasSilentDependencyConfig($rule) && ! $this->dependencyTriggerMatches($rule)) {
            return false;
        }

        if ($this->hasSilentFilterConfig($rule)) {
            if ($notifyAlert === null) {
                return false;
            }

            $filterEntries = $this->normalizeFilterEntries($rule['filters'] ?? []);
            $contexts = $this->extractAlertContexts($alertRule, $notifyAlert);

            if (! $this->notificationRuleMatches($alertRule, $filterEntries, $contexts)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function hasAnySilentTriggerConfig(array $rule): bool
    {
        return $this->hasSilentDependencyConfig($rule)
            || $this->hasSilentFilterConfig($rule)
            || $this->hasSilentTimeConfig($rule);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function hasSilentDependencyConfig(array $rule): bool
    {
        $dependsOnAlertRuleIds = array_values(array_unique($rule['dependsOnAlertRuleIds'] ?? []));

        return $dependsOnAlertRuleIds !== []
            && $this->normalizeTriggerState((string) ($rule['triggerState'] ?? '')) !== null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function hasSilentFilterConfig(array $rule): bool
    {
        return $this->normalizeFilterEntries($rule['filters'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function hasSilentTimeConfig(array $rule): bool
    {
        return $this->normalizeSilentTimestamp($rule['startsAt'] ?? null) !== null
            || $this->normalizeSilentTimestamp($rule['endsAt'] ?? null) !== null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public function isSilentRuleWithinTimeWindow(array $rule, ?int $now = null): bool
    {
        if (! $this->hasSilentTimeConfig($rule)) {
            return true;
        }

        $now ??= time();
        $startsAt = $this->normalizeSilentTimestamp($rule['startsAt'] ?? null);
        $endsAt = $this->normalizeSilentTimestamp($rule['endsAt'] ?? null);

        if ($startsAt !== null && $now < $startsAt) {
            return false;
        }

        if ($endsAt !== null && $now > $endsAt) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function dependencyTriggerMatches(array $rule): bool
    {
        $triggerState = $this->normalizeTriggerState((string) ($rule['triggerState'] ?? ''));
        if ($triggerState === null) {
            return false;
        }

        $dependsOnAlertRuleIds = array_values(array_unique($rule['dependsOnAlertRuleIds'] ?? []));
        if ($dependsOnAlertRuleIds === []) {
            return false;
        }

        foreach ($this->findDependentAlertRules($dependsOnAlertRuleIds) as $dependentAlertRule) {
            [$dependentState] = $dependentAlertRule->getStatus();
            if ($dependentState !== $triggerState) {
                return false;
            }
        }

        return true;
    }

    public function normalizeSilentTimestamp(mixed $timestamp): ?int
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        if (! is_numeric($timestamp)) {
            return null;
        }

        return (int) $timestamp;
    }

    /**
     * @param  array<string, mixed>  $ruleData
     * @return array<string, mixed>
     */
    public function buildSilentRulePayload(array $ruleData): array
    {
        $payload = [
            'dependsOnAlertRuleIds' => array_values(array_unique($ruleData['dependsOnAlertRuleIds'] ?? [])),
            'triggerState' => trim((string) ($ruleData['triggerState'] ?? '')),
            'filters' => $this->normalizeFilterEntries($ruleData['filters'] ?? []),
            'startsAt' => $this->normalizeSilentTimestamp($ruleData['startsAt'] ?? null),
            'endsAt' => $this->normalizeSilentTimestamp($ruleData['endsAt'] ?? null),
        ];

        if ($payload['dependsOnAlertRuleIds'] === []) {
            unset($payload['dependsOnAlertRuleIds']);
            $payload['triggerState'] = '';
        }

        if ($payload['filters'] === []) {
            unset($payload['filters']);
        }

        if ($payload['startsAt'] === null) {
            unset($payload['startsAt']);
        }

        if ($payload['endsAt'] === null) {
            unset($payload['endsAt']);
        }

        return $payload;
    }

    /**
     * @return Collection<int, AlertRule>
     */
    protected function findDependentAlertRules(array $dependsOnAlertRuleIds): Collection
    {
        return AlertRule::whereIn('_id', $dependsOnAlertRuleIds)->get();
    }

    private function normalizeTriggerState(string $triggerState): ?string
    {
        $normalized = trim(mb_strtolower($triggerState));

        if ($normalized === AlertRule::RESOlVED) {
            return AlertRule::RESOlVED;
        }

        if ($normalized === AlertRule::CRITICAL) {
            return AlertRule::CRITICAL;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $notifyAlert
     * @return list<array{labels: array<string, mixed>, annotations: array<string, mixed>, instance: string}>
     */
    public function extractAlertContexts(AlertRule $alertRule, array $notifyAlert): array
    {
        return match ($alertRule->type) {
            AlertRuleType::API, AlertRuleType::NOTIFICATION => $this->contextsFromApiPayload($notifyAlert),
            AlertRuleType::PROMETHEUS => $this->contextsFromAlertsArrayPayload($notifyAlert),
            AlertRuleType::GRAFANA, AlertRuleType::PMM => $this->contextsFromAlertsArrayPayload($notifyAlert),
            default => $this->contextsFromAlertsArrayPayload($notifyAlert),
        };
    }

    /**
     * @param  list<array{key: string, value: string}>  $filterEntries
     * @param  list<array{labels: array<string, mixed>, annotations: array<string, mixed>, instance: string}>  $contexts
     */
    public function notificationRuleMatches(AlertRule $alertRule, array $filterEntries, array $contexts): bool
    {
        if ($contexts === [] || $filterEntries === []) {
            return false;
        }

        foreach ($contexts as $context) {
            if ($this->matchesFilters($alertRule, $filterEntries, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{key: string, value: string}>  $filterEntries
     * @param  array{labels: array<string, mixed>, annotations: array<string, mixed>, instance: string}  $context
     */
    public function matchesFilters(AlertRule $alertRule, array $filterEntries, array $context): bool
    {
        if ($filterEntries === []) {
            return false;
        }

        foreach ($filterEntries as $filter) {
            $value = $this->getFilterValue($alertRule, $context, $filter['key']);

            if ($value === null || $value === '' || ! Utilities::CheckPatternsString($filter['value'], $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int|string, mixed>  $filters
     */
    private function isFilterEntryList(array $filters): bool
    {
        if ($filters === [] || ! array_is_list($filters)) {
            return false;
        }

        foreach ($filters as $filter) {
            if (! is_array($filter) || ! array_key_exists('key', $filter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{labels: array<string, mixed>, annotations: array<string, mixed>, instance: string}  $context
     */
    public function getFilterValue(AlertRule $alertRule, array $context, string $key): ?string
    {
        return match ($alertRule->type) {
            AlertRuleType::API, AlertRuleType::NOTIFICATION => $key === 'instance'
                ? ($context['instance'] !== '' ? $context['instance'] : null)
                : null,
            default => $this->labelFilterValue($context, $key),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{labels: array<string, mixed>, annotations: array<string, mixed>, instance: string}>
     */
    private function contextsFromApiPayload(array $payload): array
    {
        $instance = trim((string) ($payload['instance'] ?? ''));

        if ($instance === '') {
            return [];
        }

        return [
            [
                'labels' => [],
                'annotations' => [],
                'instance' => $instance,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{labels: array<string, mixed>, annotations: array<string, mixed>, instance: string}>
     */
    private function contextsFromAlertsArrayPayload(array $payload): array
    {
        $contexts = [];

        foreach ($payload['alerts'] ?? [] as $alert) {
            if (! is_array($alert)) {
                continue;
            }

            $labels = is_array($alert['labels'] ?? null) ? $alert['labels'] : [];
            $annotations = is_array($alert['annotations'] ?? null) ? $alert['annotations'] : [];

            $contexts[] = [
                'labels' => $labels,
                'annotations' => $annotations,
                'instance' => (string) ($labels['instance'] ?? ''),
            ];
        }

        if ($contexts !== []) {
            return $contexts;
        }

        if (! empty($payload['labels']) && is_array($payload['labels'])) {
            $labels = $payload['labels'];
            $annotations = is_array($payload['annotations'] ?? null) ? $payload['annotations'] : [];

            return [
                [
                    'labels' => $labels,
                    'annotations' => $annotations,
                    'instance' => (string) ($labels['instance'] ?? ''),
                ],
            ];
        }

        return [];
    }

    /**
     * @param  array{labels: array<string, mixed>, annotations: array<string, mixed>, instance: string}  $context
     */
    private function labelFilterValue(array $context, string $key): ?string
    {
        $labels = $context['labels'];
        $annotations = $context['annotations'];

        if (array_key_exists($key, $labels) && $labels[$key] !== null && $labels[$key] !== '') {
            return (string) $labels[$key];
        }

        if (array_key_exists($key, $annotations) && $annotations[$key] !== null && $annotations[$key] !== '') {
            return (string) $annotations[$key];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $ruleData
     */
    public function createNotificationRule(AlertRule $alertRule, array $ruleData): array
    {
        $rules = $alertRule->rules ?? [];

        $rule = [
            'id' => (string) Str::uuid(),
            'name' => trim((string) ($ruleData['name'] ?? '')),
            'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
            'filters' => $this->normalizeFilterEntries($ruleData['filters'] ?? []),
            'endpointIds' => array_values(array_unique($ruleData['endpointIds'] ?? [])),
        ];

        $rules[] = $rule;
        $alertRule->rules = $rules;
        $alertRule->save();

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $ruleData
     */
    public function createTemplateRule(AlertRule $alertRule, array $ruleData): array
    {
        $rules = $alertRule->rules ?? [];

        $rule = [
            'id' => (string) Str::uuid(),
            'name' => trim((string) ($ruleData['name'] ?? '')),
            'type' => AlertRuleBehaviorRuleType::TEMPLATE->value,
            'endpointIds' => array_values(array_unique($ruleData['endpointIds'] ?? [])),
            'template' => trim((string) ($ruleData['template'] ?? '')),
        ];

        $rules[] = $rule;
        $alertRule->rules = $rules;
        $alertRule->save();

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $ruleData
     */
    public function createSilentRule(AlertRule $alertRule, array $ruleData): array
    {
        $rules = $alertRule->rules ?? [];

        $rule = array_merge(
            [
                'id' => (string) Str::uuid(),
                'name' => trim((string) ($ruleData['name'] ?? '')),
                'type' => AlertRuleBehaviorRuleType::SILENT->value,
            ],
            $this->buildSilentRulePayload($ruleData),
        );

        $rules[] = $rule;
        $alertRule->rules = $rules;
        $alertRule->save();

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $ruleData
     */
    public function updateTemplateRule(AlertRule $alertRule, string $ruleId, array $ruleData): ?array
    {
        $rules = $alertRule->rules ?? [];
        $updatedRule = null;

        foreach ($rules as $index => $rule) {
            if (($rule['id'] ?? null) !== $ruleId) {
                continue;
            }

            if (($rule['type'] ?? null) !== AlertRuleBehaviorRuleType::TEMPLATE->value) {
                return null;
            }

            $rules[$index] = [
                'id' => $ruleId,
                'name' => trim((string) ($ruleData['name'] ?? $rule['name'] ?? '')),
                'type' => AlertRuleBehaviorRuleType::TEMPLATE->value,
                'endpointIds' => array_values(array_unique($ruleData['endpointIds'] ?? $rule['endpointIds'] ?? [])),
                'template' => trim((string) ($ruleData['template'] ?? $rule['template'] ?? '')),
            ];
            $updatedRule = $rules[$index];
            break;
        }

        if ($updatedRule === null) {
            return null;
        }

        $alertRule->rules = $rules;
        $alertRule->save();

        return $updatedRule;
    }

    /**
     * @param  array<string, mixed>  $ruleData
     */
    public function updateSilentRule(AlertRule $alertRule, string $ruleId, array $ruleData): ?array
    {
        $rules = $alertRule->rules ?? [];
        $updatedRule = null;

        foreach ($rules as $index => $rule) {
            if (($rule['id'] ?? null) !== $ruleId) {
                continue;
            }

            if (($rule['type'] ?? null) !== AlertRuleBehaviorRuleType::SILENT->value) {
                return null;
            }

            $mergedRuleData = array_merge($rule, $ruleData);

            $rules[$index] = array_merge(
                [
                    'id' => $ruleId,
                    'name' => trim((string) ($ruleData['name'] ?? $rule['name'] ?? '')),
                    'type' => AlertRuleBehaviorRuleType::SILENT->value,
                ],
                $this->buildSilentRulePayload($mergedRuleData),
            );

            $updatedRule = $rules[$index];
            break;
        }

        if ($updatedRule === null) {
            return null;
        }

        $alertRule->rules = $rules;
        $alertRule->save();

        return $updatedRule;
    }

    public function findRule(AlertRule $alertRule, string $ruleId): ?array
    {
        foreach ($alertRule->rules ?? [] as $rule) {
            if (($rule['id'] ?? null) === $ruleId) {
                return $rule;
            }
        }

        return null;
    }

    public function updateNotificationRule(AlertRule $alertRule, string $ruleId, array $ruleData): ?array
    {
        $rules = $alertRule->rules ?? [];
        $updatedRule = null;

        foreach ($rules as $index => $rule) {
            if (($rule['id'] ?? null) !== $ruleId) {
                continue;
            }

            if (($rule['type'] ?? null) !== AlertRuleBehaviorRuleType::NOTIFICATION->value) {
                return null;
            }

            $rules[$index] = [
                'id' => $ruleId,
                'name' => trim((string) ($ruleData['name'] ?? $rule['name'] ?? '')),
                'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                'filters' => $this->normalizeFilterEntries($ruleData['filters'] ?? $rule['filters'] ?? []),
                'endpointIds' => array_values(array_unique($ruleData['endpointIds'] ?? $rule['endpointIds'] ?? [])),
            ];
            $updatedRule = $rules[$index];
            break;
        }

        if ($updatedRule === null) {
            return null;
        }

        $alertRule->rules = $rules;
        $alertRule->save();

        return $updatedRule;
    }

    public function deleteRule(AlertRule $alertRule, string $ruleId): bool
    {
        $rules = collect($alertRule->rules ?? []);
        $filtered = $rules->reject(fn (array $rule) => ($rule['id'] ?? null) === $ruleId);

        if ($filtered->count() === $rules->count()) {
            return false;
        }

        $alertRule->rules = $filtered->values()->all();
        $alertRule->save();

        return true;
    }
}
