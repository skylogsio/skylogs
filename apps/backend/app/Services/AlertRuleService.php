<?php

namespace App\Services;

use App\Enums\AlertRuleAccessLevel;
use App\Enums\AlertRuleType;
use App\Enums\HealthAlertType;
use App\Helpers\Constants;
use App\Helpers\Utilities;
use App\Jobs\SendNotifyJob;
use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use App\Models\Config\ConfigSkylogs;
use App\Models\DataSource\DataSource;
use App\Models\ElasticCheck;
use App\Models\ElasticHistory;
use App\Models\Endpoint;
use App\Models\GrafanaCheck;
use App\Models\GrafanaWebhookAlert;
use App\Models\HealthCheck;
use App\Models\HealthHistory;
use App\Models\MetabaseWebhookAlert;
use App\Models\PrometheusCheck;
use App\Models\PrometheusHistory;
use App\Models\SentryWebhookAlert;
use App\Models\SkylogsInstance;
use App\Models\User;
use App\Models\VictoriaLogsCheck;
use App\Models\VictoriaLogsHistory;
use App\Models\ZabbixCheck;
use App\Models\ZabbixWebhookAlert;
use App\Services\AlertStatus\AlertStatusEventSourceFactory;
use App\Services\AlertStatus\AlertStatusTimelineBuilder;
use Cache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;

class AlertRuleService
{
    public function __construct(protected TeamService $teamService) {}

    public function firedAlerts(string $alertRuleId)
    {
        $alertRule = AlertRule::where('id', $alertRuleId)->firstOrFail();
        switch ($alertRule->type) {
            case AlertRuleType::API:
                $firedInstances = AlertInstance::where('alertRuleId', $alertRuleId)
                    ->where('state', AlertInstance::FIRE)
                    ->get();

                return $firedInstances;

            case AlertRuleType::PROMETHEUS:
                $check = PrometheusCheck::where('alertRuleId', $alertRuleId)->first();

                return $check ? ($check->alerts ?? []) : [];

            case AlertRuleType::ZABBIX:
                $check = ZabbixCheck::where('alertRuleId', $alertRuleId)->first();
                if ($check && ! empty($check->fireEvents)) {
                    return ZabbixWebhookAlert::whereIn('event_id', $check->fireEvents)
                        ->where('alertRuleId', $alertRuleId)
                        ->get();
                }
                break;

            case AlertRuleType::GRAFANA:
            case AlertRuleType::PMM:
                $check = GrafanaCheck::where('alertRuleId', $alertRuleId)->first();
                if ($check) {
                    return $check->alerts ?? [];
                }
                break;
            case AlertRuleType::ELASTIC:
                $check = ElasticCheck::where('alertRuleId', $alertRuleId)->first();
                if ($check) {
                    return $check->toArray() ?? [];
                }
                break;
            case AlertRuleType::VICTORIA_LOGS:
                $check = VictoriaLogsCheck::where('alertRuleId', $alertRuleId)->first();
                if ($check) {
                    return $check->toArray() ?? [];
                }
                break;

        }

        return [];

    }

    /**
     * @return Collection<int, AlertRule>
     */
    public function all(): Collection
    {
        return AlertRule::query()
            ->orderBy('name')
            ->get(['name', 'type']);
    }

    public function getAlertRules($request)
    {
        $match = [];
        $this->getMatchFilterArray($request, $match);

        $pipeline = [];
        if (! empty($match)) {
            $pipeline[] = ['$match' => $match];
        }

        $data = AlertRule::raw(function ($collection) use ($pipeline) {
            return $collection->aggregate($pipeline);
        });

        return $data;
    }

    /**
     * Alert rule types whose status can be resolved or critical (used by silent behavior rules).
     *
     * @return list<AlertRuleType>
     */
    public function silentDependencySupportedTypes(): array
    {
        return [
            AlertRuleType::API,
            AlertRuleType::PROMETHEUS,
            AlertRuleType::GRAFANA,
            AlertRuleType::PMM,
            AlertRuleType::SENTRY,
            AlertRuleType::METABASE,
            AlertRuleType::ZABBIX,
            AlertRuleType::ELASTIC,
            AlertRuleType::HEALTH,
            AlertRuleType::VICTORIA_LOGS,
        ];
    }

