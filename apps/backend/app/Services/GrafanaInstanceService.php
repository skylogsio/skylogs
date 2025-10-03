<?php

namespace App\Services;

use App\Enums\DataSourceType;
use App\Models\AlertRuleGrafana;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class GrafanaInstanceService
{


    public function __construct(protected DataSourceService $dataSourceService){}

    protected function resolveDataSources(?int $dataSourceId): Collection
    {
        if ($dataSourceId) {
            $source = $this->dataSourceService->get(DataSourceType::GRAFANA)->get($dataSourceId);
            return $source ? collect([$dataSourceId => $source]) : collect();
        }

        return $this->dataSourceService->get(DataSourceType::GRAFANA);
    }

    public function alertRulesName(?string $dataSourceId = null): Collection
    {
        $dataSources = $this->resolveDataSources($dataSourceId);

        if ($dataSources->isEmpty()) {
            return collect();
        }

        $dataSources = $this->fetchOrganizations($dataSources);

        return $this->fetchRules($dataSources)->pluck('title')->unique();

    }

    private function fetchRules($dataSources)
    {


        $requestsData = [];
        $rulesResponses = Http::pool(function (Pool $pool) use ($dataSources,&$requestsData) {

            $requests = [];

            foreach ($dataSources as $dataSource) {


                if (!empty($dataSource->orgs)) {

                    foreach ($dataSource->orgs as $org) {
                        $request = $pool->acceptJson();

                        if (!empty($dataSource->username) && !empty($dataSource->password)) {
                            $request = $request->withBasicAuth($dataSource->username, $dataSource->password);
                        }
                        $requests[] = $request
                            ->withHeader("X-Grafana-Org-Id", $org['id'])
                            ->get($dataSource->grafanaAlertRulesUrl());
                        $dataSource->org = $org;
                        $requestsData[] = $dataSource;
                    }
                } else {
                    $request = $pool->acceptJson();

                    if (!empty($dataSource->username) && !empty($dataSource->password)) {
                        $request = $request->withBasicAuth($dataSource->username, $dataSource->password);
                    }
                    $requests[] = $request->get($dataSource->grafanaAlertRulesUrl());
                    $requestsData[] = $dataSource;
                }

            }

            return $requests;
        });

        $alerts = collect();
        foreach ($rulesResponses as $index => $ruleResponse) {
            $dataSource = $requestsData[$index] ?? null;
            if (!($ruleResponse instanceof Response && $ruleResponse->ok())) continue;

            $response = $ruleResponse->json();
            try {
                foreach ($response as $alert) {
//                    ds($alert);
                    $model = new AlertRuleGrafana();
                    $model->dataSourceId = $dataSource->id;
                    $model->dataSourceName = $dataSource->name;
                    $model->organizationId = $dataSource->org['id'] ?? null;
                    $model->organizationName = $dataSource->org['name'] ?? "";
                    $model->ruleGroup = $alert["ruleGroup"] ?? "";
                    $model->title = $alert["title"];
                    $model->annotations = $alert['annotations'] ?? [];
                    $model->labels = $alert['labels'] ?? [];
                    $alerts[] = $model;
                }
            } catch  (\Exception $e) {
            }

        }

        return $alerts;
    }


    /**
     * @param Collection $dataSources
     * @return void
     */
    public function fetchOrganizations(Collection &$dataSources): Collection
    {
        $orgResponses = Http::pool(function (Pool $pool) use ($dataSources) {
            $result = [];
            foreach ($dataSources as $dataSource) {
                $request = $pool->as($dataSource->id)->acceptJson();

                if (!empty($dataSource->username) && !empty($dataSource->password)) {
                    $request = $request->withBasicAuth($dataSource->username, $dataSource->password);
                }


                $result[] = $request->get($dataSource->grafanaOrganizationsUrl());
            }
            return $result;
        });
        foreach ($orgResponses as $dataSourceId => $orgResponse) {

            if (!($orgResponse instanceof Response && $orgResponse->ok())) continue;

            $response = $orgResponse->json();

            $dataSources->get($dataSourceId)->orgs = $response;

        }
        return $dataSources;


    }

}
