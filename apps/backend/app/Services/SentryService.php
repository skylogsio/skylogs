<?php

namespace App\Services;

use App\Helpers\Utilities;
use App\Models\AlertRule;
use App\Models\PrometheusCheck;
use App\Models\Service;
use App\Utility\Constants;
use Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

class SentryService
{
    private static function GetAllOfAnEndpoint($urlEndpoint, $names = [])
    {
        $sentryAll = Service::where('type', Constants::SENTRY);

        if (! empty($names)) {
            $sentryAll = $sentryAll->whereIn('name', $names);
        }

        $sentryAll = $sentryAll->get()->keyBy('_id')->toArray();
        $result = collect();
        $responses = [];
        if (! empty($sentryAll)) {

            $responses = Http::pool(function (Pool $pool) use ($sentryAll, $urlEndpoint) {
                $result = [];
                foreach ($sentryAll as $sentry) {

                    $request = $pool->as($sentry['_id'])->acceptJson();
                    $request->withToken($sentry['api_token']);
                    $result[$sentry['_id']] = $request->get($sentry['url'].$urlEndpoint);
                }

                return $result;
            });
        }

        foreach ($responses as $sentryId => $response) {
            try {

                if (! ($response instanceof Response && $response->ok())) {
                    continue;
                }
                $res = $response->json();

                $var = $sentryAll[$sentryId];

                $result[$sentryId] = [
                    'result' => $res,
                    ...$var,
                ];

            } catch (\Exception $e) {

            }

        }

        return $result;
    }

    public static function getIssueRules($instance = null): array
    {
        if (empty($instance)) {

            return self::getAllRules();
        } else {
            return self::getRulesInstance($instance);
        }

    }

    public static function GetProjects($names = [])
    {
        $projects = self::GetAllOfAnEndpoint('/api/0/projects/', $names);

        return $projects;
    }

    private static function getAllRules($names = [])
    {
        $sentryProjects = self::GetProjects($names);

        $alerts = [];
        $responses = [];
        if ($sentryProjects->isNotEmpty()) {

            $responses = Http::pool(function (Pool $pool) use ($sentryProjects) {
                $result = [];
                foreach ($sentryProjects as $sentry) {

                    foreach ($sentry['result'] as $project) {
                        $request = $pool->as($sentry['_id'].'%'.$project['slug'])->acceptJson();
                        $request->withToken($sentry['api_token']);
                        $result[$sentry['_id'].'%'.$project['slug']] = $request->get($sentry['url'].'/api/0/projects/sentry/'.$project['slug'].'/rules/');
                    }
                }

                return $result;
            });

            //            dd($responses);
            foreach ($responses as $name => $response) {

                try {

                    [$sentryId, $projectSlug] = explode('%', $name);
                    //                    dd($response);
                    if (! ($response instanceof Response && $response->ok())) {
                        continue;
                    }

                    $res = $response->json();
                    $sentry = $sentryProjects->where('_id', $sentryId)->first();
                    if ($sentry) {
                        $projects = collect($sentry['result']);
                        $project = $projects->where('slug', $projectSlug)->first();

                        if ($project) {
                            // Update the project field
                            $project['issueAlerts'] = $res;

                            // Replace the project in the $projects collection
                            $projects = $projects->map(function ($item) use ($project) {
                                return $item['slug'] === $project['slug'] ? $project : $item;
                            });

                            // Update the 'result' in the original $sentry
                            $sentry['result'] = $projects->toArray();

                            // Now you need to update $sentryProjects
                            // Update the $sentryProjects collection with the modified $sentry
                            $sentryProjects = $sentryProjects->map(function ($item) use ($sentry, $sentryId) {
                                return $item['_id'] === $sentryId ? $sentry : $item;
                            });
                        }
                    }
                    //                    foreach ($sentryProjects as &$sentry) {
                    //                        if($sentryId != $sentry['_id']) continue;
                    //                        foreach ($sentry["result"] as &$project) {
                    //                            if($project['slug'] != $projectSlug) continue;
                    //                            $project['issueAlerts'] = $res;
                    //                            ds($res);
                    //                            break 2;
                    //                        }
                    //                        ds($sentry);
                    //                    }

                } catch (\Exception $e) {

                }

            }

            $sentryProjects = $sentryProjects->map(function ($sentry) {
                $sentry['result'] = collect($sentry['result'])->filter(function ($item) {
                    //                    ds($item['issueAlerts']);
                    return ! empty($item['issueAlerts']);
                })->toArray();

                return $sentry;
            });
        }

        return $sentryProjects->toArray();
    }

    private static function getRulesInstance($instance)
    {
        $pro = PrometheusInstance::where('name', $instance)->first();
        $alerts = [];

        try {

            $request = \Http::acceptJson();
            if (! empty($pro->username) && ! empty($pro->password)) {
                $request = $request->withBasicAuth($pro->username, $pro->password);
            }
            $response = $request->get($pro->getRulesUrl())->json();

            $ruleArr = $response['data']['groups'];
            foreach ($ruleArr as $group) {

                foreach ($group['rules'] as $rule) {
                    $model = new AlertRulePrometheus;
                    $model->instance = $pro->name;
                    $model->name = $rule['name'];
                    $model->queryString = $rule['query'];
                    $model->duration = $rule['duration'] ?? '';
                    $model->severity = empty($rule['labels']) ? '' : (empty($rule['labels']['severity']) ? '' : $rule['labels']['severity']);
                    $alerts[] = $model;
                }
            }

        } catch (\Exception $e) {

        }

        return $alerts;
    }

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

    public static function CheckPrometheusFiredAlerts($alerts, $alertRules): array
    {

        $fireAlertsByRule = [];
        foreach ($alerts as $alert) {
            foreach ($alertRules as $alertRule) {
                $isMatch = true;
                $matchLabels = [];
                $matchAnnotations = [];
                if (empty($alertRule['queryType']) || $alertRule['queryType'] == AlertRule::DYNAMIC_QUERY_TYPE) {
                    $alertRuleInstanceArray = is_array($alertRule['instance']) ? $alertRule['instance'] : [$alertRule['instance']];

                    if (in_array($alert['instance'], $alertRuleInstanceArray) && $alert['labels']['alertname'] == $alertRule['prometheus_alertname']) {

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

                    if (! empty($alertRule->prometheus_query_object)) {
                        $matchedFilterResult = self::CheckAlertFilter($alert, $alertRule->prometheus_query_object);
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
                        'instance' => $alert['instance'],
                        'alertname' => $alertRule->alertname,
                        'prometheus_alertname' => $alert['labels']['alertname'],
                        'labels' => $alert['labels'],
                        'annotations' => $alert['annotations'],
                        'alert_rule_id' => $alertRule->_id,
                        //                        "state" => PrometheusCheck::FIRE,
                    ];

                }

            }
        }

        return $fireAlertsByRule;

    }

    public static function CheckAlerts($alertRules, $prometheusFiredAlerts) {}

    public static function CleanChecks() {}
}
