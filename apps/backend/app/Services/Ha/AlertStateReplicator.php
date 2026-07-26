<?php

namespace App\Services\Ha;

use App\Enums\AlertRuleType;
use App\Jobs\Ha\PublishAlertStateJob;
use App\Models\AlertRule;
use App\Models\BaseModel;
use App\Services\Ha\Projectors\StateProjector;
use App\Services\Ha\Projectors\StateProjectorFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publishes local alert state changes into the replicated log.
 *
 * Model observers are the choke point rather than explicit calls in the
 * evaluation services: there are a dozen writers of alert state today, and the
 * next one someone adds would otherwise silently stop replicating.
 */
class AlertStateReplicator
{
    /**
     * Fields of the alert rule that belong to the replicated log rather than to
     * the configuration snapshot.
     *
     * @var array<int, string>
     */
    private const RULE_ATTRIBUTES = ['state', 'fireCount', 'notifyAt', 'acknowledgedBy'];

    private const PUBLISH_MEMO_LIMIT = 500;

    /**
     * Fingerprint of the last payload published per key, so the check write and
     * the alert rule write that follows it do not publish the same slot twice.
     *
     * @var array<string, string>
     */
    private array $lastPublished = [];

    public function __construct(
        private readonly HaLeaderService $leader,
        private readonly StateProjectorFactory $projectors,
        private readonly HaStateVersionStore $versions,
    ) {}

    /**
     * Only a leader that is not itself applying replicated state publishes.
     * Checked before projecting so a disabled or follower node never reads a
     * check document it does not need.
     */
    public function shouldReplicate(): bool
    {
        return (bool) config('ha.enabled')
            && ! HaReplicationContext::isApplying()
            && $this->leader->isLeader();
    }

    public function replicateCheck(BaseModel $check, AlertRule $alertRule): void
    {
        if (! $this->shouldReplicate()) {
            return;
        }

        $this->publishAll(fn (StateProjector $projector): array => $projector->projectCheck($check, $alertRule), $alertRule);
    }

    public function replicateCheckDeletion(BaseModel $check, AlertRule $alertRule): void
    {
        if (! $this->shouldReplicate()) {
            return;
        }

        $this->publishAll(fn (StateProjector $projector): array => $projector->projectDeletion($check, $alertRule), $alertRule);
    }

    public function replicateRule(AlertRule $alertRule): void
    {
        if (! $this->shouldReplicate() || ! $this->ruleAggregateChanged($alertRule)) {
            return;
        }

        $this->publishAll(fn (StateProjector $projector): array => $projector->projectRule($alertRule), $alertRule);
    }

    /**
     * A deleted rule takes every slot underneath it with it.
     */
    public function replicateRuleDeletion(AlertRule $alertRule): void
    {
        if (! $this->shouldReplicate()) {
            return;
        }

        $prefix = AlertStateKey::prefixFor((string) $alertRule->getKey());

        try {
            foreach ($this->versions->keysWithPrefix($prefix) as $key) {
                $this->publishTombstone($key);
            }
        } catch (Throwable $exception) {
            $this->reportFailure($exception, ['prefix' => $prefix]);
        }
    }

    /**
     * @param  callable(StateProjector): array<int, AlertStateChange>  $project
     */
    private function publishAll(callable $project, AlertRule $alertRule): void
    {
        try {
            foreach ($project($this->projectors->make($this->ruleType($alertRule))) as $change) {
                if ($change->isTombstone()) {
                    $this->publishTombstone($change->key->toString());

                    continue;
                }

                $this->publishValue($change);
            }
        } catch (Throwable $exception) {
            $this->reportFailure($exception, ['alertRuleId' => (string) $alertRule->getKey()]);
        }
    }

    private function publishValue(AlertStateChange $change): void
    {
        $key = $change->key->toString();
        $fingerprint = sha1((string) json_encode($change->value));

        if (($this->lastPublished[$key] ?? null) === $fingerprint) {
            return;
        }

        $version = $this->versions->next($key, $change->value['state'] ?? null);

        PublishAlertStateJob::dispatch($key, [
            'key' => $key,
            'version' => $version,
            'nodeId' => $this->leader->nodeId(),
            'timestamp' => time(),
            ...$change->value,
        ]);

        $this->remember($key, $fingerprint);
    }

    private function publishTombstone(string $key): void
    {
        if (($this->lastPublished[$key] ?? null) === 'tombstone') {
            return;
        }

        $this->versions->forget($key);

        PublishAlertStateJob::dispatch($key, null);

        $this->remember($key, 'tombstone');
    }

    /**
     * A rule save that did not move the aggregate carries nothing new; the
     * evaluation services save alert rules on every pass.
     */
    private function ruleAggregateChanged(AlertRule $alertRule): bool
    {
        if ($alertRule->wasRecentlyCreated) {
            return ! empty($alertRule->state);
        }

        return $alertRule->wasChanged(self::RULE_ATTRIBUTES);
    }

    private function ruleType(AlertRule $alertRule): AlertRuleType
    {
        return $alertRule->type instanceof AlertRuleType
            ? $alertRule->type
            : AlertRuleType::from((string) $alertRule->type);
    }

    private function remember(string $key, string $fingerprint): void
    {
        if (count($this->lastPublished) >= self::PUBLISH_MEMO_LIMIT) {
            $this->lastPublished = [];
        }

        $this->lastPublished[$key] = $fingerprint;
    }

    /**
     * Replication is never allowed to break evaluation: a failure here means a
     * stale slot that reconciliation will repair.
     *
     * @param  array<string, mixed>  $context
     */
    private function reportFailure(Throwable $exception, array $context): void
    {
        Log::error('Publishing replicated alert state failed.', [
            ...$context,
            'exception' => $exception->getMessage(),
        ]);
    }
}
