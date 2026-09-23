<?php

namespace App\Services\Health;

use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\HealthCheck;
use App\Models\HealthHistory;
use App\Services\SendNotifyService;

final class HealthStateMachine
{
    public function apply(AlertRule $rule, ProbeResult $result): void
    {
        $threshold = max(1, (int) ($rule->threshold ?? 3));
        $check = HealthCheck::query()->firstOrCreate(
            ['alertRuleId' => $rule->_id],
            [
                'state' => HealthCheck::UP,
                'counter' => 0,
                'successCounter' => 0,
                'checkType' => $rule->checkType,
            ],
        );

        $check->lastCheckedAt = now()->getTimestamp();
        $check->lastError = $result->ok ? null : $result->error;
        $check->lastStatusCode = $result->statusCode;
        $check->lastLatencyMs = $result->latencyMs;
        $check->url = $result->url;
        $check->checkType = $rule->checkType;

        if ($result->ok) {
            $check->counter = 0;
            $check->successCounter = min($threshold, (int) $check->successCounter + 1);

            if ((int) $check->state === HealthCheck::DOWN && $check->successCounter >= $threshold) {
                $this->transition($rule, $check, HealthCheck::UP);

                return;
            }

            $check->save();

            return;
        }

        $check->successCounter = 0;
        $check->counter = min($threshold, (int) $check->counter + 1);

        if ((int) $check->state !== HealthCheck::DOWN && $check->counter >= $threshold) {
            $this->transition($rule, $check, HealthCheck::DOWN);

            return;
        }

        $check->save();
    }

    private function transition(AlertRule $rule, HealthCheck $check, int $state): void
    {
        $check->state = $state;
        $check->notifyAt = now()->getTimestamp();
        $check->save();

        $rule->state = $state === HealthCheck::DOWN ? AlertRule::CRITICAL : AlertRule::RESOlVED;
        $rule->save();

        if ($state === HealthCheck::UP) {
            $rule->removeAcknowledge();
        }

        HealthHistory::query()->create([
            'alertRuleId' => $rule->_id,
            'alertRuleName' => $rule->name,
            'checkType' => $rule->checkType,
            'url' => $check->url,
            'threshold' => $rule->threshold,
            'state' => $state,
            'counter' => $state === HealthCheck::DOWN ? $check->counter : 0,
        ]);

        app(SendNotifyService::class)->createNotify(SendNotifyJob::HEALTH_CHECK, $check, $rule->_id);
    }
}