    /**
     * @return Collection<int, AlertRule>
     */
    public function selectableAlertRulesForSilentDependency(?AlertRule $excludeAlertRule = null)
    {
        $match = [];
        $this->getMatchFilterArray(Request::create('/'), $match);

        $typeValues = array_map(
            fn (AlertRuleType $type) => $type->value,
            $this->silentDependencySupportedTypes(),
        );
        $match['type'] = ['$in' => $typeValues];

        if ($excludeAlertRule !== null) {
            $match['_id'] = ['$ne' => $excludeAlertRule->_id ?? $excludeAlertRule->id];
        }

        $pipeline = [['$match' => $match], ['$sort' => ['name' => 1]]];

        return AlertRule::raw(function ($collection) use ($pipeline) {
            return $collection->aggregate($pipeline);
        });
    }

    /**
     * @param  iterable<int, AlertRule>  $alertRules
     * @return list<array{id: string, name: string, type: string, state: string}>
     */
    public function formatSelectableAlertRulesForApi(iterable $alertRules): array
    {
        $formatted = [];

        foreach ($alertRules as $alertRule) {
            /** @var AlertRule $alertRule */
            [$state] = $alertRule->getStatus();

            $formatted[] = [
                'id' => (string) ($alertRule->_id ?? $alertRule->id),
                'name' => (string) ($alertRule->name ?? ''),
                'type' => $alertRule->type instanceof AlertRuleType ? $alertRule->type->value : (string) $alertRule->type,
                'state' => $state,
            ];
        }

        return $formatted;
    }

    public function getMatchFilterArray($request, &$match)
    {

        $user = \Auth::user();
        $scope = $request->input('scope', 'assigned');

        if (! $user->isAdmin()) {
            $assignedOr = [
                ['userId' => $user->id],
                ['userIds' => $user->id],
            ];
            $userTeams = $this->teamService->userTeams($user)->pluck('id')->toArray();
            if (! empty($userTeams)) {
                $assignedOr[] = ['teamIds' => ['$in' => $userTeams]];
            }

            if ($scope === 'organization') {
                $assignedOr[] = ['isPrivate' => ['$ne' => true]];
                $match['$or'] = $assignedOr;
            } else {
                $match['$or'] = $assignedOr;
            }

        }

        if ($request->filled('alertname')) {
            $match['name'] = [
                '$regex' => $request->alertname,
                '$options' => 'i',
            ];
        }

        if ($request->filled('userId')) {
            $userFilterOr = [
                ['userId' => $request->userId],
                ['userIds' => $request->userId],
            ];

            // Keep access-control $or for non-admins; AND the userId filter with it.
            if (isset($match['$or'])) {
                $match['$and'] = [
                    ['$or' => $match['$or']],
                    ['$or' => $userFilterOr],
                ];
                unset($match['$or']);
            } else {
                $match['$or'] = $userFilterOr;
            }
        }

        if ($request->filled('types')) {
            $types = explode(',', $request->types);
            $match['type'] = ['$in' => $types];
        }

        if ($request->filled('tags')) {
            $tags = explode(',', $request->tags);
            $match['tags'] = ['$all' => $tags];
        }

        if ($request->has('silentStatus')) {
            $silent = $request->silentStatus == 'silent' ? 1 : 0;
            if ($silent) {
                $match['silentUserIds'] = ['$in' => [$user->id]];
            } else {
                $match['silentUserIds'] = ['$nin' => [$user->id]];
            }
        }

        if ($request->filled('endpointId')) {
            $match['endpointIds'] = ['$in' => [$request->endpointId]];
        }

        if ($request->filled('status')) {
            $match['state'] = $request->status;
        }
    }

