<?php

namespace App\Observers\Ha;

use App\Services\Ha\HaConfigCatalog;
use App\Services\Ha\HaConfigVersionStore;
use App\Services\Ha\HaReplicationContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Moves the configuration version whenever replicated configuration changes.
 *
 * Registered on every model in HaConfigCatalog, so a new writer anywhere in the
 * application cannot forget to invalidate a follower's snapshot.
 */
class HaConfigObserver
{
    public function __construct(private readonly HaConfigVersionStore $versions) {}

    public function created(Model $model): void
    {
        if (! $this->shouldTrack()) {
            return;
        }

        $this->versions->bump();
    }

    public function updated(Model $model): void
    {
        if (! $this->carriesReplicatedChange($model)) {
            return;
        }

        $this->versions->bump();
    }

    public function deleted(Model $model): void
    {
        if (! $this->shouldTrack()) {
            return;
        }

        $this->versions->bump();
    }

    /**
     * Publish a write that never fires updated.
     *
     * push and pull store the array and then mark it unchanged, and a query
     * update never loads the other rows it clears. Both skip this observer.
     * A null field means the whole document changed. runningAlertIds moves on
     * every firing alert, and the Raft-owned fields move on every state
     * transition; neither is a configuration change.
     */
    public function touch(Model $model, ?string $field = null): void
    {
        if (! $this->shouldTrack() || HaConfigCatalog::aliasFor($model) === null) {
            return;
        }

        if ($field !== null && $this->ignoresDirectField($field)) {
            return;
        }

        $this->versions->bump();
    }

    /**
     * A save that moved nothing but Raft's own fields is not a configuration
     * change. Without this the leader would bump the version on every alert
     * state transition and hand every follower a full snapshot every thirty
     * seconds, which is the cost the counter exists to avoid.
     */
    private function carriesReplicatedChange(Model $model): bool
    {
        if (! $this->shouldTrack()) {
            return false;
        }

        $alias = HaConfigCatalog::aliasFor($model);
        $excluded = $alias === null ? [] : HaConfigCatalog::definition($alias)['excluded'];

        return array_diff(
            array_keys($model->getChanges()),
            [...$excluded, ...HaConfigCatalog::IDENTITY_FIELDS],
        ) !== [];
    }

    private function ignoresDirectField(string $field): bool
    {
        return in_array($field, [
            ...HaConfigCatalog::RAFT_OWNED_ALERT_RULE_FIELDS,
            'runningAlertIds',
        ], true);
    }

    /**
     * A follower applying a snapshot must not bump its own counter: the version
     * it holds is the leader's, and moving it would make the follower skip the
     * next real change.
     */
    private function shouldTrack(): bool
    {
        return (bool) config('ha.enabled') && ! HaReplicationContext::isApplying();
    }
}
