<?php

namespace Tests\Support;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\IncidentPolicy;
use App\Models\OnCallPlan;
use App\Models\Profile\ProfileService;
use App\Models\Team;
use App\Models\User;

class IncidentPolicyTestData
{
    public static function createAlertRule(User $user, ?string $name = null): AlertRule
    {
        return AlertRule::create([
            'name' => $name ?? 'test-rule-'.uniqid(),
            'type' => AlertRuleType::API,
            'userId' => $user->id,
        ]);
    }

    public static function createEndpoint(User $user, ?string $name = null): Endpoint
    {
        return Endpoint::create([
            'name' => $name ?? 'test-endpoint-'.uniqid(),
            'type' => Endpoint::TELEGRAM,
            'userId' => $user->id,
            'value' => '123456',
        ]);
    }

    public static function createOnCallPlan(Team $team, ?string $name = null): OnCallPlan
    {
        return OnCallPlan::create([
            'name' => $name ?? 'test-plan-'.uniqid(),
            'teamId' => $team->id,
            'timezone' => 'UTC',
            'layers' => [],
        ]);
    }

    public static function createProfileService(User $owner, ?string $name = null): ProfileService
    {
        return ProfileService::create([
            'name' => $name ?? 'test-service-'.uniqid(),
            'ownerId' => $owner->id,
        ]);
    }

    /**
     * A single-document definition wired to the given fixtures.
     *
     * @param  array<string, string>  $overrides
     */
    public static function policyYaml(string $name, string $teamName, array $overrides = []): string
    {
        $alertRule = $overrides['alertRule'] ?? 'placeholder-rule';
        $channel = $overrides['channel'] ?? null;
        $ackWithinMinutes = $overrides['ackWithinMinutes'] ?? '5';
        $useLayers = $overrides['useLayers'] ?? null;
        $services = $overrides['services'] ?? null;
        $matchServices = $services === null ? '' : <<<YAML

            services: [{$services}]
        YAML;

        $notify = $channel === null ? '' : <<<YAML

              notify:
                channels: [endpoint:{$channel}]
        YAML;

        $escalation = $useLayers === null ? '' : <<<YAML

              escalation:
                useLayers: {$useLayers}
        YAML;

        return <<<YAML
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: {$name}
          description: Imported by the test suite
          teams: [{$teamName}]
        spec:
          match:
            alertRules: [{$alertRule}]
            tags: [payments]{$matchServices}
          grouping:
            key: [serviceId, alertRuleId]
            windowMinutes: 15
          incident:
            autoCreate: true
            defaultSeverity: SEV3
            severityMap:
              critical: SEV1
          rules:
            - severity: SEV1
              ack: { withinMinutes: {$ackWithinMinutes} }
              resolve: { withinMinutes: 60 }
              requireCommander: true{$notify}{$escalation}
              postmortem:
                required: true
                dueDays: 5
              runbooks: [payments-api-5xx-triage]
            - severity: SEV3
              ack: { withinMinutes: 30 }
        YAML;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createPolicy(array $attributes = []): IncidentPolicy
    {
        $match = array_replace([
            'alertRuleIds' => [],
            'tags' => [],
            'serviceIds' => [],
            'dataSourceTypes' => [],
        ], $attributes['match'] ?? []);

        $incident = array_replace([
            'autoCreate' => true,
            'autoResolveOnAlertClear' => false,
            'titleTemplate' => null,
            'defaultSeverity' => 'SEV3',
            'severityMap' => [],
        ], $attributes['incident'] ?? []);

        unset($attributes['match'], $attributes['incident']);

        return IncidentPolicy::create(array_replace([
            'name' => 'policy-'.uniqid(),
            'description' => '',
            'enabled' => true,
            'teamIds' => [],
            'match' => $match,
            'grouping' => ['key' => [], 'windowMinutes' => 15],
            'incident' => $incident,
            'rules' => [
                'SEV3' => ['ackWithinMinutes' => 30],
            ],
            'version' => 1,
        ], $attributes));
    }

    public static function deletePolicy(IncidentPolicy $policy): void
    {
        IncidentPolicy::query()->where('_id', $policy->id)->delete();
    }

    public static function deletePolicyByName(string $name): void
    {
        IncidentPolicy::query()->where('name', $name)->delete();
    }

    public static function deleteAlertRule(AlertRule $alertRule): void
    {
        AlertRule::query()->where('_id', $alertRule->id)->delete();
    }

    public static function deleteEndpoint(Endpoint $endpoint): void
    {
        Endpoint::query()->where('_id', $endpoint->id)->delete();
    }

    public static function deleteOnCallPlan(OnCallPlan $onCallPlan): void
    {
        OnCallPlan::query()->where('_id', $onCallPlan->id)->delete();
    }

    public static function deleteProfileService(ProfileService $profileService): void
    {
        ProfileService::query()->where('_id', $profileService->id)->delete();
    }
}
