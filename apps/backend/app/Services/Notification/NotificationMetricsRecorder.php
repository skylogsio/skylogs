<?php

namespace App\Services\Notification;

use App\Enums\EndpointType;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Counts profile attempts and chain runs for one application node.
 *
 * Workers increment the hashes while sending. The metrics scrape reads them
 * back out as counter values. A Redis failure is reported and ignored so a
 * delivery is never lost because monitoring is down.
 */
class NotificationMetricsRecorder
{
    public function recordAttempt(
        EndpointType $type,
        ?string $profileId,
        ?int $priority,
        string $provider,
        string $outcome,
        float $durationSeconds,
    ): void {
        try {
            $redis = $this->redis();
            $redis->hincrby(
                $this->key('attempts'),
                $this->field([
                    'endpoint_type' => $type->value,
                    'provider' => $this->label($provider),
                    'priority' => $this->priorityLabel($priority),
                    'profile_id' => $this->label($profileId),
                    'outcome' => $outcome,
                ]),
                1,
            );
            $durationField = $this->field([
                'endpoint_type' => $type->value,
                'provider' => $this->label($provider),
                'priority' => $this->priorityLabel($priority),
                'profile_id' => $this->label($profileId),
            ]);
            $redis->hincrbyfloat($this->key('duration-sum'), $durationField, $durationSeconds);
            $redis->hincrby($this->key('duration-count'), $durationField, 1);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function recordChainRun(EndpointType $type, string $outcome): void
    {
        try {
            $this->redis()->hincrby(
                $this->key('chain-runs'),
                $this->field([
                    'endpoint_type' => $type->value,
                    'outcome' => $outcome,
                ]),
                1,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return list<array{0: float, 1: list<string>}>
     */
    public function attemptSamples(): array
    {
        return $this->samples('attempts', ['endpoint_type', 'provider', 'priority', 'profile_id', 'outcome']);
    }

    /**
     * @return list<array{0: float, 1: list<string>}>
     */
    public function durationSumSamples(): array
    {
        return $this->samples('duration-sum', ['endpoint_type', 'provider', 'priority', 'profile_id']);
    }

    /**
     * @return list<array{0: float, 1: list<string>}>
     */
    public function durationCountSamples(): array
    {
        return $this->samples('duration-count', ['endpoint_type', 'provider', 'priority', 'profile_id']);
    }

    /**
     * @return list<array{0: float, 1: list<string>}>
     */
    public function chainRunSamples(): array
    {
        return $this->samples('chain-runs', ['endpoint_type', 'outcome']);
    }

    public function forget(): void
    {
        try {
            $this->redis()->del(
                $this->key('attempts'),
                $this->key('duration-sum'),
                $this->key('duration-count'),
                $this->key('chain-runs'),
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  list<string>  $labelNames
     * @return list<array{0: float, 1: list<string>}>
     */
    private function samples(string $name, array $labelNames): array
    {
        try {
            $rows = $this->redis()->hgetall($this->key($name));
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        if (! is_array($rows)) {
            return [];
        }

        $samples = [];

        foreach ($rows as $field => $value) {
            $labels = json_decode((string) $field, true);

            if (! is_array($labels)) {
                continue;
            }

            $samples[] = [(float) $value, $this->labelValues($labelNames, $labels)];
        }

        return $samples;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function field(array $labels): string
    {
        ksort($labels);

        return json_encode($labels, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, mixed>  $labels
     * @return list<string>
     */
    private function labelValues(array $names, array $labels): array
    {
        $values = [$this->nodeId()];

        foreach ($names as $name) {
            $values[] = (string) ($labels[$name] ?? 'none');
        }

        return $values;
    }

    private function priorityLabel(?int $priority): string
    {
        return $priority === null ? 'none' : (string) $priority;
    }

    private function label(?string $value): string
    {
        return $value === null || $value === '' ? 'none' : $value;
    }

    private function key(string $name): string
    {
        return 'notification-metrics:'.$this->nodeId().':'.$name;
    }

    private function nodeId(): string
    {
        $nodeId = (string) config('ha.node_id');

        return $nodeId === '' ? 'none' : $nodeId;
    }

    private function redis(): Connection
    {
        return Redis::connection((string) config('notification.metrics_redis_connection', 'default'));
    }
}
