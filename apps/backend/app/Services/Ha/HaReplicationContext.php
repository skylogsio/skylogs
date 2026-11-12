<?php

namespace App\Services\Ha;

use Closure;

/**
 * Marks the code path that applies replicated state coming from the leader.
 *
 * Everything a follower writes while applying goes through the same models and
 * observers as a local evaluation would, so without this flag an apply would
 * immediately publish the state straight back into the replicated log.
 */
final class HaReplicationContext
{
    private static int $depth = 0;

    public static function isApplying(): bool
    {
        return self::$depth > 0;
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function apply(Closure $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }
}
