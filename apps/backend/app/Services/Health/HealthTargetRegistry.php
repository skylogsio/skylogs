<?php

namespace App\Services\Health;

use App\Enums\HealthAlertType;
use App\Services\Health\Contracts\HealthTarget;
use App\Services\Health\Targets\DataSourceTarget;
use App\Services\Health\Targets\HttpTarget;

final class HealthTargetRegistry
{
    public function for(HealthAlertType|string|null $type): HealthTarget
    {
        $checkType = $type instanceof HealthAlertType ? $type : HealthAlertType::tryFrom((string) $type);

        if ($checkType === null) {
            throw new HealthTargetUnavailable('target missing');
        }

        return match ($checkType) {
            HealthAlertType::DATASOURCE => app(DataSourceTarget::class),
            HealthAlertType::HTTP => app(HttpTarget::class),
            default => throw new HealthTargetUnavailable('target missing'),
        };
    }
}
