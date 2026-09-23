<?php

namespace App\Services\Health\Targets;

use App\Models\AlertRule;
use App\Models\DataSource\DataSource;
use App\Services\Health\Contracts\HealthTarget;
use App\Services\Health\DataSourceProbes\DataSourceProbeFactory;
use App\Services\Health\HealthProbe;
use App\Services\Health\HealthTargetUnavailable;
use Closure;

final class DataSourceTarget implements HealthTarget
{
    public function __construct(private DataSourceProbeFactory $probes) {}

    public function rules(): array
    {
        return [
            'dataSourceId' => ['required', 'string', $this->exists(...)],
        ];
    }

    public function attributes(array $validated): array
    {
        return [
            'dataSourceId' => $validated['dataSourceId'],
        ];
    }

    public function probe(AlertRule $rule): HealthProbe
    {
        $dataSource = $this->find($rule->target['dataSourceId'] ?? null);

        if ($dataSource === null || ! is_string($dataSource->url) || $dataSource->url === '') {
            throw new HealthTargetUnavailable('target missing');
        }

        return $this->probes->for($dataSource);
    }

    private function exists(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->find($value) === null) {
            $fail('The selected data source does not exist.');
        }
    }

    private function find(mixed $id): ?DataSource
    {
        if (! is_string($id) || $id === '') {
            return null;
        }

        return DataSource::query()->find($id);
    }
}