    public function getAllHistory(Request $request)
    {
        if ($request->has('perPage')) {
            $perPage = (int) $request->perPage;
        } else {
            $perPage = 50;
        }

        $alerts = $request->has('alerts') ? $request->get('alerts') : [];

        $filterCreatedAtArray = [];

        $showElastic = false;
        $showPrometheus = false;
        $showSentry = false;
        $showMetabase = false;
        $showApi = false;
        $showHealth = false;
        $showZabbix = false;

        if ($request->has(Constants::ELASTIC) && ! empty($request->get(Constants::ELASTIC))) {
            $showElastic = true;
        }
        if ($request->has(Constants::PROMETHEUS) && ! empty($request->get(Constants::PROMETHEUS))) {
            $showPrometheus = true;
        }
        if ($request->has(Constants::SENTRY) && ! empty($request->get(Constants::SENTRY))) {
            $showSentry = true;
        }
        if ($request->has(Constants::METABASE) && ! empty($request->get(Constants::METABASE))) {
            $showMetabase = true;
        }
        if ($request->has(Constants::API) && ! empty($request->get(Constants::API))) {
            $showApi = true;
        }

        if ($request->has(Constants::ZABBIX) && ! empty($request->get(Constants::ZABBIX))) {
            $showZabbix = true;
        }

        if ($request->has(Constants::HEALTH) && ! empty($request->get(Constants::HEALTH))) {
            $showHealth = true;
        }

        if ($request->has('from') && ! empty($request->from)) {
            $date = Carbon::createFromFormat('Y-m-d H:i', $request->from);
            $filterCreatedAtArray['$gte'] = new UTCDateTime($date->getTimestamp() * 1000);
        }

        if ($request->has('to') && ! empty($request->to)) {
            $date = Carbon::createFromFormat('Y-m-d H:i', $request->to);
            $filterCreatedAtArray['$lte'] = new UTCDateTime($date->getTimestamp() * 1000);
        }
        $page = $request->page ?? 1;

        $query = ApiAlertHistory::raw(function ($collection) use ($filterCreatedAtArray, $alerts, $page, $perPage, $showHealth, $showZabbix, $showApi, $showPrometheus, $showSentry, $showMetabase, $showElastic) {

            $aggregationArray = [];

            /*       $aggregationArray[] = [
                       '$sort' => [
                           'createdAt' => -1,
                       ]
                   ];


                   $aggregationArray[] = [
                       '$facet' => [
                           'metadata' => [['$count' => 'totalCount']],
                           "data" => [['$skip' => ($page - 1) * $perPage], ['$limit' => $perPage]],
                       ]
                   ];*/

            if (! empty($alerts) || ! empty($filterCreatedAtArray)) {
                $matchAggregationArray = [];
                if (! empty($alerts)) {
                    $matchAggregationArray['alertRule_id'] = ['$in' => $alerts];

                }
                if (! empty($filterCreatedAtArray)) {
                    $matchAggregationArray['createdAt'] = $filterCreatedAtArray;
                }
                $aggregationArray[] = [
                    '$match' => $matchAggregationArray,
                ];

            }

            $aggregationArray[] = [
                '$sort' => [
                    'createdAt' => -1,
                ],
            ];
            $aggregationArray[] = [
                '$limit' => ($page + 1) * $perPage,
            ];

            $aggregationArray[] = [
                '$addFields' => [
                    'alert_type' => Constants::API,
                ],
            ];

            if ($showSentry) {
                $pipelineArray = [];

                if (! empty($alerts) || ! empty($filterCreatedAtArray)) {
                    $matchAggregationArray = [];
                    if (! empty($alerts)) {
                        $matchAggregationArray['alertRuleId'] = ['$in' => $alerts];

                    }
                    if (! empty($filterCreatedAtArray)) {
                        $matchAggregationArray['createdAt'] = $filterCreatedAtArray;
                    }
                    $pipelineArray[] = [
                        '$match' => $matchAggregationArray,
                    ];

                }

                $pipelineArray[] = [
                    '$sort' => [
                        'createdAt' => -1,
                    ],
                ];
                $pipelineArray[] = [
                    '$limit' => ($page + 1) * $perPage,
                ];

                $pipelineArray[] = [
                    '$addFields' => [
                        'alert_type' => Constants::SENTRY,
                    ],
                ];

                $aggregationArray[] = [
                    '$unionWith' => [
                        'coll' => 'sentry_webhook_alerts',
                        'pipeline' => $pipelineArray,
                    ],
                ];
            }
            if ($showMetabase) {
                $pipelineArray = [];

                if (! empty($alerts) || ! empty($filterCreatedAtArray)) {
                    $matchAggregationArray = [];
                    if (! empty($alerts)) {
                        $matchAggregationArray['alertRuleId'] = ['$in' => $alerts];

                    }
                    if (! empty($filterCreatedAtArray)) {
                        $matchAggregationArray['createdAt'] = $filterCreatedAtArray;
                    }
                    $pipelineArray[] = [
                        '$match' => $matchAggregationArray,
                    ];

                }

                $pipelineArray[] = [
                    '$sort' => [
                        'createdAt' => -1,
                    ],
                ];
                $pipelineArray[] = [
                    '$limit' => ($page + 1) * $perPage,
                ];

                $pipelineArray[] = [
                    '$addFields' => [
                        'alert_type' => Constants::METABASE,
                    ],
                ];

                $aggregationArray[] = [
                    '$unionWith' => [
                        'coll' => 'metabase_webhook_alerts',
                        'pipeline' => $pipelineArray,
                    ],
                ];
            }
            if ($showPrometheus) {
                $pipelineArray = [];

                if (! empty($alerts) || ! empty($filterCreatedAtArray)) {
                    $matchAggregationArray = [];
                    if (! empty($alerts)) {
                        $matchAggregationArray['alertRuleId'] = ['$in' => $alerts];

                    }
                    if (! empty($filterCreatedAtArray)) {
                        $matchAggregationArray['createdAt'] = $filterCreatedAtArray;
                    }
                    $pipelineArray[] = [
                        '$match' => $matchAggregationArray,
                    ];

                }

                $pipelineArray[] = [
                    '$sort' => [
                        'createdAt' => -1,
                    ],
                ];
                $pipelineArray[] = [
                    '$limit' => ($page + 1) * $perPage,
                ];

                $pipelineArray[] = [
                    '$addFields' => [
                        'alert_type' => Constants::PROMETHEUS,
                    ],
                ];

                $aggregationArray[] = [
                    '$unionWith' => [
                        'coll' => 'prometheus_histories',
                        'pipeline' => $pipelineArray,
                    ],
                ];

            }

            if ($showHealth) {
                $pipelineArray = [];

                if (! empty($alerts) || ! empty($filterCreatedAtArray)) {
                    $matchAggregationArray = [];
                    if (! empty($alerts)) {
                        $matchAggregationArray['alertRuleId'] = ['$in' => $alerts];

                    }
                    if (! empty($filterCreatedAtArray)) {
                        $matchAggregationArray['createdAt'] = $filterCreatedAtArray;
                    }
                    $pipelineArray[] = [
                        '$match' => $matchAggregationArray,
                    ];

                }

                $pipelineArray[] = [
                    '$sort' => [
                        'createdAt' => -1,
                    ],
                ];
                $pipelineArray[] = [
                    '$limit' => ($page + 1) * $perPage,
                ];

                $pipelineArray[] = [
                    '$addFields' => [
                        'alert_type' => Constants::HEALTH,
                    ],
                ];

                $aggregationArray[] = [
                    '$unionWith' => [
                        'coll' => 'health_histories',
                        'pipeline' => $pipelineArray,
                    ],
                ];

            }
            if ($showElastic) {

                $pipelineArray = [];

                if (! empty($alerts) || ! empty($filterCreatedAtArray)) {
                    $matchAggregationArray = [];
                    if (! empty($alerts)) {
                        $matchAggregationArray['alertRuleId'] = ['$in' => $alerts];

                    }
                    if (! empty($filterCreatedAtArray)) {
                        $matchAggregationArray['createdAt'] = $filterCreatedAtArray;
                    }
                    $pipelineArray[] = [
                        '$match' => $matchAggregationArray,
                    ];

                }

                $pipelineArray[] = [
                    '$sort' => [
                        'createdAt' => -1,
                    ],
                ];
                $pipelineArray[] = [
                    '$limit' => ($page + 1) * $perPage,
                ];

                $pipelineArray[] = [
                    '$addFields' => [
                        'alert_type' => Constants::ELASTIC,
                    ],
                ];

                $aggregationArray[] = [
                    '$unionWith' => [
                        'coll' => 'elastic_histories',
                        'pipeline' => $pipelineArray,
                    ],
                ];

            }

            if ($showZabbix) {

                $pipelineArray = [];

                if (! empty($alerts) || ! empty($filterCreatedAtArray)) {
                    $matchAggregationArray = [];
                    if (! empty($alerts)) {
                        $matchAggregationArray['alertRuleId'] = ['$in' => $alerts];

                    }
                    if (! empty($filterCreatedAtArray)) {
                        $matchAggregationArray['createdAt'] = $filterCreatedAtArray;
                    }
                    $pipelineArray[] = [
                        '$match' => $matchAggregationArray,
                    ];

                }

                $pipelineArray[] = [
                    '$sort' => [
                        'createdAt' => -1,
                    ],
                ];
                $pipelineArray[] = [
                    '$limit' => ($page + 1) * $perPage,
                ];

                $pipelineArray[] = [
                    '$addFields' => [
                        'alert_type' => Constants::ZABBIX,
                    ],
                ];

                $aggregationArray[] = [
                    '$unionWith' => [
                        'coll' => 'zabbix_webhook_alerts',
                        'pipeline' => $pipelineArray,
                    ],
                ];

            }
            //                $aggregationArray[] = [
            //                    '$unionWith' => [
            //                        'coll' => 'grafana_webhook_alerts',
            //                        "pipeline" => [
            //                            [
            //                                '$addFields' => [
            //                                    'alert_type' => Constants::GRAFANA,
            //                                ]
            //                            ]
            //                        ]
            //                    ]
            //                ];

            if (! $showApi) {
                $aggregationArray[] = [
                    '$match' => [
                        'alert_type' => ['$not' => ['$eq' => Constants::API]],
                    ],
                ];
            }

            $aggregationArray[] = [
                '$sort' => [
                    'createdAt' => -1,
                ],
            ];

            $aggregationArray[] = [
                '$facet' => [
                    'metadata' => [['$count' => 'totalCount']],
                    'data' => [['$skip' => ($page - 1) * $perPage], ['$limit' => $perPage]],
                ],
            ];

            return $collection->aggregate($aggregationArray);
        });
        $result = collect($query)->toArray()[0];
        $data = json_decode(json_encode(iterator_to_array($result['data'])), true);
        if (! empty($data)) {
            $isEnd = json_decode(json_encode(iterator_to_array($result['metadata'])), true)[0]['totalCount'] <= $perPage * $page;
            $data = collect($data)->map(function ($array) {
                $array['id'] = $array['_id']['$oid'];
                $array['createdAt'] = Carbon::createFromTimestampMs($array['createdAt']['$date']['$numberLong'])->format('Y-m-d H:i:s');

                return $array;
            });

            return compact('data', 'isEnd', 'page');
        } else {
            return '';
        }

    }

