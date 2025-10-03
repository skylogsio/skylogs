<?php

namespace App\Services;

use App\Enums\HealthAlertType;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\HealthCheck;
use App\Models\HealthHistory;


class HealthService
{


    public function createCheckArray(AlertRule $alert)
    {
        switch ($alert->checkType) {
            case HealthAlertType::AGENT_CLUSTER:
                return [
                    "url" => $alert->url,
                    "threshold" => $alert->threshold,
                    "checkType" => $alert->checkType,
                    "skylogsInstanceId" => $alert->skylogsInstanceId,
                    "agentToken" => $alert->agentToken,
                    "state" => HealthCheck::UP,
                    "counter" => 0
                ];
            case HealthAlertType::SOURCE_CLUSTER:
                return [
                    "url" => $alert->url,
                    "threshold" => $alert->threshold,
                    "checkType" => $alert->checkType,
                    "sourceToken" => $alert->sourceToken,
                    "state" => HealthCheck::UP,
                    "counter" => 0
                ];
            default:
                return [
                    "url" => $alert->url,
                    "threshold" => $alert->threshold,
                    "checkType" => $alert->checkType,
                    "state" => HealthCheck::UP,
                    "counter" => 0
                ];

        }
    }

    public function check(AlertRule $alert)
    {

        $createAlertArray = $this->createCheckArray($alert);
        $check = HealthCheck::firstOrCreate(
            [
                "alertRuleId" => $alert->_id
            ],
            $createAlertArray
        );
        try {

            $pendingRequest = \Http::timeout(5);

            $pendingRequest = match ($alert->checkType) {
                HealthAlertType::SOURCE_CLUSTER => $pendingRequest->withToken($alert->sourceToken),
                HealthAlertType::AGENT_CLUSTER => $pendingRequest->withToken($alert->agentToken)
            };


            $response = $pendingRequest->get($check->url);

            $check->refresh();
            if ($response->status() == 200 || $response->status() == 201) {

                if ($check->counter != 0) {
                    $check->counter -= 1;
                    if ($check->counter == 0 && $check->state == HealthCheck::DOWN) {

                        $check->state = HealthCheck::UP;
                        $check->notifyAt = time();
                        $check->save();
                        $alert->state = AlertRule::RESOlVED;
                        $alert->save();
                        $alert->removeAcknowledge();

                        SendNotifyService::CreateNotify(SendNotifyJob::HEALTH_CHECK, $check, $alert->id);
                        HealthHistory::create(
                            [
                                "alertRuleId" => $alert->_id,
                                "alertRuleName" => $alert->alertname,
                                "url" => $alert->url,
                                "threshold" => $alert->threshold,
                                "state" => HealthCheck::UP,
                                "counter" => 0
                            ]
                        );


                    } else {
                        $check->save();
                    }
                }

            } else {
                throw new \Exception("Not Ok");
            }

        } catch (\Exception $e) {


            if ($check->counter != $check->threshold) {

                if ($check->counter < $check->threshold)
                    $check->counter += 1;

                if ($check->counter >= $check->threshold && $check->state == HealthCheck::UP) {
                    $check->state = HealthCheck::DOWN;
                    $check->notifyAt = time();
                    $check->save();
                    $alert->state = AlertRule::CRITICAL;
                    $alert->save();
                    SendNotifyService::CreateNotify(SendNotifyJob::HEALTH_CHECK, $check, $alert->id);

                    HealthHistory::create(
                        [
                            "alertRuleId" => $alert->_id,
                            "alertRuleName" => $alert->alertname,
                            "checkType" => $alert->checkType,
                            "url" => $alert->url,
                            "threshold" => $alert->threshold,
                            "state" => HealthCheck::DOWN,
                            "counter" => $check->counter
                        ]
                    );


                } else {
                    $check->save();
                }
            }
        }
    }
}
