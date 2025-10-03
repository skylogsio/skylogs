<?php

namespace App\Services;

use App\Enums\AlertRuleType;
use App\Helpers\Utilities;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\PrometheusCheck;
use App\Helpers\Constants;

class PrometheusService
{

    /**
     * @throws \Exception
     */
    public static function CheckAlertFilter($alert, $query): bool
    {

        switch ($query['token']['type']) {
            case Constants::LITERAL:
                list($key, $patterns) = explode(":", $query['token']['literal']);
                $key = trim($key);
                $patterns = trim($patterns);
                if ($key == "prometheus_instance") {
                    return Utilities::CheckPatternsString($patterns, $alert['instance']);
                } elseif ((!empty($alert['labels'][$key]) && Utilities::CheckPatternsString($patterns, $alert['labels'][$key]))) {
                    return true;
                } elseif ((!empty($alert['annotations'][$key]) && Utilities::CheckPatternsString($patterns, $alert['annotations'][$key]))) {
                    return true;
                }
                return false;
            case Constants::AND:
                $right = self::CheckAlertFilter($alert, $query['right']);
                $left = self::CheckAlertFilter($alert, $query['left']);
                return ($right && $left);
            case Constants::OR:
                $right = self::CheckAlertFilter($alert, $query['right']);
                $left = self::CheckAlertFilter($alert, $query['left']);
                return ($right || $left);
            case Constants::XOR:
                $right = self::CheckAlertFilter($alert, $query['right']);
                $left = self::CheckAlertFilter($alert, $query['left']);
                return ($right xor $left);
            case Constants::NOT:
                return !self::CheckAlertFilter($alert, $query['right']);
        }
        throw new \Exception("invalid token type");
    }

    public function CheckPrometheusFiredAlerts($alerts, $alertRules): array
    {

        $fireAlertsByRule = [];
        foreach ($alerts as $alert) {
            foreach ($alertRules as $alertRule) {
                $isMatch = true;
                $matchLabels = [];
                $matchAnnotations = [];
                if (empty($alertRule["queryType"]) || $alertRule['queryType'] == AlertRule::DYNAMIC_QUERY_TYPE) {
                    $alertRuleDataSourcesArray = is_array($alertRule['dataSourceIds']) ? $alertRule['dataSourceIds'] : [$alertRule['dataSourceIds'],];

                    if (empty($alertRule['dataSourceIds']) || in_array($alert['dataSourceId'], $alertRuleDataSourcesArray)) {

                        if (!empty($alertRule['dataSourceAlertName']) && $alert['labels']['alertname'] != $alertRule['dataSourceAlertName']) {
                            $isMatch = false;
                        }

                        if (!empty($alertRule->extraField)) {
                            foreach ($alertRule->extraField as $key => $patterns) {
                                if ((!empty($alert['labels'][$key]) && Utilities::CheckPatternsString($patterns, $alert['labels'][$key]))) {
                                    $matchLabels[$key] = $patterns;
                                } elseif ((!empty($alert['annotations'][$key]) && Utilities::CheckPatternsString($patterns, $alert['annotations'][$key]))) {
                                    $matchAnnotations[$key] = $patterns;
                                } else {
                                    $isMatch = false;
                                    break;
                                }
                            }
                        }
                    } else {
                        $isMatch = false;
                    }

                } else {
                    // TEXT QUERY

                    if (!empty($alertRule->queryObject)) {
                        $matchedFilterResult = self::CheckAlertFilter($alert, $alertRule->queryObject);
                        if (!$matchedFilterResult) {
                            $isMatch = false;
                        }
                    }


                }


                if ($isMatch) {
                    // check with database checkprometheus

                    if (empty($fireAlertsByRule[$alertRule->_id])) {
                        $fireAlertsByRule[$alertRule->_id] = [];
                    }

                    $fireAlertsByRule[$alertRule->_id][] = [
                        "dataSourceId" => $alert['dataSourceId'],
                        "dataSourceName" => $alert['dataSourceName'],
                        "alertRuleName" => $alertRule->name,
                        "dataSourceAlertName" => $alert['labels']['alertname'],
                        "labels" => $alert['labels'],
                        "annotations" => $alert['annotations'],
                        "alertRuleId" => $alertRule->_id,
//                        "state" => PrometheusCheck::FIRE,
                    ];

                }

            }
        }
        return $fireAlertsByRule;

    }

    public function CheckAlerts($prometheusFiredAlerts)
    {
        $checks = PrometheusCheck::all();
        foreach ($checks as $check) {
            $isStillFired = false;
            foreach ($prometheusFiredAlerts as $alertRuleId => $prometheusAlertRuleFiredAlerts) {
                foreach ($prometheusAlertRuleFiredAlerts as $prometheusFiredAlert) {
                    if ($check->alertRuleId == $alertRuleId &&
                        $check->alert['labels'] == $prometheusFiredAlert['labels']) {
                        $isStillFired = true;
                    }
                }
            }

            if (!$isStillFired) {
                $check->state = PrometheusCheck::RESOLVED;
                $check->save();
                $check->createHistory();

                SendNotifyService::CreateNotify(SendNotifyJob::PROMETHEUS_RESOLVE, $check, $check->alertRuleId);
                $check->delete();
            }
        }


        $checks = PrometheusCheck::all();

        foreach ($prometheusFiredAlerts as $alertRuleId => $prometheusAlertRuleFiredAlerts) {
            foreach ($prometheusAlertRuleFiredAlerts as $prometheusFiredAlert) {
                $isAlreadyFired = false;
                foreach ($checks as $check) {
                    if ($check->alertRuleId == $alertRuleId &&
                        $check->alert['labels'] == $prometheusFiredAlert['labels']) {
                        $isAlreadyFired = true;
                    }
                }

                if (!$isAlreadyFired) {
                    $check = PrometheusCheck::create([
                        'alertRuleId' => $alertRuleId,
                        "dataSourceId" => $prometheusFiredAlert['dataSourceId'],
                        "dataSourceName" => $prometheusFiredAlert['dataSourceName'],
                        'alert' => $prometheusFiredAlert,
                        'state' => PrometheusCheck::FIRE,
                    ]);
                    $check->createHistory();

                    SendNotifyService::CreateNotify(SendNotifyJob::PROMETHEUS_FIRE, $check, $check->alertRuleId);
                }

            }
        }

    }

    public function refreshStatus()
    {
        $alertRules = AlertRule::where('type', AlertRuleType::PROMETHEUS)->get();
        foreach ($alertRules as $alertRule) {

            $count = PrometheusCheck::where('alertRuleId', $alertRule->id)
                ->where("state", PrometheusCheck::FIRE)->count();
            $alertRule->state = $count == 0 ? AlertRule::RESOlVED : AlertRule::CRITICAL;
            $alertRule->fireCount = $count;
            $alertRule->save();
            if ($alertRule->state == AlertRule::RESOlVED)
                $alertRule->removeAcknowledge();
        }
    }
}
