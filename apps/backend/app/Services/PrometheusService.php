<?php

namespace App\Services;

use App\Helpers\Constants;
use App\Helpers\Utilities;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\PrometheusCheck;

class PrometheusService
{
    /**
     * @throws \Exception
     */
    public static function CheckAlertFilter($alert, $query): bool
    {

        switch ($query['token']['type']) {
            case Constants::LITERAL:
                [$key, $patterns] = explode(':', $query['token']['literal']);
                $key = trim($key);
                $patterns = trim($patterns);
                if ($key == 'prometheus_instance') {
                    return Utilities::CheckPatternsString($patterns, $alert['instance']);
                } elseif ((! empty($alert['labels'][$key]) && Utilities::CheckPatternsString($patterns, $alert['labels'][$key]))) {
                    return true;
                } elseif ((! empty($alert['annotations'][$key]) && Utilities::CheckPatternsString($patterns, $alert['annotations'][$key]))) {
                    return true;
                }

                return false;
            case Constants::AND:
                $right = self::CheckAlertFilter($alert, $query['right']);
                $left = self::CheckAlertFilter($alert, $query['left']);

                return $right && $left;
            case Constants::OR:
                $right = self::CheckAlertFilter($alert, $query['right']);
                $left = self::CheckAlertFilter($alert, $query['left']);

                return $right || $left;
            case Constants::XOR:
                $right = self::CheckAlertFilter($alert, $query['right']);
                $left = self::CheckAlertFilter($alert, $query['left']);

                return $right xor $left;
            case Constants::NOT:
                return ! self::CheckAlertFilter($alert, $query['right']);
        }
        throw new \Exception('invalid token type');
    }

