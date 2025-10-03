<?php

namespace App\Http\Middleware;

use App\Enums\AlertRuleType;
use App\Enums\DataSourceType;
use App\Models\AlertRule;
use App\Services\DataSourceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookAuth
{

    public function __construct(protected DataSourceService $dataSourceService) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if($request->routeIs('webhook.pmm')) {
            $dataSourceType = DataSourceType::PMM;
            $alertRuleType = AlertRuleType::PMM;
        }elseif($request->routeIs('webhook.grafana')) {
            $dataSourceType = DataSourceType::GRAFANA;
            $alertRuleType = AlertRuleType::GRAFANA;
        }elseif($request->routeIs('webhook.sentry')) {
            $dataSourceType = DataSourceType::SENTRY;
            $alertRuleType = AlertRuleType::SENTRY;
        }elseif($request->routeIs('webhook.splunk')) {
            $dataSourceType = DataSourceType::SPLUNK;
            $alertRuleType = AlertRuleType::SPLUNK;
        }elseif($request->routeIs('webhook.zabbix')){
            $dataSourceType = DataSourceType::ZABBIX;
            $alertRuleType = AlertRuleType::ZABBIX;
        }else{
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $token = $request->route('token');
        $dataSource = $this->dataSourceService->get($dataSourceType)
            ->where('webhookToken', $token)
            ->first();
        if (!$dataSource) abort(403);

        $alertRules = AlertRule::where('type', $alertRuleType)
            ->where('dataSourceIds', $dataSource->id)
            ->get();

        if ($alertRules->isEmpty())
            abort(422,'Alert rule not found');


        return $next($request->merge(['alertRules' => $alertRules,'dataSource' => $dataSource]));
    }
}
