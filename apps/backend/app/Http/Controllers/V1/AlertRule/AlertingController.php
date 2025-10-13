<?php

namespace App\Http\Controllers\V1\AlertRule;


use App\Enums\AlertRuleType;
use App\Http\Controllers\Controller;
use App\Jobs\SendNotifyJob;
use App\Models\AlertInstance;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use App\Models\DataSource\DataSource;
use App\Models\ElasticCheck;
use App\Models\ElasticHistory;
use App\Models\GrafanaWebhookAlert;
use App\Models\HealthHistory;
use App\Models\MetabaseWebhookAlert;
use App\Models\PrometheusCheck;
use App\Models\PrometheusHistory;
use App\Models\SentryWebhookAlert;
use App\Models\ZabbixWebhookAlert;
use App\Services\AlertRuleService;
use App\Services\ApiService;
use App\Services\EndpointService;
use App\Services\SendNotifyService;
use App\Services\UserService;
use App\Services\ZabbixService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Morilog\Jalali\Jalalian;
use Str;

class AlertingController extends Controller
{

    public function __construct(
        protected AlertRuleService $alertRuleService,
        protected EndpointService  $endpointService,
        protected ZabbixService    $zabbixService,
    )
    {
    }


    public function Index(Request $request)
    {

        $perPage = $request->perPage ? intval($request->perPage) : 25;
        $currentUser = \Auth::user();
        $userId = $currentUser->id;

        $pipeline = [];

        $match = [];

        $this->alertRuleService->getMatchFilterArray($request, $match);

        if (!empty($match)) {
            $pipeline[] = ['$match' => $match];
        }

        $pipeline[] = [
            '$addFields' => [
                'isPinned' => [
                    '$in' => [
                        $currentUser->_id,
                        ['$ifNull' => ['$pinUserIds', []]]
                    ]
                ]
            ]
        ];

        $pipeline[] = ['$sort' => ['isPinned' => -1, '_id' => 1]];


        $page = $request->input('page', 1);
        $skip = ($page - 1) * $perPage;


        $pipeline[] = ['$skip' => $skip];
        $pipeline[] = ['$limit' => $perPage];

        $data = AlertRule::raw(function ($collection) use ($pipeline) {
            return $collection->aggregate($pipeline);
        });

        $total = AlertRule::raw(function ($collection) use ($match) {
            if (empty($match)) {
                $pipeline = [
                    ['$count' => 'total'],
                ];
            } else {
                $pipeline = [
                    ['$match' => $match],
                    ['$count' => 'total']
                ];
            }
            return $collection->aggregate($pipeline)->toArray();
        });
        $total = !empty($total) ? $total[0]['total'] : 0;

        $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
            $data,
            $total,
            $perPage,
            $page,
            ['path' => $request->url()]
        );

        foreach ($paginatedData as &$alert) {
//            $alert =new AlertRule($alert);
            /** @var $alert AlertRule */
            $alert->hasAdminAccess = $this->alertRuleService->hasAdminAccessAlert($currentUser, $alert);
            $alert->has_admin_access = $alert->hasAdminAccess;
            [$alertStatus, $alertStatusCount] = $alert->getStatus();
            $alert->statusLabel = $alertStatus;
            $alert->statusCount = $alertStatusCount;
            $alert->status_label = $alertStatus;
            $isSilent = $alert->isSilent();
            $alert->isSilent = $isSilent;
            $alert->is_silent = $isSilent;
            $alert->countEndpoints = $this->endpointService->countUserEndpointAlert($currentUser, $alert);
            $alert->count_endpoints = $alert->countEndpoints;

            $extraField = [];
            if (!empty($alert->extraField)) {
                foreach ($alert->extraField as $key => $value) {
                    $extraField[] = [
                        "key" => $key,
                        "value" => $value,
                    ];
                }
            }
            $alert->extraField = $extraField;
        }


