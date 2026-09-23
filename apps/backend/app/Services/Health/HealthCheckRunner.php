<?php

namespace App\Services\Health;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\HealthCheck;
use App\Services\AlertRuleService;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

final class HealthCheckRunner
{
    public function __construct(
        private AlertRuleService $alertRuleService,
        private HealthTargetRegistry $targets,
        private HealthStateMachine $stateMachine,
    ) {}

    public function run(): void
    {
        $rules = $this->alertRuleService->getAlerts(AlertRuleType::HEALTH);

        if ($rules->isEmpty()) {
            return;
        }

        $checks = $this->checksFor($rules);

        $due = $rules->filter(function (AlertRule $rule) use ($checks): bool {
            $check = $checks->get((string) $rule->_id);
            $interval = (int) ($rule->intervalSeconds ?? 30);

            if ($interval < 1) {
                $interval = 30;
            }

            $lastCheckedAt = (int) ($check->lastCheckedAt ?? 0);

            return $lastCheckedAt + $interval <= now()->getTimestamp();
        })->values();

        if ($due->isEmpty()) {
            return;
        }

        $this->probeChunk($due);
    }

    /**
     * @param  Collection<int, AlertRule>  $rules
     * @return Collection<string, HealthCheck>
     */
    private function checksFor(Collection $rules): Collection
    {
        $checks = HealthCheck::query()
            ->where(function ($query) use ($rules) {
                foreach ($rules as $rule) {
                    $query->orWhere('alertRuleId', $rule->_id);
                }
            })
            ->get()
            ->keyBy(fn (HealthCheck $check): string => (string) $check->alertRuleId);

        return $checks;
    }

    /**
     * @param  Collection<int, AlertRule>  $rules
     */
    private function probeChunk(Collection $rules): void
    {
        /** @var array<string, HealthProbe> $probes */
        $probes = [];
        /** @var array<string, AlertRule> $byId */
        $byId = [];

        foreach ($rules as $rule) {
            $id = (string) $rule->_id;
            $byId[$id] = $rule;

            try {
                $probes[$id] = $this->targets->for($rule->checkType)->probe($rule);
            } catch (HealthTargetUnavailable $exception) {
                $this->stateMachine->apply($rule, ProbeResult::failure($exception->getMessage() ?: 'target missing'));
            } catch (Throwable $exception) {
                $this->stateMachine->apply($rule, ProbeResult::failure($exception->getMessage() ?: 'probe failed'));
            }
        }

        if ($probes === []) {
            return;
        }

        $responses = Http::pool(function (Pool $pool) use ($probes) {
            $requests = [];

            foreach ($probes as $id => $probe) {
                $pending = $pool->as($id)
                    ->connectTimeout(HealthProbe::CONNECT_TIMEOUT_SECONDS)
                    ->timeout($probe->effectiveTimeout())
                    ->withOptions(['verify' => $probe->verifyTls])
                    ->withHeaders($probe->headers);

                if (is_string($probe->bearerToken) && $probe->bearerToken !== '') {
                    $pending = $pending->withToken($probe->bearerToken);
                }

                if (is_string($probe->username) && $probe->username !== '') {
                    $pending = $pending->withBasicAuth($probe->username, (string) $probe->password);
                }

                $options = [];

                if ($probe->json !== null) {
                    $pending = $pending->acceptJson();
                    $options['json'] = $probe->json;
                } elseif ($probe->body !== null) {
                    $options['body'] = $probe->body;
                }

                $requests[] = $pending->send($probe->method, $probe->url, $options);
            }

            return $requests;
        }, count($probes));

        foreach ($probes as $id => $probe) {
            $this->stateMachine->apply($byId[$id], $this->resultFor($probe, $responses[$id] ?? null));
        }
    }

    private function resultFor(HealthProbe $probe, mixed $response): ProbeResult
    {
        if ($response instanceof Response) {
            $error = $probe->errorFor($response);
            $latency = $this->latency($response);

            return $error === null
                ? ProbeResult::success($response->status(), $latency, $probe->url)
                : ProbeResult::failure($error, $probe->url, $response->status(), $latency);
        }

        $message = $response instanceof Throwable ? $response->getMessage() : 'request failed';

        return ProbeResult::failure($message !== '' ? $message : 'request failed', $probe->url);
    }

    private function latency(Response $response): ?int
    {
        $stats = $response->handlerStats();
        $seconds = $stats['total_time'] ?? null;

        if (! is_numeric($seconds)) {
            return null;
        }

        return (int) round(((float) $seconds) * 1000);
    }
}
