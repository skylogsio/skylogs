<?php

namespace App\Services\Ha;

use App\Enums\AlertRuleType;
use App\Services\GrafanaService;
use InvalidArgumentException;
use Stringable;

/**
 * The identity of one replicated alert slot: alert:{alertRuleId}:{type}:{instanceId}.
 *
 * The human readable rule name is deliberately absent. Names are user mutable,
 * so a rename would orphan every key already in the replicated log and
 * resurrect stale state; the name travels inside the value instead.
 */
final class AlertStateKey implements Stringable
{
    public const PREFIX = 'alert';

    /**
     * Instance segment for the types that have exactly one check document per
     * alert rule and therefore a single slot.
     */
    public const SINGLE_SLOT = '_';

    public function __construct(
        public readonly string $alertRuleId,
        public readonly string $type,
        public readonly string $instanceId = self::SINGLE_SLOT,
    ) {}

    public static function make(string $alertRuleId, AlertRuleType $type, string $instanceId = self::SINGLE_SLOT): self
    {
        return new self($alertRuleId, self::typeSegment($type), $instanceId);
    }

    public static function parse(string $key): self
    {
        $segments = explode(':', $key, 4);

        if (count($segments) !== 4 || $segments[0] !== self::PREFIX || $segments[1] === '' || $segments[2] === '' || $segments[3] === '') {
            throw new InvalidArgumentException("Malformed alert state key [{$key}].");
        }

        return new self($segments[1], $segments[2], $segments[3]);
    }

    /**
     * Prefix shared by every key of one alert rule, used to sweep the rule's
     * slots when the rule itself is deleted.
     */
    public static function prefixFor(string $alertRuleId): string
    {
        return self::PREFIX.':'.$alertRuleId.':';
    }

    /**
     * Grafana and PMM keep distinct segments so that identical fingerprints
     * coming from the two sources cannot collide.
     */
    public static function typeSegment(AlertRuleType $type): string
    {
        return match ($type) {
            AlertRuleType::VICTORIA_LOGS => 'victoriaLogs',
            default => $type->value,
        };
    }

    /**
     * Prometheus alerts are identified by their label set, which is what the
     * evaluator itself compares when matching stored against fetched alerts.
     *
     * @param  array<string, mixed>  $labels
     */
    public static function prometheusInstanceId(array $labels): string
    {
        ksort($labels);

        return sha1((string) json_encode($labels));
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    public static function grafanaInstanceId(array $alert): string
    {
        return GrafanaService::grafanaAlertInstanceKey($alert);
    }

    public static function zabbixInstanceId(int|string $eventId): string
    {
        return (string) $eventId;
    }

    /**
     * Client supplied, so it is hashed to keep keys opaque and safe; the raw
     * instance travels in the value.
     */
    public static function apiInstanceId(?string $instance): string
    {
        return sha1((string) $instance);
    }

    public function toString(): string
    {
        return implode(':', [self::PREFIX, $this->alertRuleId, $this->type, $this->instanceId]);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
