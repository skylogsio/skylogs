<?php

namespace App\Services;

use App\Jobs\SendNotifyJob;
use App\Models\ServiceCheck;

class ServiceCheckService
{
    public static function CheckSentry(ServiceCheck $check)
    {
        try {

            $response = \Http::timeout(5)
                ->withToken($check->api_token)
                ->get($check->getUrl());

            $check->refresh();

            if ($response->status() == 200 || $response->status() == 201) {
                self::ResultCheckProcess($check, true);
            } else {
                throw new \Exception('Not Ok');
            }

        } catch (\Exception $e) {

            self::ResultCheckProcess($check, false);
        }
    }

    public static function CheckZabbix(ServiceCheck $check)
    {
        try {

            $response = \Http::timeout(5)
//                ->withToken($check->api_token)
                ->contentType('application/json-rpc')
                ->post($check->getUrl(), [
                    'jsonrpc' => '2.0',
                    'method' => 'apiinfo.version',
                    'params' => [],
                    'id' => 1,
                ]);

            $check->refresh();

            if ($response->status() == 200 || $response->status() == 201) {
                self::ResultCheckProcess($check, true);
            } else {
                throw new \Exception('Not Ok');
            }

        } catch (\Exception $e) {
            self::ResultCheckProcess($check, false);
        }
    }

    public static function CheckPrometheus(ServiceCheck $check)
    {
        try {

            $pendingRequest = \Http::timeout(5);

            if (! empty($check->basic_auth_username) && ! empty($check->basic_auth_password)) {
                $pendingRequest = $pendingRequest->withBasicAuth($check->basic_auth_username, $check->basic_auth_password);
            }

            $response = $pendingRequest->get($check->getUrl());

            $check->refresh();

            if ($response->status() == 200 || $response->status() == 201) {
                self::ResultCheckProcess($check, true);
            } else {
                throw new \Exception('Not Ok');
            }

        } catch (\Exception $e) {
            self::ResultCheckProcess($check, false);
        }
    }

    public static function CheckPmm(ServiceCheck $check)
    {
        try {

            $response = \Http::timeout(5)
                ->withToken($check->api_token)
                ->get($check->getUrl());

            $check->refresh();

            if ($response->status() == 200 || $response->status() == 201) {
                self::ResultCheckProcess($check, true);
            } else {
                throw new \Exception('Not Ok');
            }

        } catch (\Exception $e) {
            self::ResultCheckProcess($check, false);
        }
    }

    public static function CheckGrafana(ServiceCheck $check)
    {
        try {

            $response = \Http::timeout(5)
                ->withToken($check->api_token)
                ->get($check->getUrl());

            $check->refresh();

            if ($response->status() == 200 || $response->status() == 201) {
                self::ResultCheckProcess($check, true);
            } else {
                throw new \Exception('Not Ok');
            }

        } catch (\Exception $e) {
            self::ResultCheckProcess($check, false);
        }
    }

    public static function CheckElastic(ServiceCheck $check)
    {
        try {

            $response = \Http::timeout(5)
                ->withToken($check->api_token)
                ->get($check->getUrl());

            $check->refresh();

            if ($response->status() == 200 || $response->status() == 201) {
                self::ResultCheckProcess($check, true);
            } else {
                throw new \Exception('Not Ok');
            }

        } catch (\Exception $e) {
            self::ResultCheckProcess($check, false);
        }
    }

    public static function ResultCheckProcess(ServiceCheck $check, bool $isOK)
    {
        if ($isOK) {

            if ($check->counter != 0) {
                $check->counter -= 1;
                if ($check->counter < $check->threshold_up && $check->state == ServiceCheck::DOWN) {

                    $check->state = ServiceCheck::UP;
                    $check->notifyAt = time();
                    $check->save();
                    SendNotifyService::CreateNotify(SendNotifyJob::HEALTH_CHECK, $check, $check->alertRuleId);

                    /*        HealthHistory::create(
                        [
                            "alertRuleId" => $this->alert->_id,
                            "alertRuleName" => $this->alert->alertname,
                            "url" => $this->alert->url,
                            "threshold_down" => $this->alert->threshold_down,
                            "threshold_up" => $this->alert->threshold_up,
                            "state" => ServiceCheck::UP,
                            "counter" => 0
                        ]
                    );*/

                } else {
                    $check->save();
                }
            }

        } else {

            if ($check->counter != $check->threshold_down) {
                if ($check->counter < $check->threshold_down) {
                    $check->counter += 1;
                }
                if ($check->counter >= $check->threshold_down && $check->state == ServiceCheck::UP) {
                    $check->state = ServiceCheck::DOWN;
                    $check->notifyAt = time();
                    $check->save();
                    SendNotifyService::CreateNotify(SendNotifyJob::HEALTH_CHECK, $check, $check->alertRuleId);

                    /*    HealthHistory::create(
                            [
                                "alertRuleId" => $this->alert->_id,
                                "alertRuleName" => $this->alert->alertname,
                                "url" => $this->alert->url,
                                "threshold_down" => $this->alert->threshold_down,
                                "threshold_up" => $this->alert->threshold_up,
                                "state" => ServiceCheck::DOWN,
                                "counter" => $check->counter
                            ]
                        );*/

                } else {
                    $check->save();
                }
            }
        }
    }
}
