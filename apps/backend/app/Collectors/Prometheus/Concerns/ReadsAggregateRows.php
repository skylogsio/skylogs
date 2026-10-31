<?php

namespace App\Collectors\Prometheus\Concerns;

use MongoDB\Model\BSONDocument;

trait ReadsAggregateRows
{
    /**
     * @param  object|array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function aggregateId(object|array $row): array
    {
        $id = is_array($row) ? ($row['_id'] ?? null) : ($row->_id ?? null);

        if ($id instanceof BSONDocument) {
            return $id->getArrayCopy();
        }

        if (is_array($id)) {
            return $id;
        }

        return [];
    }

    /**
     * @param  object|array<string, mixed>  $row
     */
    protected function aggregateScalarId(object|array $row): mixed
    {
        return is_array($row) ? ($row['_id'] ?? null) : ($row->_id ?? null);
    }

    /**
     * @param  object|array<string, mixed>  $row
     */
    protected function aggregateCount(object|array $row): float
    {
        $count = is_array($row) ? ($row['count'] ?? 0) : ($row->count ?? 0);

        return (float) $count;
    }
}
