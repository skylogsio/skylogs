<?php

namespace App\Services;

use App\Enums\DataSourceType;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\ZabbixCheck;
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
        return collect([
            "Not classified",
            "Information",
            "Warning",
            "Average",
            "High",
            "Disaster"
        ]);
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

                if (!empty($rule['severity']) && !in_array($data['event_severity'], $rule['severity'])) {
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

                    $check = ZabbixCheck::firstOrCreate([
                        "alertRuleId" => $includedAlert->id,
                    ], [
                        "alertRuleId" => $includedAlert->id,
                        "fireEvents" => []
                    ]);

                    if ($model->event_status == ZabbixWebhookAlert::RESOLVED) {
                        $check->pull("fireEvents", $model->event_id);
                    } elseif ($model->event_status == ZabbixWebhookAlert::PROBLEM) {
                        $check->push("fireEvents", $model->event_id, true);
                    }

                    if(!empty($check->fireEvents)){
                        $includedAlert->fireCount = count($check->fireEvents);
                        $includedAlert->state = AlertRule::CRITICAL;
                        $includedAlert->save();
                    }else{
                        $includedAlert->fireCount = 0;
                        $includedAlert->state = AlertRule::RESOlVED;
                        $includedAlert->save();
                        $includedAlert->removeAcknowledge();
                    }
                }
            }


        } catch (\Exception $e) {
        }


    }
}
