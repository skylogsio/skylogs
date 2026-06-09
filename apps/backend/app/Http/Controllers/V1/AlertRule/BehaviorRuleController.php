<?php

namespace App\Http\Controllers\V1\AlertRule;

use App\Enums\AlertRuleBehaviorRuleType;
use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Services\AlertRuleBehaviorRuleService;
use App\Services\AlertRuleService;
use App\Services\EndpointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BehaviorRuleController extends Controller
{
    public function __construct(
        protected AlertRuleService $alertRuleService,
        protected AlertRuleBehaviorRuleService $behaviorRuleService,
        protected EndpointService $endpointService,
    ) {}

    public function Index(string $alertRuleId)
    {
        $alertRule = $this->authorizedAlertRule($alertRuleId);

        return response()->json([
            'rules' => $this->behaviorRuleService->formatRulesForApi($alertRule->rules ?? []),
        ]);
    }

    public function SelectableAlertRules(string $alertRuleId)
    {
        $alertRule = $this->authorizedAlertRule($alertRuleId);

        $alertRules = $this->alertRuleService->selectableAlertRulesForSilentDependency($alertRule);

        return response()->json(
            $this->alertRuleService->formatSelectableAlertRulesForApi($alertRules)
        );
    }

    public function Store(Request $request, string $alertRuleId)
    {
        $alertRule = $this->authorizedAlertRule($alertRuleId, requireAdmin: true);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'type' => [
                'required',
                Rule::in([
                    AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    AlertRuleBehaviorRuleType::TEMPLATE->value,
                    AlertRuleBehaviorRuleType::SILENT->value,
                ]),
            ],
            'filters' => [
                Rule::requiredIf(fn () => $request->input('type') === AlertRuleBehaviorRuleType::NOTIFICATION->value),
                'array',
                'min:1',
            ],
            'filters.*.key' => ['required_with:filters', 'string'],
            'filters.*.value' => ['required_with:filters', 'string'],
            'endpointIds' => [
                Rule::requiredIf(fn () => in_array($request->input('type'), [
                    AlertRuleBehaviorRuleType::NOTIFICATION->value,
                    AlertRuleBehaviorRuleType::TEMPLATE->value,
                ], true)),
                Rule::prohibitedIf(fn () => $request->input('type') === AlertRuleBehaviorRuleType::SILENT->value),
                'array',
                'min:1',
            ],
            'endpointIds.*' => ['required_with:endpointIds', 'string'],
            'dependsOnAlertRuleIds' => [
                Rule::requiredIf(fn () => $request->input('type') === AlertRuleBehaviorRuleType::SILENT->value),
                'array',
                'min:1',
            ],
            'dependsOnAlertRuleIds.*' => ['required_with:dependsOnAlertRuleIds', 'string'],
            'triggerState' => [
                Rule::requiredIf(fn () => $request->input('type') === AlertRuleBehaviorRuleType::SILENT->value),
                'string',
                Rule::in([AlertRule::RESOlVED, AlertRule::CRITICAL]),
            ],
            'template' => [
                Rule::requiredIf(fn () => $request->input('type') === AlertRuleBehaviorRuleType::TEMPLATE->value),
                'string',
                'min:1',
            ],
        ]);

        if (! empty($validated['endpointIds'])) {
            $this->assertSelectableEndpoints($alertRule, $validated['endpointIds']);
        }

        if (! empty($validated['dependsOnAlertRuleIds'])) {
            $this->assertSelectableAlertRules($alertRule, $validated['dependsOnAlertRuleIds']);
        }

        $rule = match ($validated['type']) {
            AlertRuleBehaviorRuleType::TEMPLATE->value => $this->behaviorRuleService->createTemplateRule($alertRule, $validated),
            AlertRuleBehaviorRuleType::SILENT->value => $this->behaviorRuleService->createSilentRule($alertRule, $validated),
            default => $this->behaviorRuleService->createNotificationRule($alertRule, $validated),
        };

        return response()->json([
            'status' => true,
            'rule' => $this->behaviorRuleService->formatRulesForApi([$rule])[0],
        ]);
    }

    public function Update(Request $request, string $alertRuleId, string $ruleId)
    {
        $alertRule = $this->authorizedAlertRule($alertRuleId, requireAdmin: true);

        $existingRule = $this->behaviorRuleService->findRule($alertRule, $ruleId);
        if ($existingRule === null) {
            abort(404);
        }

        $isTemplate = ($existingRule['type'] ?? null) === AlertRuleBehaviorRuleType::TEMPLATE->value;
        $isSilent = ($existingRule['type'] ?? null) === AlertRuleBehaviorRuleType::SILENT->value;

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:1', 'max:255'],
            'filters' => [$isTemplate || $isSilent ? 'prohibited' : 'sometimes', 'array', 'min:1'],
            'filters.*.key' => ['required_with:filters', 'string'],
            'filters.*.value' => ['required_with:filters', 'string'],
            'endpointIds' => [$isSilent ? 'prohibited' : 'sometimes', 'array', 'min:1'],
            'endpointIds.*' => ['required_with:endpointIds', 'string'],
            'template' => [$isTemplate ? 'sometimes' : 'prohibited', 'string', 'min:1'],
            'dependsOnAlertRuleIds' => [$isSilent ? 'sometimes' : 'prohibited', 'array', 'min:1'],
            'dependsOnAlertRuleIds.*' => ['required_with:dependsOnAlertRuleIds', 'string'],
            'triggerState' => [
                $isSilent ? 'sometimes' : 'prohibited',
                'string',
                Rule::in([AlertRule::RESOlVED, AlertRule::CRITICAL]),
            ],
        ]);

        if (! empty($validated['endpointIds'])) {
            $this->assertSelectableEndpoints($alertRule, $validated['endpointIds']);
        }

        if (! empty($validated['dependsOnAlertRuleIds'])) {
            $this->assertSelectableAlertRules($alertRule, $validated['dependsOnAlertRuleIds']);
        }

        $rule = match (true) {
            $isTemplate => $this->behaviorRuleService->updateTemplateRule($alertRule, $ruleId, $validated),
            $isSilent => $this->behaviorRuleService->updateSilentRule($alertRule, $ruleId, $validated),
            default => $this->behaviorRuleService->updateNotificationRule($alertRule, $ruleId, $validated),
        };

        if ($rule === null) {
            abort(404);
        }

        return response()->json([
            'status' => true,
            'rule' => $this->behaviorRuleService->formatRulesForApi([$rule])[0],
        ]);
    }

    public function Delete(string $alertRuleId, string $ruleId)
    {
        $alertRule = $this->authorizedAlertRule($alertRuleId, requireAdmin: true);

        if (! $this->behaviorRuleService->deleteRule($alertRule, $ruleId)) {
            abort(404);
        }

        return response()->json(['status' => true]);
    }

    private function authorizedAlertRule(string $alertRuleId, bool $requireAdmin = false): AlertRule
    {
        $alertRule = AlertRule::where('_id', $alertRuleId)->firstOrFail();
        $user = Auth::user();

        if (! $this->alertRuleService->hasUserAccessAlert($user, $alertRule)) {
            abort(403);
        }

        if ($requireAdmin && ! $this->alertRuleService->hasAdminAccessAlert($user, $alertRule)) {
            abort(403);
        }

        return $alertRule;
    }

    /**
     * @param  list<string>  $endpointIds
     */
    private function assertSelectableEndpoints(AlertRule $alertRule, array $endpointIds): void
    {
        $selectableEndpointIds = $this->endpointService
            ->selectableUserEndpoint(Auth::user(), $alertRule)
            ->pluck('id');

        foreach ($endpointIds as $endpointId) {
            if (! $selectableEndpointIds->contains($endpointId)) {
                abort(403);
            }
        }
    }

    /**
     * @param  list<string>  $dependsOnAlertRuleIds
     */
    private function assertSelectableAlertRules(AlertRule $alertRule, array $dependsOnAlertRuleIds): void
    {
        $selectableAlertRuleIds = collect(
            $this->alertRuleService->formatSelectableAlertRulesForApi(
                $this->alertRuleService->selectableAlertRulesForSilentDependency($alertRule)
            )
        )->pluck('id');

        foreach ($dependsOnAlertRuleIds as $dependsOnAlertRuleId) {
            if (! $selectableAlertRuleIds->contains($dependsOnAlertRuleId)) {
                abort(403);
            }
        }
    }
}
