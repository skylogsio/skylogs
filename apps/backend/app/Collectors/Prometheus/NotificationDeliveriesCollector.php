<?php

namespace App\Collectors\Prometheus;

use App\Collectors\Prometheus\Concerns\ReadsAggregateRows;
use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class NotificationDeliveriesCollector implements Collector
{
    use ReadsAggregateRows;

    public function register(): void
    {
        Prometheus::addGauge('Notification deliveries')
            ->name('notification_deliveries')
            ->helpText('In-flight or failed notification deliveries by status and endpoint type')
            ->labels(['status', 'endpoint_type'])
            ->value(fn (): array => $this->values());
    }

    /**
     * @return list<array{0: float, 1: list<string>}>
     */
    private function values(): array
    {
        $statuses = [
            NotificationDeliveryStatus::PENDING->value,
            NotificationDeliveryStatus::SENDING->value,
            NotificationDeliveryStatus::FAILED->value,
        ];

        $rows = NotificationDelivery::raw(function ($collection) use ($statuses) {
            return $collection->aggregate([
                [
                    '$match' => [
                        'status' => ['$in' => $statuses],
                    ],
                ],
                [
                    '$group' => [
                        '_id' => [
                            'status' => ['$ifNull' => ['$status', 'unknown']],
                            'endpoint_type' => ['$ifNull' => ['$endpointType', 'unknown']],
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
            $endpointType = strtolower((string) ($id['endpoint_type'] ?? 'unknown'));

            $values[] = [$this->aggregateCount($row), [$status, $endpointType]];
        }

        return $values;
    }
}
