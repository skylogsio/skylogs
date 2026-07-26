<?php

namespace App\Services\Ha;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Services\Ha\Writers\StateWriterFactory;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Applies one replicated slot to this node.
 *
 * Everything here runs inside HaReplicationContext so the models this touches
 * do not publish the state straight back into the log, and so that no apply can
 * notify: the leader already paged whoever needed paging.
 */
class HaStateApplier
{
    public const REASON_STALE = 'stale';

    public const REASON_MALFORMED = 'malformed';

    public const REASON_UNKNOWN_ALERT_RULE = 'unknownAlertRule';

    public const REASON_FAILED = 'failed';

    public function __construct(
        private readonly StateWriterFactory $writers,
        private readonly HaStateVersionStore $versions,
    ) {}

    /**
     * @param  array<string, mixed>|null  $payload  A null payload is a tombstone.
     * @return array{applied: bool, reason?: string}
     */
    public function apply(string $rawKey, ?array $payload): array
    {
        try {
            $key = AlertStateKey::parse($rawKey);
        } catch (InvalidArgumentException) {
            return $this->rejected(self::REASON_MALFORMED);
        }

        try {
            return $payload === null
                ? $this->applyTombstone($key)
                : $this->applyValue(AlertStateValue::fromArray($key, $payload));
        } catch (Throwable $exception) {
            Log::error('Applying replicated alert state failed.', [
                'key' => $rawKey,
                'exception' => $exception->getMessage(),
            ]);

            return $this->rejected(self::REASON_FAILED);
        }
    }

    /**
     * @return array{applied: bool, reason?: string}
     */
    private function applyValue(AlertStateValue $value): array
    {
        if (! $this->isNewer($value)) {
            return $this->rejected(self::REASON_STALE);
        }

        $alertRule = AlertRule::find($value->alertRuleId);

        if (! $alertRule) {
            return $this->rejected(self::REASON_UNKNOWN_ALERT_RULE);
        }

        $writer = $this->writers->make($this->ruleType($alertRule));

        HaReplicationContext::apply(function () use ($writer, $alertRule, $value): void {
            $previousState = $writer->localState($alertRule, $value);

            $writer->write($alertRule, $value);
            $this->writeRuleAggregate($alertRule, $value);

            if ($previousState !== $value->state) {
                $writer->writeHistory($alertRule, $value);
            }
        });

        $this->versions->record($value->key->toString(), $value->version, $value->nodeId, $value->state);

        return ['applied' => true];
    }

    /**
     * @return array{applied: bool, reason?: string}
     */
    private function applyTombstone(AlertStateKey $key): array
    {
        $alertRule = AlertRule::find($key->alertRuleId);

        if ($alertRule) {
            $writer = $this->writers->make($this->ruleType($alertRule));

            HaReplicationContext::apply(fn () => $writer->remove($alertRule, $key));
        }

        $this->versions->forget($key->toString());

        return ['applied' => true];
    }

    /**
     * A version that is not strictly newer is a replay or a duplicate delivery,
     * and dropping it is what makes those harmless. Two nodes that reached the
     * same version during a partition are separated by their node id, so every
     * follower resolves the tie the same way.
     */
    private function isNewer(AlertStateValue $value): bool
    {
        $local = $this->versions->entry($value->key->toString());

        if ($value->version !== $local['version']) {
            return $value->version > $local['version'];
        }

        return strcmp($value->nodeId, $local['nodeId']) > 0;
    }

    /**
     * The leader's aggregate wins outright, so that a follower converges even
     * when one instance delivery was lost.
     */
    private function writeRuleAggregate(AlertRule $alertRule, AlertStateValue $value): void
    {
        $state = $value->ruleState();

        if ($state === null) {
            return;
        }

        $alertRule->state = $state;
        $alertRule->fireCount = $value->fireCount();
        $alertRule->notifyAt = $value->notifyAt();
        $alertRule->acknowledgedBy = $value->acknowledgedBy();
        $alertRule->save();
    }

    private function ruleType(AlertRule $alertRule): AlertRuleType
    {
        return $alertRule->type instanceof AlertRuleType
            ? $alertRule->type
            : AlertRuleType::from((string) $alertRule->type);
    }

    /**
     * @return array{applied: bool, reason: string}
     */
    private function rejected(string $reason): array
    {
        return ['applied' => false, 'reason' => $reason];
    }
}
