<?php

namespace App\Services\Ha;

use App\Models\AlertRule;

/**
 * The payload half of one replicated slot, parsed back out of the log.
 *
 * The leader writes this shape as a plain array from the projectors; the
 * follower and the reconciler read it through here so that a malformed or
 * partial document from an older node cannot reach the models untyped.
 */
final class AlertStateValue
{
    /**
     * @param  array<string, mixed>  $instance  Identity of the slot, in the type's own terms.
     * @param  array<string, mixed>  $rule  The leader's authoritative alert rule aggregate.
     * @param  array<string, mixed>  $extra  Per type payload the writer needs to rebuild the check document.
     */
    public function __construct(
        public readonly AlertStateKey $key,
        public readonly int $version,
        public readonly string $nodeId,
        public readonly int $timestamp,
        public readonly string $alertRuleId,
        public readonly ?string $alertRuleName,
        public readonly string $type,
        public readonly array $instance,
        public readonly string $state,
        public readonly ?int $firedAt,
        public readonly ?int $resolvedAt,
        public readonly array $rule,
        public readonly array $extra,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(AlertStateKey $key, array $payload): self
    {
        return new self(
            key: $key,
            version: (int) ($payload['version'] ?? 0),
            nodeId: (string) ($payload['nodeId'] ?? ''),
            timestamp: (int) ($payload['timestamp'] ?? time()),
            alertRuleId: (string) ($payload['alertRuleId'] ?? $key->alertRuleId),
            alertRuleName: isset($payload['alertRuleName']) ? (string) $payload['alertRuleName'] : null,
            type: (string) ($payload['type'] ?? $key->type),
            instance: self::arrayField($payload, 'instance'),
            state: (string) ($payload['state'] ?? AlertRule::UNKNOWN),
            firedAt: isset($payload['firedAt']) ? (int) $payload['firedAt'] : null,
            resolvedAt: isset($payload['resolvedAt']) ? (int) $payload['resolvedAt'] : null,
            rule: self::arrayField($payload, 'rule'),
            extra: self::arrayField($payload, 'extra'),
        );
    }

    public function isResolved(): bool
    {
        return $this->state === AlertRule::RESOlVED;
    }

    public function isFiring(): bool
    {
        return in_array($this->state, [AlertRule::CRITICAL, AlertRule::WARNING, AlertRule::TRIGGERED], true);
    }

    /**
     * When the slot last moved, according to the leader.
     */
    public function changedAt(): int
    {
        return $this->resolvedAt ?? $this->firedAt ?? $this->timestamp;
    }

    public function ruleState(): ?string
    {
        return isset($this->rule['state']) ? (string) $this->rule['state'] : null;
    }

    public function fireCount(): int
    {
        return (int) ($this->rule['fireCount'] ?? 0);
    }

    public function notifyAt(): ?int
    {
        return isset($this->rule['notifyAt']) ? (int) $this->rule['notifyAt'] : null;
    }

    public function acknowledgedBy(): ?string
    {
        $acknowledgedBy = $this->rule['acknowledgedBy'] ?? null;

        return $acknowledgedBy === null ? null : (string) $acknowledgedBy;
    }

    public function extra(string $field, mixed $default = null): mixed
    {
        return $this->extra[$field] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function extraArray(string $field): array
    {
        $value = $this->extra[$field] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key->toString(),
            'version' => $this->version,
            'nodeId' => $this->nodeId,
            'timestamp' => $this->timestamp,
            'alertRuleId' => $this->alertRuleId,
            'alertRuleName' => $this->alertRuleName,
            'type' => $this->type,
            'instance' => $this->instance,
            'state' => $this->state,
            'firedAt' => $this->firedAt,
            'resolvedAt' => $this->resolvedAt,
            'rule' => $this->rule,
            'extra' => $this->extra,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function arrayField(array $payload, string $field): array
    {
        $value = $payload[$field] ?? [];

        return is_array($value) ? $value : [];
    }
}
