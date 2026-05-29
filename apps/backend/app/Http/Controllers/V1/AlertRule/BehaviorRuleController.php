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

    public function Store(Request $request, string $alertRuleId)
    {
        $alertRule = $this->authorizedAlertRule($alertRuleId, requireAdmin: true);

        $validated = $request->validate([
            'type' => ['required', Rule::in([AlertRuleBehaviorRuleType::NOTIFICATION->value])],
            'filters' => ['required', 'array', 'min:1'],
            'filters.*.key' => ['required_with:filters', 'string'],
            'filters.*.value' => ['required_with:filters', 'string'],
            'endpointIds' => ['required', 'array', 'min:1'],
            'endpointIds.*' => ['required', 'string'],
        ]);

        $this->assertSelectableEndpoints($alertRule, $validated['endpointIds']);

        $rule = $this->behaviorRuleService->createNotificationRule($alertRule, $validated);

        return response()->json([
            'status' => true,
            'rule' => $this->behaviorRuleService->formatRulesForApi([$rule])[0],
        ]);
    }

    public function Update(Request $request, string $alertRuleId, string $ruleId)
    {
        $alertRule = $this->authorizedAlertRule($alertRuleId, requireAdmin: true);

        $validated = $request->validate([
            'filters' => ['sometimes', 'array', 'min:1'],
            'filters.*.key' => ['required_with:filters', 'string'],
            'filters.*.value' => ['required_with:filters', 'string'],
            'endpointIds' => ['sometimes', 'array', 'min:1'],
            'endpointIds.*' => ['required', 'string'],
        ]);

        if (! empty($validated['endpointIds'])) {
            $this->assertSelectableEndpoints($alertRule, $validated['endpointIds']);
        }

        $rule = $this->behaviorRuleService->updateNotificationRule($alertRule, $ruleId, $validated);

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
}
