<?php

namespace App\Services\Ha;

use App\Models\AlertRule;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Config\ConfigCall;
use App\Models\Config\ConfigEmail;
use App\Models\Config\ConfigSkylogs;
use App\Models\Config\ConfigSms;
use App\Models\Config\ConfigTelegram;
use App\Models\DataSource\DataSource;
use App\Models\Endpoint;
use App\Models\Incident;
use App\Models\OnCallPlan;
use App\Models\Profile\ProfileAsset;
use App\Models\Profile\ProfileEnvironment;
use App\Models\Profile\ProfileService;
use App\Models\Service;
use App\Models\SilentRule;
use App\Models\SkylogsInstance;
use App\Models\Status;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What the configuration snapshot carries, and what it must keep its hands off.
 *
 * The first is the collection list itself: it is an allowlist, so every check
 * and every history collection is outside the snapshot by construction (checks
 * belong to Raft; timelines and notifies belong to history sync).
 * The second is the per model field list, which keeps the two replication paths
 * from fighting inside the one collection they share, alert_rules.
 */
final class HaConfigCatalog
{
    /**
     * The alert rule fields Raft owns. Letting the snapshot carry these would
     * have config sync overwrite a fire with a thirty second old resolve, and
     * then Raft overwrite it back, indefinitely.
     *
     * @var array<int, string>
     */
    public const RAFT_OWNED_ALERT_RULE_FIELDS = ['state', 'fireCount', 'notifyAt', 'acknowledgedBy'];

    /**
     * Identity and timestamps: a change confined to these is never a
     * configuration change worth a version bump.
     *
     * @var array<int, string>
     */
    public const IDENTITY_FIELDS = ['_id', 'id', 'createdAt', 'updatedAt'];

    /**
     * The applier sets the primary key itself and lets Eloquent stamp
     * updatedAt with the moment of the sync. createdAt is deliberately absent:
     * it is user visible, so a failover should not show every document as
     * created the instant the follower first synced.
     *
     * @var array<int, string>
     */
    public const APPLIER_OWNED_FIELDS = ['_id', 'id', 'updatedAt'];

    /**
     * Keyed by the name the snapshot uses on the wire, so renaming a model
     * class does not break a follower running an older build.
     *
     * @return array<string, array{model: class-string<Model>, excluded: array<int, string>}>
     */
    public static function collections(): array
    {
        return [
            'users' => ['model' => User::class, 'excluded' => []],
            'roles' => ['model' => Role::class, 'excluded' => []],
            'permissions' => ['model' => Permission::class, 'excluded' => []],
            'teams' => ['model' => Team::class, 'excluded' => []],
            'endpoints' => ['model' => Endpoint::class, 'excluded' => []],
            'dataSources' => ['model' => DataSource::class, 'excluded' => []],
            'alertRules' => ['model' => AlertRule::class, 'excluded' => self::RAFT_OWNED_ALERT_RULE_FIELDS],
            'silentRules' => ['model' => SilentRule::class, 'excluded' => []],
            'statuses' => ['model' => Status::class, 'excluded' => []],
            'services' => ['model' => Service::class, 'excluded' => []],
            'skylogsInstances' => ['model' => SkylogsInstance::class, 'excluded' => []],
            'profileAssets' => ['model' => ProfileAsset::class, 'excluded' => []],
            'profileEnvironments' => ['model' => ProfileEnvironment::class, 'excluded' => []],
            'profileServices' => ['model' => ProfileService::class, 'excluded' => []],
            'configSkylogs' => ['model' => ConfigSkylogs::class, 'excluded' => []],
            'configTelegrams' => ['model' => ConfigTelegram::class, 'excluded' => []],
            'configSms' => ['model' => ConfigSms::class, 'excluded' => []],
            'configCalls' => ['model' => ConfigCall::class, 'excluded' => []],
            'configEmails' => ['model' => ConfigEmail::class, 'excluded' => []],
            'incidents' => ['model' => Incident::class, 'excluded' => []],
            'onCallPlans' => ['model' => OnCallPlan::class, 'excluded' => []],
        ];
    }

    /**
     * @return array<int, class-string<Model>>
     */
    public static function models(): array
    {
        return array_values(array_map(
            fn (array $definition): string => $definition['model'],
            self::collections(),
        ));
    }

    /**
     * @return array{model: class-string<Model>, excluded: array<int, string>}|null
     */
    public static function definition(string $alias): ?array
    {
        return self::collections()[$alias] ?? null;
    }

    public static function aliasFor(Model $model): ?string
    {
        foreach (self::collections() as $alias => $definition) {
            if ($model instanceof $definition['model']) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * Everything the applier must not copy onto a follower's document.
     *
     * @return array<int, string>
     */
    public static function protectedFields(string $alias): array
    {
        return [...self::APPLIER_OWNED_FIELDS, ...(self::definition($alias)['excluded'] ?? [])];
    }
}