        return response()->json($paginatedData);


    }


    public function Pin($id)
    {
        $alert = AlertRule::where("_id", $id)->first();
        if ($alert->isPin()) {
            $alert->unPin();
        } else {
            $alert->pin();
        }

        return response()->json(['status' => true, "isPin" => $alert->isPin()]);
    }

    public function Acknowledge($id)
    {
        $alert = AlertRule::where("_id", $id)->first();
        $user = Auth::user();

        if (!$this->alertRuleService->hasUserAccessAlert($user, $alert)) {
            abort(403);
        }

        $alert->acknowledge($user);
        SendNotifyService::CreateNotify(SendNotifyJob::ALERT_RULE_ACKNOWLEDGED, $alert, $alert->_id);

        return response()->json(['status' => true]);
    }

    public function AcknowledgeLoginLink($id)
    {
        $alert = AlertRule::where("_id", $id)->first();
        $user = app(UserService::class)->admin();
        if ($alert->isAcknowledged()) {
            return response()->json(['status' => false, "message" => "Alert rule Already Acknowledged."]);
        }
        $alert->acknowledge($user);
        SendNotifyService::CreateNotify(SendNotifyJob::ALERT_RULE_ACKNOWLEDGED, $alert, $alert->_id);

        return response()->json(['status' => true]);
    }


    public function FilterEndpoints()
    {

        $currentUser = \Auth::user();

        $selectableEndpoints = $this->endpointService->selectableUserEndpoint($currentUser);

        return response()->json($selectableEndpoints);
    }

    public function GetTypes()
    {
        return response()->json(AlertRuleType::GetTypes());
    }

    public function Silent($id)
    {
        $alert = AlertRule::where("_id", $id)->first();
        if ($alert->isSilent()) {
            $alert->unSilent();
        } else {
            $alert->silent();
        }

        return ['status' => true, "isSilent" => $alert->isSilent()];
    }


    public function Store(Request $request)
    {


        $va = \Validator::make(
            $request->all(),
            [
                'name' => "required|unique:alert_rules",
                'type' => "required",
                'dataSourceAlertName' => "required_if:type," . AlertRuleType::GetDataSourceAlertNeed()->implode(','),
                "dataSourceId" => "required_if:type," . AlertRuleType::ELASTIC->value,
            ], [
            ]
        );
//        dd("");
        if ($va->passes()) {
            $alertType = AlertRuleType::tryFrom($request->type);
            $commonFields = [
                'name' => $request->name,
                'type' => $request->type,
                'showAcknowledgeBtn' => $request->boolean('showAcknowledgeBtn'),
                'tags' => $request->tags ?? [],
//                "userId" => $request->userId,
//                "instances" => $request->instance ?? [],
//                "silentUserIds" => $request->silentUserIds,
                "userId" => \Auth::id(),
                "endpointIds" => [],
                "userIds" => [],
            ];
            switch ($alertType) {
                case AlertRuleType::GRAFANA:
                case AlertRuleType::PMM:
                case AlertRuleType::PROMETHEUS:

                    $alert = AlertRule::create([
                        ...$commonFields,
                    ]);
                    $alert->queryType = $request->queryType;
                    $extraField = [];
                    $queryText = "";
                    $dataSourceIds = [];
                    $dataSourceAlertname = "";
                    if ($alert->queryType == AlertRule::DYNAMIC_QUERY_TYPE) {
                        $dataSourceIds = array_unique($request->dataSourceIds ?? []);
                        $dataSourceAlertname = $request->dataSourceAlertName;
                        if ($request->has("extraField") && !empty($request->extraField))
                            foreach ($request->extraField as $value) {
                                if (!empty($value)) {
                                    if (!empty($value["key"]) && !empty($value["value"]))
                                        $extraField[$value["key"]] = $value['value'];
                                }
                            }
                    } else {
                        $queryText = $request->queryText;
                        $alert->queryObject = $request->queryObject;
                    }

                    $alert->dataSourceIds = $dataSourceIds;
                    $alert->dataSourceAlertName = $dataSourceAlertname ?? "";
                    $alert->queryText = $queryText;

                    $alert->extraField = $extraField;

                    $alert->save();

                    break;
                case AlertRuleType::METABASE:
                case AlertRuleType::SENTRY:
                case AlertRuleType::SPLUNK:

                    $alert = AlertRule::create([
                        ...$commonFields,
                        "dataSourceIds" => array_unique($request->dataSourceIds ?? []),
                        "dataSourceAlertName" => $request->dataSourceAlertName,
                    ]);
                    break;
                case AlertRuleType::ZABBIX:
                    $alert = AlertRule::create([
                        ...$commonFields,
                        "dataSourceIds" => array_unique($request->dataSourceIds ?? []),
                        "hosts" => $request->hosts ?? [],
                        "actions" => $request->actions ?? [],
                        "severities" => $request->severities ?? [],
                    ]);
                    break;
                case AlertRuleType::NOTIFICATION:

                    $alert = AlertRule::create([
                        ...$commonFields,
                        "apiToken" => Str::random(60),
                    ]);
                    break;
                case AlertRuleType::API:

                    $enableAutoResolve = !empty($request->enableAutoResolve) && $request->enableAutoResolve;

                    $autoResolveMinutes = 0;
                    if ($enableAutoResolve) {
                        $autoResolveMinutes = ((int)$request->autoResolveMinutes);
                    }
                    $alert = AlertRule::create([
                        ...$commonFields,
                        "enableAutoResolve" => $enableAutoResolve,
                        "autoResolveMinutes" => $autoResolveMinutes,
                        "apiToken" => Str::random(60),
                    ]);
                    break;
                case AlertRuleType::ELASTIC:

                    $alert = AlertRule::create([
                        ...$commonFields,
                        "dataSourceId" => $request->dataSourceId,
                        "dataviewName" => $request->dataviewName,
                        "dataviewTitle" => $request->dataviewTitle,
                        "queryString" => $request->queryString,
                        "minutes" => ((int)$request->minutes),
                        "conditionType" => $request->conditionType,
                        "countDocument" => ((int)$request->countDocument),
                    ]);
                    break;
            }
            $alert->tags = collect($request->tags ?? [])->map(fn($item) => trim($item))->unique()->toArray();

            $alert->save();

            if ($request->has("endpointIds") && !empty($request->endpointIds))
                foreach ($request->endpointIds as $end) {
                    $alert->push("endpoint_ids", $end, true);
                    $alert->push("endpointIds", $end, true);
                }

            if ($request->has("userIds") && !empty($request->userIds))
                foreach ($request->userIds as $userId) {
                    $alert->push("user_ids", $userId, true);
                    $alert->push("userIds", $userId, true);
                }


            return ['status' => true];
        } else {
            return ['status' => false, "message" => $va->messages()[0] ?? "Error"];
        }
    }

    public function Show($id)
    {
        $alert = AlertRule::where("_id", $id)->firstOrFail();
        $userIds = [];
        if (!empty($alert->userIds)) {
            $userIds = $alert->userIds;
        }
        $userIds[] = $alert->userId;
        $currentUser = Auth::user();

        if (!($currentUser->isAdmin() || in_array($currentUser->_id, $userIds))) {
            abort(403);
        }

        if (!empty($alert->dataSourceIds)) {
            $alert->dataSourceLabels = DataSource::whereIn("id", $alert->dataSourceIds)->get()->pluck("name")->toArray();
        }
        $extraField = [];
        if (!empty($alert->extraField)) {
            foreach ($alert->extraField as $key => $value) {
                $extraField[] = [
                    "key" => $key,
                    "value" => $value,
                ];
            }
        }
        $alert->extraField = $extraField;

        $alert->hasAdminAccess = $this->alertRuleService->hasAdminAccessAlert($currentUser, $alert);
        $alert->has_admin_access = $alert->hasAdminAccess;
        [$alertStatus, $alertStatusCount] = $alert->getStatus();
        $alert->statusLabel = $alertStatus;
        $alert->status_label = $alertStatus;
        $alert->statusCount = $alertStatusCount;
        $alert->ownerName = $alert->user->name;
        $isSilent = $alert->isSilent();
        $alert->isSilent = $isSilent;
        $alert->is_silent = $isSilent;
        $alert->countEndpoints = $this->endpointService->countUserEndpointAlert($currentUser, $alert);
        $alert->count_endpoints = $alert->countEndpoints;


        return response()->json($alert);
    }


    public function StoreUpdate(Request $request, $id)
    {
        $model = AlertRule::where("_id", $id);
        if (!auth()->user()->isAdmin()) {
            $model = $model->where("userId", auth()->id());
        }
        $model = $model->firstOrFail();
        $model->showAcknowledgeBtn = $request->boolean('showAcknowledgeBtn');

        switch ($model->type) {
            case AlertRuleType::GRAFANA:
            case AlertRuleType::PMM:
            case AlertRuleType::PROMETHEUS:
                $extraField = [];
                $queryText = "";
                $dataSourceIds = [];
                $dataSourceAlertname = "";
                $model->queryType = $request->queryType;
                if ($request->queryType == AlertRule::DYNAMIC_QUERY_TYPE) {
                    $dataSourceIds = array_unique($request->dataSourceIds);
                    $dataSourceAlertname = $request->dataSourceAlertName;

                    if ($request->has("extraField") && !empty($request->extraField))
                        foreach ($request->extraField as $value) {
                            if (!empty($value)) {
                                if (!empty($value["key"]) && !empty($value["value"]))
                                    $extraField[$value["key"]] = $value['value'];
                            }
                        }
                    $model->extraField = $extraField;
                } else {
                    $queryText = $request->queryText;
                    $model->queryObject = $request->queryObject;
                }
                $model->dataSourceIds = $dataSourceIds;
                $model->dataSourceAlertName = $dataSourceAlertname ?? "";
                $model->queryText = $queryText;

                if ($model->isDirty()) {
                    $model->state = null;
                    $model->save();
                }

                break;
            case AlertRuleType::METABASE:
            case AlertRuleType::SENTRY:
            case AlertRuleType::SPLUNK:
                $model->name = $request->name;
                $model->dataSourceIds = array_unique($request->dataSourceIds ?? []);
                $model->dataSourceAlertName = $request->dataSourceAlertName;


                if ($model->isDirty()) {
                    $model->state = null;
                    $model->save();
                }

                break;
            case AlertRuleType::ZABBIX:
                $model->name = $request->name;
                $model->dataSourceIds = array_unique($request->dataSourceIds ?? []);
                $model->hosts = $request->hosts ?? [];
                $model->actions = $request->actions ?? [];
                $model->severities = $request->severities ?? [];

                if ($model->isDirty()) {
                    $model->state = null;
                    $model->save();
                }

                break;
            case AlertRuleType::ELASTIC:
                $model->name = $request->name;
                $model->dataSourceId = $request->dataSourceId;
                $model->dataviewName = $request->dataviewName;
                $model->dataviewTitle = $request->dataviewTitle;
                $model->queryString = $request->queryString;
                $model->conditionType = $request->conditionType;
                $model->minutes = ((int)$request->minutes);
                $model->countDocument = ((int)$request->countDocument);
                $model->save();
                ElasticCheck::where("alertRuleId", $model->_id)->delete();
                break;

            case AlertRuleType::NOTIFICATION:
                AlertInstance::where("alertRuleId", $model->id)->update(['alertRuleName' => $request->name]);
                $model->name = $request->name;
                $model->save();
                break;
            case AlertRuleType::API:
                AlertInstance::where("alertRuleId", $model->id)->update(['alertRuleName' => $request->name]);
                $autoResolveMinutes = 0;
                $enableAutoResolve = !empty($request->enableAutoResolve) && $request->enableAutoResolve;
                if ($enableAutoResolve) {
                    $autoResolveMinutes = ((int)$request->autoResolveMinutes);
                }
                $model->enableAutoResolve = $enableAutoResolve;
                $model->autoResolveMinutes = $autoResolveMinutes;
                $model->name = $request->name;
                $model->save();
                break;
        }

        $alertEndpoints = collect($model->endpointIds);
        $endpointsIds = collect($request->array("endpointIds"));
        $selectableEndpoints = $this->endpointService->selectableUserEndpoint(auth()->user());

        foreach ($alertEndpoints as $end) {
            if ($selectableEndpoints->contains($end)) {
                if ($endpointsIds->doesntContain($end)) {
                    $model->pull("endpoint_ids", $end);
                    $model->pull("endpointIds", $end);
                }
            }
        }

        foreach ($endpointsIds as $endpointId) {
            $model->push("endpoint_ids", $endpointId, true);
            $model->push("endpointIds", $endpointId, true);
        }

        $alertUserIds = collect($model->userIds);
        $userIds = collect($request->array("userIds"));

        foreach ($alertUserIds as $alertUserId) {
            if ($userIds->doesntContain($alertUserId)) {
                $model->pull("user_ids", $alertUserId);
                $model->pull("userIds", $alertUserId);
            }
        }

        foreach ($userIds as $userId) {
            $model->push("user_ids", $userId, true);
            $model->push("userIds", $userId, true);
        }

        $model->tags = collect($request->tags ?? [])->map(fn($item) => trim($item))->unique()->toArray();

        if ($model->isDirty(['tags']))
            $model->save();

        return response()->json(['status' => true]);

    }

    public function ResolveAlert($id)
    {

        $alert = AlertRule::where('_id', $id)->first();
        $sendResolve = false;
        $currentUser = auth()->user();
        if (!$this->alertRuleService->hasUserAccessAlert($currentUser, $alert)) {
            abort(403);
        }

        switch ($alert->type) {
            case AlertRuleType::API:
                $apiAlerts = AlertInstance::where("alertRuleId", $alert->id)
                    ->where("state", AlertInstance::FIRE)
                    ->get();
                if ($apiAlerts->isNotEmpty()) {
                    $sendResolve = true;
                    foreach ($apiAlerts as $apiAlert) {
                        $apiAlert->description = "";
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
                        "alertRuleName" => $alert->name,
                        "dataSourceAlertName" => $alert->dataSourceAlertName,
                        "alertRuleId" => $alert->_id,
                        "action" => "resolved",
                        "message" => "resolved manually.",
                        "description" => "resolved manually.",
                    ]);
                }
                break;
            case AlertRuleType::ZABBIX:
                if (empty($alert->status) || $alert->status != AlertRule::RESOlVED) {
                    $sendResolve = true;
                    $alert->status = AlertRule::RESOlVED;
                    $alert->save();
                }
                break;
            case AlertRuleType::PROMETHEUS:
                $prometheusAlert = PrometheusCheck::where("alertRuleId", $alert->_id)->where("state", PrometheusCheck::FIRE)->first();
                if ($prometheusAlert && $prometheusAlert->state == PrometheusCheck::FIRE) {
                    $prometheusAlert->state = PrometheusCheck::RESOLVED;
                    $prometheusAlert->save();
                    $prometheusAlert->createHistory();
                    $sendResolve = true;
                }
                break;
            case AlertRuleType::ELASTIC:
                $check = ElasticCheck::where("alertRuleId", $alert->_id)->where("state", ElasticCheck::FIRE)->first();
                if ($check && $check->state == ElasticCheck::FIRE) {
                    $check->state = ElasticCheck::RESOLVED;
                    $check->save();

                    ElasticHistory::create([
                        "alertRuleId" => $alert->_id,
                        "alertRuleName" => $alert->name,
                        "dataSourceId" => $alert->dataSourceId,
                        "dataviewName" => $alert->dataviewName,
                        "dataviewTitle" => $alert->dataviewTitle,
                        "queryString" => $alert->queryString,
                        "conditionType" => $alert->conditionType,
                        "minutes" => $alert->minutes,
                        "countDocument" => $alert->countDocument,
                        "currentCountDocument" => -1,
                        "state" => ElasticCheck::RESOLVED,
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
                }
                break;

            case AlertRuleType::SPLUNK:
            case AlertRuleType::NOTIFICATION:
            case AlertRuleType::METABASE:
                break;


        }
        $alert->removeAcknowledge();
        if ($sendResolve) {
            SendNotifyService::CreateNotify(SendNotifyJob::RESOLVED_MANUALLY, $alert, $alert->_id);
        }
        return ['status' => true];
    }


    public function Delete(Request $request)
    {

        $alert = AlertRule::where('_id', $request->id)->firstOrFail();
        $user = \Auth::user();

        $this->alertRuleService->deleteForUser($user, $alert);

        return response()->json(['status' => true]);
//        return redirect()->route('role.index');
    }

    public function AllHistory(Request $request)
    {
        return AlertRuleService::GetAllHistory($request);

    }

    public function History(Request $request, $id)
    {
        $perPage = $request->perPage ?? 50;

        $alert = AlertRule::where("_id", $id)->firstOrFail();
        $userIds = [];
        if (!empty($alert->userIds)) {
            $userIds = $alert->userIds;
        }
        $userIds[] = $alert->userId;

        if (!(Auth::user()->isAdmin() || in_array(Auth::user()->_id, $userIds))) {
            abort(403);
        }

        switch ($alert->type) {
            case AlertRuleType::PMM:
            case AlertRuleType::GRAFANA:
                $data = GrafanaWebhookAlert::where("alertRuleId", $id)->latest();
                break;

            case AlertRuleType::PROMETHEUS:
                $data = PrometheusHistory::where("alertRuleId", $id)->latest();
                break;

            case AlertRuleType::SENTRY:
                $data = SentryWebhookAlert::where("alertRuleId", $id)->latest();
                break;
            case AlertRuleType::SPLUNK:
                $data = SplunkWebhookAlert::where("alertRuleId", $id)->latest();
                break;

            case AlertRuleType::METABASE:
                $data = MetabaseWebhookAlert::where("alertRuleId", $id)->latest();
                break;

            case AlertRuleType::ZABBIX:
                $data = ZabbixWebhookAlert::where("alertRuleId", $id)->latest();
                break;

            case AlertRuleType::API:
            case AlertRuleType::NOTIFICATION:
                $data = ApiAlertHistory::where("alertRuleId", $id)->latest();
                break;

            case AlertRuleType::HEALTH:
                $data = HealthHistory::where("alertRuleId", $id)->latest();
                break;

            case AlertRuleType::ELASTIC:
                $data = ElasticHistory::where("alertRuleId", $id)->latest();
                break;
            default:
                abort(404);
        }
        if ($request->has("from") && !empty($request->from)) {
            $date = Carbon::createFromFormat("Y-m-d H:i", $request->from);
            $data = $data->where("createdAt", ">=", $date->toDateTime());
        }
        if ($request->has("to") && !empty($request->to)) {
            $date = Carbon::createFromFormat("Y-m-d H:i", $request->to);
            $data = $data->where("createdAt", "<=", $date->toDateTime());
        }
        $data = $data->paginate($perPage)->toArray();

        $arrayData = $data["data"];
        foreach ($arrayData as &$item) {
            $item["updatedAt"] = Jalalian::fromCarbon(Carbon::parse($item["updatedAt"])->setTimezone('Asia/Tehran'))->format('Y/m/d H:i:s');
            $item["createdAt"] = Jalalian::fromCarbon(Carbon::parse($item["createdAt"])->setTimezone('Asia/Tehran'))->format('Y/m/d H:i:s');
        }
        $data['data'] = $arrayData;

        return response()->json($data);
    }

    public function FiredAlerts($id)
    {
        // TODO check access alert
        return $this->alertRuleService->firedAlerts($id);
    }


}
