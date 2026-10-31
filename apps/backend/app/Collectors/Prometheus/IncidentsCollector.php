<?php

namespace App\Collectors\Prometheus;

use App\Collectors\Prometheus\Concerns\ReadsAggregateRows;
use App\Models\Incident;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class IncidentsCollector implements Collector
{
    use ReadsAggregateRows;

    public function register(): void
    {
        Prometheus::addGauge('Incidents')
            ->name('incidents')
            ->helpText('Number of incidents by status and severity')
            ->labels(['status', 'severity'])
            ->value(fn (): array => $this->values());
    }

    /**
     * @return list<array{0: float, 1: list<string>}>
     */
    private function values(): array
    {
        $rows = Incident::raw(function ($collection) {
            return $collection->aggregate([
                [
                    '$group' => [
                        '_id' => [
                            'status' => ['$ifNull' => ['$status', 'unknown']],
                            'severity' => ['$ifNull' => ['$severity', 'unknown']],
                        ],
                        'count' => ['$sum' => 1],
                    ],
                ],
            ]);
        });

        $values = [];

        foreach ($rows as $row) {
            $id = $this->aggregateId($row);
            $status = strtolower((string) ($id['status'] ?? 'unknown'));
            $severity = strtoupper((string) ($id['severity'] ?? 'unknown'));

            $values[] = [$this->aggregateCount($row), [$status, $severity]];
        }

        return $values;
    }
}
