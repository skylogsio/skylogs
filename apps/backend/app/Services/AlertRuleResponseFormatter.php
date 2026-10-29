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
        'watchUserIds',
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
        'target',
        'skylogsInstanceId',
        'extraField',
        'dataSourceAlertName',
        'queryType',
        'ownerName',
        'countEndpoints',
        'count_endpoints',
    ];

    /**
     * User id => name, loaded once per index page or show document.
     *
     * @var array<string, string>
     */
    private array $acknowledgeNames = [];

    public function __construct(
        protected AlertRuleService $alertRuleService,
        protected EndpointService $endpointService,
        protected AlertRuleBehaviorRuleService $behaviorRuleService,
    ) {}

    /**
     * @param  iterable<int, AlertRule>  $alerts
     */
    public function preloadAcknowledgeUsers(iterable $alerts): void
    {
        $ids = [];

        foreach ($alerts as $alert) {
            if (! empty($alert->acknowledgedBy)) {
                $ids[] = (string) $alert->acknowledgedBy;
            }
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return;
        }

        $this->acknowledgeNames = User::query()
            ->whereIn('_id', $ids)
            ->get()
            ->mapWithKeys(fn (User $user): array => [(string) $user->id => (string) $user->name])
            ->all();
    }

    public function enrichForList(AlertRule $alert, User $user): void
    {
        $accessLevel = $this->alertRuleService->resolveAccessLevel($user, $alert);

        $this->applyCommonFields($alert, $user, $accessLevel);

        if ($accessLevel === AlertRuleAccessLevel::Readonly) {
            $this->stripSensitiveAttributes($alert);
        } else {
            $alert->teamIds = $alert->teamIds ?? [];
            $alert->extraField = $this->formatExtraField($alert->extraField ?? []);
            $alert->countEndpoints = $this->endpointService->countAlertEndpoints($alert);
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
            $alert->countEndpoints = $this->endpointService->countAlertEndpoints($alert);
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

        $isWatched = $alert->isWatched($user);
        $alert->isWatched = $isWatched;
        $alert->is_watched = $isWatched;

        $this->applyAcknowledgement($alert);
    }

    private function applyAcknowledgement(AlertRule $alert): void
    {
        $isAcknowledged = $alert->isAcknowledged();
        $acknowledgedBy = $isAcknowledged ? (string) $alert->acknowledgedBy : null;

        $alert->isAcknowledged = $isAcknowledged;
        $alert->acknowledgedBy = $acknowledgedBy;
        $alert->acknowledgedByName = $acknowledgedBy === null ? null : ($this->acknowledgeNames[$acknowledgedBy] ?? null);
        $alert->acknowledgedAt = $isAcknowledged ? $alert->acknowledgedAt : null;
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
