<?php

namespace App\Services;

use App\Enums\AlertRuleAccessLevel;
use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\DataSource\DataSource;
use App\Models\User;

class AlertRuleResponseFormatter
{
    /**
     * @var list<string>
     */
    private const READONLY_HIDDEN_ATTRIBUTES = [
        'endpointIds',
        'userIds',
        'teamIds',
        'apiToken',
        'sourceToken',
        'agentToken',
        'webhookToken',
        'rules',
        'queryText',
        'queryObject',
        'dataSourceIds',
        'dataSourceId',
        'dataSourceLabels',
        'userId',
        'silentUserIds',
        'pinUserIds',
        'url',
        'hosts',
        'actions',
        'severities',
        'queryString',
        'dataviewName',
        'dataviewTitle',
        'conditionType',
        'minutes',
        'countDocument',
        'enableAutoResolve',
        'autoResolveMinutes',
        'checkType',
        'skylogsInstanceId',
        'extraField',
        'dataSourceAlertName',
        'queryType',
        'acknowledgedBy',
        'ownerName',
        'countEndpoints',
        'count_endpoints',
    ];

    public function __construct(
        protected AlertRuleService $alertRuleService,
        protected EndpointService $endpointService,
        protected AlertRuleBehaviorRuleService $behaviorRuleService,
    ) {}

    public function enrichForList(AlertRule $alert, User $user): void
    {
        $accessLevel = $this->alertRuleService->resolveAccessLevel($user, $alert);

        $this->applyCommonFields($alert, $user, $accessLevel);

        if ($accessLevel === AlertRuleAccessLevel::Readonly) {
            $this->stripSensitiveAttributes($alert);
        } else {
            $alert->teamIds = $alert->teamIds ?? [];
            $alert->extraField = $this->formatExtraField($alert->extraField ?? []);
            $alert->countEndpoints = $this->endpointService->countUserEndpointAlert($user, $alert);
            $alert->count_endpoints = $alert->countEndpoints;
        }

        $this->stripApiTokensUnlessOwner($alert, $user);
    }

    public function enrichForShow(AlertRule $alert, User $user): void
    {
        $accessLevel = $this->alertRuleService->resolveAccessLevel($user, $alert);

        if ($accessLevel === AlertRuleAccessLevel::Manage && ! empty($alert->dataSourceIds)) {
            $alert->dataSourceLabels = DataSource::whereIn('id', $alert->dataSourceIds)
                ->get()
                ->pluck('name')
                ->toArray();
        }

        $this->applyCommonFields($alert, $user, $accessLevel);

        if ($accessLevel === AlertRuleAccessLevel::Manage) {
            $alert->teamIds = $alert->teamIds ?? [];
            $alert->extraField = $this->formatExtraField($alert->extraField ?? []);
            $alert->ownerName = $alert->user->name;
            $alert->countEndpoints = $this->endpointService->countUserEndpointAlert($user, $alert);
            $alert->count_endpoints = $alert->countEndpoints;
            $alert->rules = $this->behaviorRuleService->formatRulesForApi($alert->rules ?? []);
        } else {
            $this->stripSensitiveAttributes($alert);
        }

        $this->stripApiTokensUnlessOwner($alert, $user);
    }

    private function applyCommonFields(AlertRule $alert, User $user, AlertRuleAccessLevel $accessLevel): void
    {
        $alert->accessLevel = $accessLevel->value;
        $alert->hasActionAccess = $accessLevel === AlertRuleAccessLevel::Manage
            && $this->alertRuleService->hasAdminAccessAlert($user, $alert);
        $alert->isPrivate = (bool) ($alert->isPrivate ?? false);

        [$alertStatus, $alertStatusCount] = $alert->getStatus();
        $alert->statusLabel = $alertStatus;
        $alert->statusCount = $alertStatusCount;
        $alert->status_label = $alertStatus;
        $alert->description = $alert->description ?? '';
        $alert->showAcknowledgeBtn = $accessLevel === AlertRuleAccessLevel::Manage
            ? ($alert->showAcknowledgeBtn ?? false)
            : false;

        $isSilent = $accessLevel === AlertRuleAccessLevel::Manage ? $alert->isSilent() : false;
        $alert->isSilent = $isSilent;
        $alert->is_silent = $isSilent;
        $alert->isSilentByBehavior = $this->behaviorRuleService->resolveIsSilent($alert);
    }

    /**
     * @param  array<string, mixed>|list<array{key: string, value: mixed}>  $extraField
     * @return list<array{key: string, value: mixed}>
     */
    private function formatExtraField(array $extraField): array
    {
        if ($extraField === []) {
            return [];
        }

        $formatted = [];

        foreach ($extraField as $key => $value) {
            if (is_array($value) && array_key_exists('key', $value)) {
                $formatted[] = $value;

                continue;
            }

            $formatted[] = [
                'key' => $key,
                'value' => $value,
            ];
        }

        return $formatted;
    }

    private function stripSensitiveAttributes(AlertRule $alert): void
    {
        foreach (self::READONLY_HIDDEN_ATTRIBUTES as $attribute) {
            unset($alert->{$attribute});
        }
    }

    private function stripApiTokensUnlessOwner(AlertRule $alert, User $user): void
    {
        $type = $alert->type instanceof AlertRuleType ? $alert->type : AlertRuleType::tryFrom((string) $alert->type);

        if (! in_array($type, [AlertRuleType::API, AlertRuleType::NOTIFICATION], true)) {
            return;
        }

        if ($this->alertRuleService->hasAdminAccessAlert($user, $alert)) {
            return;
        }

        unset($alert->apiToken);
    }
}
