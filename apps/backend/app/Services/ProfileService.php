<?php

namespace App\Services;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\Profile\ProfileAsset;

class ProfileService
{

    public function __construct(protected DataSourceService $dataSourceService, protected AlertRuleService $alertRuleService)
    {
    }

    public function createAlertRules(ProfileAsset $profileAsset)
    {

        $config = json_decode($profileAsset->config, true);
        $userId = $profileAsset->ownerId;
        $createdAlerts = collect();
        foreach ($config as $env => $envConfig) {
            foreach ($envConfig as $serviceType => $serviceConfig) {
                foreach ($serviceConfig as $dataSourceToken => $dataSourceConfig) {

                    switch (AlertRuleType::from($serviceType)) {
                        case AlertRuleType::PMM:
                        case AlertRuleType::PROMETHEUS:
                        case AlertRuleType::GRAFANA:
                            $alerts = $this->generateGrafanaPrometheusPmmAlert($serviceType, $userId, $profileAsset->name, $env, $dataSourceToken, $dataSourceConfig);
                            $createdAlerts = $createdAlerts->concat($alerts);
                            break;
                    }


                }
            }

        }

        $createdAlertIds = $createdAlerts->pluck("id")->toArray();
        if (!empty($profileAsset->createdAlertRuleIds)) {
            $needToDelete = collect($profileAsset->createdAlertRuleIds)->reject(function ($alertRuleId) use ($createdAlertIds) {
                return in_array($alertRuleId, $createdAlertIds);
            });
            if (!empty($needToDelete)) {
                AlertRule::whereIn('id', $needToDelete)->get()->each(fn($alert) => $this->alertRuleService->delete($alert));
            }
        }
        $this->alertRuleService->flushCache();
        return $createdAlerts;
    }

    private function generateGrafanaPrometheusPmmAlert($type, $userId, $service, $env, $dataSourceToken, $config)
    {
        $dataSource = $this->dataSourceService->byToken($dataSourceToken);
        $tags = collect($config["tags"])->push($service, $env);
        $resultAlerts = [];
        $commonFields = [
            'type' => $type,
            "dataSourceIds" => [$dataSource->id],
            "queryType" => AlertRule::DYNAMIC_QUERY_TYPE,
            "queryText" => "",

            'tags' => $tags->toArray(),
            "userId" => $userId,
            "silentUserIds" => [],
            "endpointIds" => [],
            "userIds" => [],
        ];
        $commonLabels = collect($config["labels"]);
        if (!empty($config['alertnames'])) {

            foreach ($config['alertnames'] as $alertConfig) {
                if (is_array($alertConfig)) {
                    $alertRuleName = $alertConfig['name'] . "-" . $service . "-" . $env;
                    $dataSourceAlertname = $alertConfig['name'];
                    $extraField = $commonLabels->merge($alertConfig['labels']);
                } else {
                    $alertRuleName = $alertConfig . "-" . $service . "-" . $env;
                    $dataSourceAlertname = $alertConfig;
                    $extraField = $commonLabels->toArray();

                }

                $createData = [
                    ...$commonFields,
                    "name" => $alertRuleName,
                    "dataSourceAlertName" => $dataSourceAlertname,
                    "extraField" => $extraField,
                ];

                $alertRule = AlertRule::firstOrNew([
                    "name" => $alertRuleName,
                ], $createData);

                if ($alertRule->exists) {
                    unset($createData["endpointIds"]);
                    unset($createData["userIds"]);
                    $alertRule->update($createData);
                } else {
                    $alertRule->save();
                }
                $resultAlerts[] = $alertRule;

            }

        } else {
            $alertRuleName = $dataSource->name . "-" . $service . "-" . $env;


            $createData = [
                ...$commonFields,
                "name" => $alertRuleName,
                "dataSourceAlertName" => "",
                "extraField" => $commonLabels->toArray(),
            ];


            $alertRule = AlertRule::firstOrNew([
                "name" => $alertRuleName,
            ], $createData);

            if ($alertRule->exists) {
                unset($createData["endpointIds"]);
                unset($createData["userIds"]);
                $alertRule->update($createData);
            } else {
                $alertRule->save();
            }
            $resultAlerts[] = $alertRule;
        }

        return $resultAlerts;

    }


    public function delete(ProfileAsset $profileAsset)
    {
        if (!empty($profileAsset->createdAlertRuleIds)) {

            AlertRule::whereIn('id', $profileAsset->createdAlertRuleIds)->get()->each(fn($alert) => $this->alertRuleService->delete($alert));

        }
        $this->alertRuleService->flushCache();
    }
}
