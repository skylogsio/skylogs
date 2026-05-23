<?php

namespace App\Services;

use App\Enums\AlertRuleBehaviorRuleType;
use App\Enums\AlertRuleType;
use App\Helpers\Utilities;
use App\Models\AlertRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AlertRuleBehaviorRuleService
{
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
            $filters = $this->normalizeFilters($rule['filters'] ?? []);
            if ($filters === []) {
                continue;
            }

            if ($this->anyContextMatches($alertRule, $filters, $contexts)) {
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
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function normalizeFilters(array $filters): array
    {
        if ($filters === []) {
            return [];
        }

        if (array_is_list($filters)) {
            $normalized = [];
            foreach ($filters as $filter) {
                if (! is_array($filter)) {
                    continue;
                }

                $key = trim((string) ($filter['key'] ?? ''));
                $value = trim((string) ($filter['value'] ?? ''));

                if ($key !== '' && $value !== '') {
                    $normalized[$key] = $value;
                }
            }

            return $normalized;
        }

        $normalized = [];
        foreach ($filters as $key => $value) {
            $key = trim((string) $key);
            $value = trim((string) $value);

            if ($key !== '' && $value !== '') {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<int, array<string, mixed>>
     */
    public function formatRulesForApi(array $rules): array
    {
        return collect($rules)->map(function (array $rule) {
            $filters = [];
            foreach ($this->normalizeFilters($rule['filters'] ?? []) as $key => $value) {
                $filters[] = [
                    'key' => $key,
                    'value' => $value,
                ];
            }

            $rule['filters'] = $filters;
            $rule['endpointIds'] = array_values($rule['endpointIds'] ?? []);

            return $rule;
        })->values()->all();
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
     * @param  array<string, string>  $filters
     * @param  list<array{labels: array<string, mixed>, annotations: array<string, mixed>, instance: string}>  $contexts
     */
    public function anyContextMatches(AlertRule $alertRule, array $filters, array $contexts): bool
    {
        if ($contexts === []) {
            return false;
        }

        foreach ($contexts as $context) {
            if ($this->matchesFilters($alertRule, $filters, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $filters
     * @param  array{labels: array<string, mixed>, annotations: array<string, mixed>, instance: string}  $context
     */
    public function matchesFilters(AlertRule $alertRule, array $filters, array $context): bool
    {
        foreach ($filters as $key => $pattern) {
            $value = $this->getFilterValue($alertRule, $context, $key);

            if ($value === null || $value === '' || ! Utilities::CheckPatternsString($pattern, $value)) {
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
            AlertRuleType::API, AlertRuleType::NOTIFICATION => $context['instance'] !== ''
                ? $context['instance']
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
            'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
            'filters' => $this->normalizeFilters($ruleData['filters'] ?? []),
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
                'type' => AlertRuleBehaviorRuleType::NOTIFICATION->value,
                'filters' => $this->normalizeFilters($ruleData['filters'] ?? $rule['filters'] ?? []),
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
