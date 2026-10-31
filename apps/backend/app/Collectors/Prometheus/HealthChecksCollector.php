<?php

namespace App\Collectors\Prometheus;

use App\Collectors\Prometheus\Concerns\ReadsAggregateRows;
use App\Models\HealthCheck;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class HealthChecksCollector implements Collector
{
    use ReadsAggregateRows;

    public function register(): void
    {
        Prometheus::addGauge('Health checks')
            ->name('health_checks')
            ->helpText('Number of health checks by state')
            ->labels(['state'])
            ->value(fn (): array => $this->values());
    }

    /**
     * @return list<array{0: float, 1: list<string>}>
     */
    private function values(): array
    {
        $rows = HealthCheck::raw(function ($collection) {
            return $collection->aggregate([
                [
                    '$group' => [
                        '_id' => ['$ifNull' => ['$state', 'unknown']],
                        'count' => ['$sum' => 1],
                    ],
                ],
            ]);
        });

        $values = [];

        foreach ($rows as $row) {
            $values[] = [
                $this->aggregateCount($row),
                [$this->labelForState($this->aggregateScalarId($row))],
            ];
        }

        return $values;
    }

    private function labelForState(mixed $state): string
    {
        return match ((int) $state) {
            HealthCheck::UP => 'up',
            HealthCheck::DOWN => 'down',
            default => 'unknown',
        };
    }
}