    public function getHistory($alert,
        int $perPage = 50,
        ?Carbon $from = null,
        ?Carbon $to = null)
    {

        $query = match ($alert->type) {
            AlertRuleType::PMM => GrafanaWebhookAlert::query(),
            AlertRuleType::GRAFANA => GrafanaWebhookAlert::query(),
            AlertRuleType::PROMETHEUS => PrometheusHistory::query(),
            AlertRuleType::SENTRY => SentryWebhookAlert::query(),
            AlertRuleType::SPLUNK => SplunkWebhookAlert::query(),
            AlertRuleType::METABASE => MetabaseWebhookAlert::query(),
            AlertRuleType::ZABBIX => ZabbixWebhookAlert::query(),
            AlertRuleType::API => ApiAlertHistory::query(),
            AlertRuleType::NOTIFICATION => ApiAlertHistory::query(),
            AlertRuleType::HEALTH => HealthHistory::query(),
            AlertRuleType::ELASTIC => ElasticHistory::query(),
            AlertRuleType::VICTORIA_LOGS => VictoriaLogsHistory::query(),
            default => throw new ModelNotFoundException,
        };

        $query->where('alertRuleId', $alert->id)->latest();

        if ($from) {
            $query->where('createdAt', '>=', $from);
        }

        if ($to) {
            $query->where('createdAt', '<=', $to);
        }

        $data = $query->paginate($perPage)->toArray();

        $arrayData = $data['data'];
        foreach ($arrayData as &$item) {
            $item['updatedAt'] = Utilities::ConvertUTCTimeTOJalali($item['updatedAt']);
            $item['createdAt'] = Utilities::ConvertUTCTimeTOJalali($item['createdAt']);
        }
        $data['data'] = $arrayData;

        return $data;
    }

