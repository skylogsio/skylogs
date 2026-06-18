<?php

namespace App\Mcp\Tools;

use App\Services\AlertRuleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-fired-alerts')]
#[IsReadOnly]
#[Description('Return currently firing alerts from Skylogs. Omit alertRuleId to list all critical alert rules with their fired alerts, or pass alertRuleId to inspect one rule.')]
class GetFiredAlertsTool extends Tool
{
    public function __construct(protected AlertRuleService $alertRuleService) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'alertRuleId' => 'nullable|string|regex:/^[0-9a-fA-F]{24}$/',
        ]);

        if (empty($validated['alertRuleId'])) {
            return Response::json([
                'firedAlertRules' => $this->normalizeCriticalRules(
                    $this->alertRuleService->firedAlertsForCriticalRules()
                ),
            ]);
        }

        $alertRuleId = $validated['alertRuleId'];

        return Response::json([
            'alertRuleId' => $alertRuleId,
            'firedAlerts' => $this->normalizeFiredAlerts(
                $this->alertRuleService->firedAlerts($alertRuleId)
            ),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'alertRuleId' => $schema->string()
                ->description('Optional MongoDB alert rule ID. When omitted, returns all critical alert rules and their fired alerts.'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $criticalRules
     * @return list<array<string, mixed>>
     */
    private function normalizeCriticalRules(array $criticalRules): array
    {
        return array_map(function (array $rule): array {
            $rule['firedAlerts'] = $this->normalizeFiredAlerts($rule['firedAlerts'] ?? []);

            return $rule;
        }, $criticalRules);
    }

    /**
     * @return list<array<string, mixed>|mixed>
     */
    private function normalizeFiredAlerts(mixed $firedAlerts): array
    {
        if ($firedAlerts instanceof Collection) {
            return $firedAlerts
                ->map(fn (mixed $alert): mixed => $this->normalizeAlert($alert))
                ->values()
                ->all();
        }

        if (is_array($firedAlerts)) {
            if (array_is_list($firedAlerts)) {
                return array_map(fn (mixed $alert): mixed => $this->normalizeAlert($alert), $firedAlerts);
            }

            return $firedAlerts;
        }

        return [];
    }

    private function normalizeAlert(mixed $alert): mixed
    {
        if (is_array($alert)) {
            return $alert;
        }

        if (is_object($alert) && method_exists($alert, 'toArray')) {
            return $alert->toArray();
        }

        return $alert;
    }
}
