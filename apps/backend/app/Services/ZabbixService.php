<?php

namespace App\Services;

use App\Enums\DataSourceType;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\ZabbixWebhookAlert;
use Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;


class ZabbixService
{
    public function __construct(protected DataSourceService $dataSourceService)
    {
    }

    public function getHosts()
    {
        $allZabbix = $this->dataSourceService->get(DataSourceType::ZABBIX);
        $resultHosts = [];
        if ($allZabbix->isNotEmpty()) {

            $responses = Http::pool(function (Pool $pool) use ($allZabbix) {
                $result = [];
                foreach ($allZabbix as $zabbix) {

                    $request = $pool->as($zabbix->name)->acceptJson();
                    $body = [
                        "auth" => $zabbix->apiToken,
                        "jsonrpc" => "2.0",
                        "method" => "host.get",
                        "params" => new \stdClass(),
                        "id" => 1,
                    ];
                    $result[] = $request->withOptions([
                        'verify' => false
                    ])->post($zabbix->zabbixApiUrl(), $body);
                }

                return $result;
            });

            foreach ($responses as $name => $response) {

                try {

                    if (!($response instanceof Response && $response->ok())) continue;
                    $response = $response->json();

                    $data = $response['result'];
                    foreach ($data as $host) {
                        $resultHosts[] = $host['name'];
                    }

                } catch (\Exception $e) {

                }

            }

        }

        return $resultHosts;
    }

    public function getActions()
    {
        $allZabbix = $this->dataSourceService->get(DataSourceType::ZABBIX);
        $resultHosts = [];
        if ($allZabbix->isNotEmpty()) {

            $responses = Http::pool(function (Pool $pool) use ($allZabbix) {
                $result = [];
                foreach ($allZabbix as $zabbix) {

                    $request = $pool->as($zabbix->name)->acceptJson();
                    $body = [
                        "auth" => $zabbix->apiToken,
                        "jsonrpc" => "2.0",
                        "method" => "action.get",
                        "params" => new \stdClass(),
                        "id" => 1,
                    ];
                    $result[] = $request->withOptions([
                        'verify' => false
                    ])->post($zabbix->zabbixApiUrl(), $body);
                }

                return $result;
            });

            foreach ($responses as $name => $response) {

                try {

                    if (!($response instanceof Response && $response->ok())) continue;
                    $response = $response->json();

                    $data = $response['result'];
                    foreach ($data as $host) {
                        $resultHosts[] = $host['name'];
                    }

                } catch (\Exception $e) {

                }

            }

        }
        return $resultHosts;

    }

    public function getSeverities()
    {
        return [
            [
                "key" => "0",
                "value" => "Not classified",
            ],
            [
                "key" => "1",
                "value" => "Information",
            ],
            [
                "key" => "2",
                "value" => "Warning",
            ],
            [
                "key" => "3",
                "value" => "Average",
            ],
            [
                "key" => "4",
                "value" => "High",
            ],
            [
                "key" => "5",
                "value" => "Disaster",
            ],
        ];
    }

    public function checkAlertRules($dataSource, $alertRules, $data)
    {

        try {


            $includedAlerts = collect();
            foreach ($alertRules as $rule) {

                if (!empty($rule['hosts']) && !in_array($data['host_name'], $rule['hosts'])) {
                    continue;
                }

                if (!empty($rule['actions']) && !in_array($data['action_name'], $rule['actions'])) {
                    continue;
                }

                if (!empty($rule['severity']) && $data['event_nseverity'] != $rule['actions']) {
                    continue;
                }

                $includedAlerts[] = $rule;
            }

            if ($includedAlerts->isNotEmpty()) {
                foreach ($includedAlerts as $includedAlert) {

                    $model = new ZabbixWebhookAlert();
                    $model->dataSourceId = $dataSource->id;
                    $model->dataSourceName = $dataSource->name;

                    foreach ($data as $key => $value) {
                        $model->$key = $value;

                    }


                    $model->alertRuleId = $includedAlert->id;
                    $model->alertRuleName = $includedAlert->name;
                    $model->save();
                    SendNotifyService::CreateNotify(SendNotifyJob::ZABBIX_WEBHOOK, $model, $includedAlert->id);

                    $includedAlert->notifyAt = time();
                    $includedAlert->save();

                }
            }


        } catch (\Exception $e) {
        }


    }
}
