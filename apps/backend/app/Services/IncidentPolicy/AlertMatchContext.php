<?php

namespace App\Services\IncidentPolicy;

use App\Enums\DataSourceType;
use App\Models\AlertRule;

/**
 * Facts about a firing alert used to find matching incident policies.
 */
class AlertMatchContext
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>  $serviceIds
     */
    public function __construct(
        public readonly ?string $alertRuleId = null,
        public readonly array $tags = [],
        public readonly array $serviceIds = [],
        public readonly ?string $dataSourceType = null,
        public readonly ?string $alertName = null,
        public readonly ?string $alertState = null,
    ) {}

    /**
     * @param  list<string>  $serviceIds
     */
    public static function fromAlertRule(AlertRule $alertRule, array $serviceIds = []): self
    {
        $type = $alertRule->type?->value;

        return new self(
            alertRuleId: (string) $alertRule->id,
            tags: self::stringList($alertRule->tags ?? []),
            serviceIds: self::stringList($serviceIds),
            dataSourceType: $type === null ? null : DataSourceType::tryFrom($type)?->value,
            alertName: $alertRule->name,
            alertState: $alertRule->state === null ? null : strtolower((string) $alertRule->state),
        );
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    public static function stringList(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('strval', $values), fn (string $value): bool => $value !== '')));
    }
}