    public function createHealthDataSource(DataSource $dataSource) {}

    public function createHealthCluster(SkylogsInstance|ConfigSkylogs $instance)
    {
        if ($instance instanceof ConfigSkylogs) {
            $alert = AlertRule::create([
                'name' => 'Main Cluster',
                'type' => AlertRuleType::HEALTH,
                'userId' => app(UserService::class)->admin()->id,
                'url' => $instance->sourceUrl,
                'checkType' => HealthAlertType::SOURCE_CLUSTER,
                'threshold' => 5,
                'sourceToken' => $instance->sourceToken,
            ]);
        } else {
            $alert = AlertRule::create([
                'name' => 'Health Cluster '.$instance->name,
                'type' => AlertRuleType::HEALTH,
                'userId' => \Auth::id(),
                'skylogsInstanceId' => $instance->id,
                'url' => $instance->url,
                'checkType' => HealthAlertType::AGENT_CLUSTER,
                'threshold' => 5,
                'agentToken' => $instance->token,
            ]);
        }

        return $alert;

    }

    public function deleteHealthCluster(SkylogsInstance $instance)
    {
        AlertRule::where('skylogsInstanceId', $instance->id)->delete();
        HealthCheck::where('skylogsInstanceId', $instance->id)->delete();
        $this->flushCache();
    }

