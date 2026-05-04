<?php

namespace App\Http\Controllers\V1\AlertRule;

use App\Enums\AlertRuleType;
use App\Enums\DataSourceType;
use App\Http\Controllers\Controller;
use App\Models\DataSource\DataSource;
use App\Models\User;
use App\Services\EndpointService;
use App\Services\GrafanaInstanceService;
use App\Services\PrometheusInstanceService;
use App\Services\ZabbixService;
use Illuminate\Http\Request;

class CreateDataController extends Controller
{
    public function __construct(
        protected PrometheusInstanceService $prometheusInstanceService,
        protected GrafanaInstanceService $grafanaInstanceService,
        protected ZabbixService $zabbixService,
    ) {}

    public function CreateData(Request $request)
    {

        $adminUserId = User::where('username', 'admin')->first()->_id;

        $endpoints = app(EndpointService::class)->selectableUserEndpoint(auth()->user());
        $users = User::whereNotIn('_id', [$adminUserId, \Auth::id()])->get();

        return response()->json(
            compact(
                'endpoints',
                'users'
            )
        );

    }

    public function DataSources(Request $request, $type)
    {
        $type = DataSourceType::tryFrom($type);
        $dataSources = DataSource::where('type', $type)->get();

        $result = [];
        foreach ($dataSources as $dataSource) {
            $result[] = [
                'name' => $dataSource->name,
                'id' => $dataSource->id,
            ];
        }

        return response()->json($result);

    }

    public function Rules(Request $request)
    {

        $type = AlertRuleType::tryFrom($request->input('type'));
        $rules = match ($type) {
            AlertRuleType::PROMETHEUS => $this->prometheusInstanceService->getRules($request->dataSourceId),
            AlertRuleType::GRAFANA => $this->grafanaInstanceService->alertRulesName($request->dataSourceId),
            default => [],
        };

        return response()->json($rules);
    }

    public function Labels(Request $request)
    {
        return response()->json($this->prometheusInstanceService->getLabels());
    }

    public function LabelValues(Request $request, $label)
    {
        return response()->json($this->prometheusInstanceService->getLabelValues($label));
    }

    public function ZabbixData()
    {
        $hosts = $this->zabbixService->getHosts();
        $actions = $this->zabbixService->getActions();

        $severities = $this->zabbixService->getSeverities();

        return response()->json(compact('hosts', 'actions', 'severities'));
    }
}
