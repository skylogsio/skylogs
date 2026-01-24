<?php

namespace App\Services;

use App\Enums\AlertRuleType;
use App\Enums\HealthAlertType;
use App\Helpers\Constants;
use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use App\Models\Config\ConfigSkylogs;
use App\Models\DataSource\DataSource;
use App\Models\ElasticCheck;
use App\Models\Endpoint;
use App\Models\GrafanaCheck;
use App\Models\HealthCheck;
use App\Models\PrometheusCheck;
use App\Models\SkylogsInstance;
use App\Models\User;
use App\Models\ZabbixCheck;
use App\Models\ZabbixWebhookAlert;
use Cache;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        }

        return [];

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

    public function getMatchFilterArray($request, &$match)
    {

        $user = \Auth::user();

        if (! $user->isAdmin()) {
            $match['$or'] = [
                ['userId' => $user->id],
                ['userIds' => $user->id],
            ];
            $userTeams = $this->teamService->userTeams($user)->pluck('id')->toArray();
            if (! empty($userTeams)) {
                $match['$or'][] = ['teamIds' => ['$in' => $userTeams]];
            }

        }

        if ($request->filled('alertname')) {
            $match['name'] = [
                '$regex' => $request->alertname,
                '$options' => 'i',
            ];
        }

        if ($request->filled('userId')) {
            $match['$or'] = [
                ['userId' => $request->userId],
                ['userIds' => $request->userId],
            ];
            $userTeams = $this->teamService->userTeams($user)->pluck('id')->toArray();
            if (! empty($userTeams)) {
                $match['$or'][] = ['teamIds' => ['$in' => $userTeams]];
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

        if ($request->filled('userId')) {
            $match['userId'] = $request->userId;
        }

        if ($request->filled('status')) {
            $match['state'] = $request->status;
        }
    }

    public static function GetAllHistory(Request $request)
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

    public function hasAdminAccessAlert(User $user, AlertRule $alert)
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($user->_id == $alert->userId) {
            return true;
        }

        return false;
    }

    public function hasUserAccessAlert(User $user, AlertRule $alert): bool
    {
        if ($this->hasAdminAccessAlert($user, $alert)) {
            return true;
        }
        $userIds = $alert->userIds ?? [];
        if (in_array($user->_id, $userIds)) {
            return true;
        }

        $teamIds = $this->teamService->userTeams($user)->pluck('id')->toArray();
        if (! empty($teamIds) && ! empty($user->teamIds) && collect($user->teamIds)->intersect($teamIds)->isNotEmpty()) {
            return true;
        }

        return false;
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
                $alert->push('user_ids', $toUser->id, true);
                $alert->pull('userIds', $fromUser->id);
                $alert->pull('user_ids', $fromUser->id);
                $alert->save();
            }
        }

    }
}