    public function hasAdminAccessAlert(User $user, AlertRule $alert): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->userOwnsAlert($user, $alert);
    }

    public function isPrivateAlert(AlertRule $alert): bool
    {
        return (bool) ($alert->isPrivate ?? false);
    }

    public function hasReadAccessAlert(User $user, AlertRule $alert): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->hasUserAccessAlert($user, $alert)) {
            return true;
        }

        return ! $this->isPrivateAlert($alert);
    }

    public function resolveAccessLevel(User $user, AlertRule $alert): AlertRuleAccessLevel
    {
        if ($this->hasUserAccessAlert($user, $alert)) {
            return AlertRuleAccessLevel::Manage;
        }

        if ($this->hasReadAccessAlert($user, $alert)) {
            return AlertRuleAccessLevel::Readonly;
        }

        return AlertRuleAccessLevel::None;
    }

    public function hasTeamAccessAlert(User $user, AlertRule $alert): bool
    {
        $alertTeamIds = $alert->teamIds ?? [];
        if ($alertTeamIds === []) {
            return false;
        }

        $userTeamIds = $this->teamService->userTeams($user)->pluck('id')->toArray();
        if ($userTeamIds === []) {
            return false;
        }

        return collect($alertTeamIds)->intersect($userTeamIds)->isNotEmpty();
    }

    public function hasUserAccessAlert(User $user, AlertRule $alert): bool
    {
        if ($this->hasAdminAccessAlert($user, $alert)) {
            return true;
        }

        if ($this->userIsListedOnAlert($user, $alert)) {
            return true;
        }

        return $this->hasTeamAccessAlert($user, $alert);
    }

    public function userOwnsAlert(User $user, AlertRule $alert): bool
    {
        return $user->id == $alert->userId || $user->_id == $alert->userId;
    }

    public function userIsListedOnAlert(User $user, AlertRule $alert): bool
    {
        $userIds = $alert->userIds ?? [];

        return in_array($user->id, $userIds, true) || in_array($user->_id, $userIds, true);
    }

    public function getAlerts(AlertRuleType|array|null $type = null)
    {
        $tagsArray = ['alertRule'];
        $keyName = 'alertRule';
        if (! empty($type)) {
            if (is_array($type)) {
                foreach ($type as $alertType) {
                    $tagsArray[] = $alertType->value;
                    $keyName .= ':'.$alertType->value;
                }
            } else {
                $tagsArray[] = $type->value;
                $keyName .= ':'.$type->value;
            }
        }

        return Cache::tags($tagsArray)->rememberForever($keyName, fn () => $this->getAlertsDB($type));

    }

    public function getAlertsDB(AlertRuleType|array|null $type = null)
    {
        if (! empty($type)) {
            if ($type instanceof AlertRuleType) {
                return AlertRule::where('type', $type)->get();
            } else {
                return AlertRule::whereIn('type', $type)->get();
            }
        } else {
            return AlertRule::get();
        }
    }

    public function flushCache(): void
    {
        Cache::tags(['alertRule'])->flush();
    }

    public function deleteForUser(User $user, AlertRule $alert)
    {
        if ($alert->userId == $user->id || \Auth::user()->isAdmin()) {
            $this->delete($alert);
        } else {
            $alert->pull('userIds', $user->id);
            if (! empty($alert->endpointIds)) {
                $userEndpoints = Endpoint::whereIn('_id', $alert->endpointIds)->where('userId', $user->id)->get();
                foreach ($userEndpoints as $endpoint) {
                    $alert->pull('endpointIds', $endpoint->_id);
                }

            }

        }
    }

    public function update(AlertRule $alertRule)
    {
        switch ($alertRule->type) {
            case AlertRuleType::HEALTH:
                HealthCheck::where('alertRuleId', $alertRule->id)->delete();
        }
    }

    public function delete(AlertRule $alertRule)
    {
        $alertRuleId = $alertRule->_id;
        $type = $alertRule->type;
        $alertRule->delete();
        switch ($type) {
            case AlertRuleType::API:
            case AlertRuleType::NOTIFICATION:
                AlertInstance::where('alertRuleId', $alertRuleId)->delete();
                break;
            case AlertRuleType::PROMETHEUS:
                PrometheusCheck::where('alertRuleId', $alertRuleId)->delete();
                break;
            case AlertRuleType::ELASTIC:
                ElasticCheck::where('alertRuleId', $alertRuleId)->delete();
                break;
            case AlertRuleType::VICTORIA_LOGS:
                VictoriaLogsCheck::where('alertRuleId', $alertRuleId)->delete();
                break;
            case AlertRuleType::GRAFANA:
            case AlertRuleType::PMM:
                GrafanaCheck::where('alertRuleId', $alertRuleId)->delete();
                break;
        }

    }

    public function ChangeOwner(User $fromUser, User $toUser)
    {

        $alerts = AlertRule::where(function ($query) use ($fromUser) {
            return $query->where('userId', $fromUser->id)
                ->orWhereIn('userIds', [$fromUser->id]);
        })->get();

        foreach ($alerts as $alert) {
            if ($alert->userId == $fromUser->id) {
                $alert->userId = $toUser->id;
                $alert->save();
            } elseif (in_array($fromUser->id, $alert->userIds ?? [])) {
                $alert->push('userIds', $toUser->id, true);
                $alert->pull('userIds', $fromUser->id);
                $alert->save();
            }
        }

    }

    public function resolveAlertManually($alert)
    {
        $sendResolve = false;

        switch ($alert->type) {
            case AlertRuleType::API:
                $apiAlerts = AlertInstance::where('alertRuleId', $alert->id)
                    ->where('state', AlertInstance::FIRE)
                    ->get();
                if ($apiAlerts->isNotEmpty()) {
                    $sendResolve = true;
                    foreach ($apiAlerts as $apiAlert) {
                        $apiAlert->description = '';
                        $apiAlert->state = AlertInstance::RESOLVED;
                        $apiAlert->save();
                        $apiHistory = $apiAlert->createHistory();
                        $apiAlert->createStatusHistory($apiHistory);
                    }
                }
                app(ApiService::class)->refreshStatus($alert);
                break;
            case AlertRuleType::SENTRY:
                if (empty($alert->state) || $alert->state != AlertRule::RESOlVED) {
                    $sendResolve = true;
                    $alert->state = AlertRule::RESOlVED;
                    $alert->save();

                    SentryWebhookAlert::create([
                        'alertRuleName' => $alert->name,
                        'dataSourceAlertName' => $alert->dataSourceAlertName,
                        'alertRuleId' => $alert->_id,
                        'action' => 'resolved',
                        'message' => 'resolved manually.',
                        'description' => 'resolved manually.',
                    ]);
                }
                break;
            case AlertRuleType::ZABBIX:
                if (empty($alert->state) || $alert->state != AlertRule::RESOlVED) {
                    $sendResolve = true;
                    $alert->state = AlertRule::RESOlVED;
                    $alert->fireCount = 0;
                    $alert->save();
                    $zabbixCheck = ZabbixCheck::where('alertRuleId', $alert->id)->first();
                    if ($zabbixCheck) {
                        $zabbixCheck->fireEvents = [];
                        $zabbixCheck->save();
                    }
                }
                break;
            case AlertRuleType::PROMETHEUS:
                $prometheusAlert = PrometheusCheck::where('alertRuleId', $alert->_id)->where('state', PrometheusCheck::FIRE)->first();
                if ($prometheusAlert && $prometheusAlert->state == PrometheusCheck::FIRE) {
                    $prometheusAlert->state = PrometheusCheck::RESOLVED;
                    $prometheusAlert->save();
                    $prometheusAlert->createHistory();
                    $sendResolve = true;
                }
                break;
            case AlertRuleType::ELASTIC:
                $check = ElasticCheck::where('alertRuleId', $alert->_id)->where('state', ElasticCheck::FIRE)->first();
                if ($check && $check->state == ElasticCheck::FIRE) {
                    $check->state = ElasticCheck::RESOLVED;
                    $check->save();

                    ElasticHistory::create([
                        'alertRuleId' => $alert->_id,
                        'alertRuleName' => $alert->name,
                        'dataSourceId' => $alert->dataSourceId,
                        'dataviewName' => $alert->dataviewName,
                        'dataviewTitle' => $alert->dataviewTitle,
                        'queryString' => $alert->queryString,
                        'conditionType' => $alert->conditionType,
                        'minutes' => $alert->minutes,
                        'countDocument' => $alert->countDocument,
                        'currentCountDocument' => -1,
                        'state' => ElasticCheck::RESOLVED,
                    ]);
                    $sendResolve = true;
                }
                break;
            case AlertRuleType::VICTORIA_LOGS:
                $check = VictoriaLogsCheck::where('alertRuleId', $alert->_id)
                    ->where('state', VictoriaLogsCheck::FIRE)
                    ->first();
                if ($check && $check->state == VictoriaLogsCheck::FIRE) {
                    $check->state = VictoriaLogsCheck::RESOLVED;
                    $check->save();

                    VictoriaLogsHistory::create([
                        'alertRuleId' => $alert->_id,
                        'alertRuleName' => $alert->name,
                        'dataSourceId' => $alert->dataSourceId,
                        'queryString' => $alert->queryString,
                        'conditionType' => $alert->conditionType,
                        'minutes' => $alert->minutes,
                        'countDocument' => $alert->countDocument,
                        'currentCountDocument' => -1,
                        'state' => VictoriaLogsCheck::RESOLVED,
                    ]);
                    $sendResolve = true;
                }
                break;
            case AlertRuleType::PMM:
            case AlertRuleType::GRAFANA:
                if ($alert->state == AlertRule::CRITICAL) {
                    $sendResolve = true;
                    $alert->state = AlertRule::RESOlVED;
                    $alert->save();
                    $check = GrafanaCheck::where('alertRuleId', $alert->id)->first();
                    if ($check) {
                        $check->alerts = [];
                        $check->state = GrafanaWebhookAlert::RESOLVED;
                        $check->save();
                    }
                }
                break;

            case AlertRuleType::SPLUNK:
            case AlertRuleType::NOTIFICATION:
            case AlertRuleType::METABASE:
                break;

        }
        $alert->removeAcknowledge();
        if ($sendResolve) {
            app(SendNotifyService::class)->createNotify(SendNotifyJob::RESOLVED_MANUALLY, $alert, $alert->_id);
        }

    }

    /**
     * Build a fixed-bucket status timeline for each of the given alert rules over [fromTime, toTime].
     *
     * Alert rules the user doesn't have access to are silently skipped rather than aborting the
     * whole request, since this endpoint is designed to be called with a batch of ids.
     *
     * @param  array<int, string>  $alertRuleIds
     * @return array<int, array{alertRuleId: string, type: string, name: string, bucketSeconds: int, segments: array<int, array{status: string, count: int, fromTime: int, toTime: int, summary?: string}>}>
     */
    public function getAlertsStatusHistory(
        array $alertRuleIds,
        int $fromTime,
        int $toTime,
        User $user,
    ): array {
        $slotCount = (int) config('alert-status.timeline_slot_count', 100);
        $alertRules = AlertRule::whereIn('_id', $alertRuleIds)
            ->get()
            ->filter(fn (AlertRule $alertRule) => $this->hasReadAccessAlert($user, $alertRule))
            ->keyBy(fn (AlertRule $alertRule) => (string) $alertRule->_id);

        if ($alertRules->isEmpty()) {
            return [];
        }

        $from = Carbon::createFromTimestamp($fromTime);
        $to = Carbon::createFromTimestamp($toTime);

        $factory = app(AlertStatusEventSourceFactory::class);
        $builder = new AlertStatusTimelineBuilder;

        $eventsByAlertRule = collect();

        foreach ($alertRules->groupBy(fn (AlertRule $alertRule) => $alertRule->type->value, preserveKeys: true) as $typeValue => $rulesOfType) {
            $source = $factory->make(AlertRuleType::from($typeValue));

            $baseline = $source->fetchBaseline($rulesOfType, $from);
            $windowEvents = $source->fetchEvents($rulesOfType, $from, $to)->groupBy('alertRuleId');

            foreach ($rulesOfType as $alertRuleId => $alertRule) {
                $events = collect();

                if ($baseline->has($alertRuleId)) {
                    $events->push($baseline->get($alertRuleId));
                }

                $eventsByAlertRule->put($alertRuleId, $events->merge($windowEvents->get($alertRuleId, collect())));
            }
        }

        return $alertRules
            ->map(function (AlertRule $alertRule, string $alertRuleId) use ($eventsByAlertRule, $fromTime, $toTime, $slotCount, $builder) {
                $timeline = $builder->build($eventsByAlertRule->get($alertRuleId, collect()), $fromTime, $toTime, $slotCount);

                return [
                    'alertRuleId' => $alertRuleId,
                    'type' => $alertRule->type->value,
                    'name' => $alertRule->name,
                    'bucketSeconds' => $timeline['bucketSeconds'],
                    'segments' => $timeline['segments'],
                ];
            })
            ->values()
            ->all();
    }
}