    public function CheckPrometheusFiredAlerts($alerts, $alertRules): array
    {

        $fireAlertsByRule = [];
        foreach ($alerts as $alert) {
            foreach ($alertRules as $alertRule) {
                $isMatch = true;
                $matchLabels = [];
                $matchAnnotations = [];
                if (empty($alertRule['queryType']) || $alertRule['queryType'] == AlertRule::DYNAMIC_QUERY_TYPE) {
                    $alertRuleDataSourcesArray = is_array($alertRule['dataSourceIds']) ? $alertRule['dataSourceIds'] : [$alertRule['dataSourceIds']];

                    if (empty($alertRule['dataSourceIds']) || in_array($alert['dataSourceId'], $alertRuleDataSourcesArray)) {

                        if (! empty($alertRule['dataSourceAlertName']) && $alert['labels']['alertname'] != $alertRule['dataSourceAlertName']) {
                            $isMatch = false;
                        }

                        if (! empty($alertRule->extraField)) {
                            foreach ($alertRule->extraField as $key => $patterns) {
                                if ((! empty($alert['labels'][$key]) && Utilities::CheckPatternsString($patterns, $alert['labels'][$key]))) {
                                    $matchLabels[$key] = $patterns;
                                } elseif ((! empty($alert['annotations'][$key]) && Utilities::CheckPatternsString($patterns, $alert['annotations'][$key]))) {
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

                    if (! empty($alertRule->queryObject)) {
                        $matchedFilterResult = self::CheckAlertFilter($alert, $alertRule->queryObject);
                        if (! $matchedFilterResult) {
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
                        'dataSourceId' => $alert['dataSourceId'],
                        'dataSourceName' => $alert['dataSourceName'],
                        'alertRuleName' => $alertRule->name,
                        'dataSourceAlertName' => $alert['labels']['alertname'],
                        'labels' => $alert['labels'],
                        'annotations' => $alert['annotations'],
                        'alertRuleId' => $alertRule->_id,
                        //                        "state" => PrometheusCheck::FIRE,
                    ];

                }

            }
        }

        return $fireAlertsByRule;

    }

    public function CheckAlerts($alertRules, $prometheusFiredAlerts)
    {
        self::CleanChecks();
        foreach ($alertRules as $alertRule) {
            $check = PrometheusCheck::firstOrCreate([
                'alertRuleId' => $alertRule->_id,
            ], [
                'alerts' => [],
                'state' => PrometheusCheck::RESOLVED,
            ]);

            $newFiredAlertsArray = collect();
            $resolvedAlertsArray = collect();
            $commonAlertsArray = collect();
            $updatedAlertsArray = collect();
            $prometheusAlerts = empty($prometheusFiredAlerts[$alertRule->id]) ? [] : $prometheusFiredAlerts[$alertRule->id];
            foreach ($prometheusAlerts as $prometheusAlert) {
                $isExists = false;
                foreach ($check->alerts as $alert) {
                    if ($prometheusAlert['labels'] == $alert['labels']) {
                        $isExists = true;
                        break;
                    }
                }

                if ($isExists) {
                    $commonAlertsArray->add($prometheusAlert);
                } else {
                    $newFiredAlertsArray->add($prometheusAlert);
                }
            }

            if (collect($prometheusAlerts)->count() == $commonAlertsArray->count() && $commonAlertsArray->count() == collect($check->alerts)->count()) {
                continue;
            }

            if ($commonAlertsArray->count() != collect($check->alerts)->count()) {
                foreach ($check->alerts as $savedAlert) {
                    $isResolved = true;
                    foreach ($commonAlertsArray as $alert) {
                        if ($savedAlert['labels'] == $alert['labels']) {
                            $isResolved = false;
                            break;
                        }
                    }
                    if ($isResolved) {
                        $resolvedAlertsArray->add($savedAlert);
                    }
                }
            }

            //            $updatedAlertsArray = $commonAlertsArray->clone();

            foreach ($commonAlertsArray as $commonAlert) {
                $commonAlert['skylogsStatus'] = empty($commonAlert['skylogsStatus']) ? PrometheusCheck::FIRE : $commonAlert['skylogsStatus'];
                $updatedAlertsArray->add($commonAlert);
            }

            foreach ($newFiredAlertsArray as $newFiredAlert) {
                $newFiredAlert['skylogsStatus'] = PrometheusCheck::FIRE;
                $updatedAlertsArray->add($newFiredAlert);
            }

            foreach ($resolvedAlertsArray as $resolvedAlert) {
                $resolvedAlert['skylogsStatus'] = PrometheusCheck::RESOLVED;
                $updatedAlertsArray->add($resolvedAlert);
            }

            $firedAlerts = $updatedAlertsArray->filter(function ($alert) {
                return empty($alert['skylogsStatus']) || $alert['skylogsStatus'] == PrometheusCheck::FIRE;
            });

            if ($updatedAlertsArray->isEmpty()) {
                continue;
            }

            $check->alerts = $updatedAlertsArray->toArray();

            $alertRule = $check->alertRule;
            if ($firedAlerts->isEmpty()) {
                $check->state = PrometheusCheck::RESOLVED;
                $alertRule->state = AlertRule::RESOlVED;
                $alertRule->fireCount = 0;
            } else {
                $check->state = PrometheusCheck::FIRE;
                $alertRule->state = AlertRule::CRITICAL;
                $alertRule->fireCount = $firedAlerts->count();
            }

            if ($check->state == PrometheusCheck::RESOLVED && empty($check->alerts)) {
                continue;
            }

            $check->save();
            $alertRule->save();
            if ($alertRule->state == AlertRule::RESOlVED) {
                $alertRule->removeAcknowledge();
            }
            $check->createHistory();

            //            $updatedAlertsArray->isNotEmpty() ? AlertRule::
            //            \Bus::chain([
            //                new SendNotifyJob(SendNotifyJob::PROMETHEUS_FIRE, $check),
            //                new RefreshPrometheusCheckJob,
            //            ])->dispatch();
            SendNotifyService::CreateNotify(SendNotifyJob::PROMETHEUS_FIRE, $check, $alertRule->_id);

        }

    }

    public static function CleanChecks()
    {
        $checks = PrometheusCheck::get();
        foreach ($checks as $check) {
            $alerts = collect($check->alerts);

            if ($alerts->isEmpty()) {
                continue;
            }

            $onlyFiredAlerts = $alerts->filter(function ($alert) {
                return empty($alert['skylogsStatus']) || $alert['skylogsStatus'] == PrometheusCheck::FIRE;
            });

            if ($onlyFiredAlerts->count() == $alerts->count()) {
                continue;
            }

            $check->alerts = $onlyFiredAlerts->toArray();
            $check->save();
        }
    }
}
