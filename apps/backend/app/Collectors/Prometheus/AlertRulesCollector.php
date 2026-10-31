<?php

namespace App\Collectors\Prometheus;

use App\Collectors\Prometheus\Concerns\ReadsAggregateRows;
use App\Models\AlertRule;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class AlertRulesCollector implements Collector
{
    use ReadsAggregateRows;

    public function register(): void
    {
        Prometheus::addGauge('Alert rules')
            ->name('alert_rules')
            ->helpText('Number of alert rules by type and state')
            ->labels(['type', 'state'])
            ->value(fn (): array => $this->values());
    }

    /**
     * @return list<array{0: float, 1: list<string>}>
     */
    private function values(): array
    {
        $rows = AlertRule::raw(function ($collection) {
            return $collection->aggregate([
                [
                    '$group' => [
                        '_id' => [
                            'type' => ['$ifNull' => ['$type', 'unknown']],
                            'state' => ['$ifNull' => ['$state', 'unknown']],
                        ],
                        'count' => ['$sum' => 1],
                    ],
                ],
            ]);
        });

        $values = [];

        foreach ($rows as $row) {
            $id = $this->aggregateId($row);
            $type = strtolower((string) ($id['type'] ?? 'unknown'));
            $state = strtolower((string) ($id['state'] ?? 'unknown'));

            $values[] = [$this->aggregateCount($row), [$type, $state]];
        }

        return $values;
    }
}
