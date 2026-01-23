<?php

namespace App\Services;

use App\Helpers\Constants;
use App\Helpers\Utilities;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\GrafanaCheck;
use App\Models\GrafanaWebhookAlert;

class GrafanaService
{
    public static function CheckAlertFilter($alert, $query)
    {

        switch ($query['token']['type']) {
            case Constants::LITERAL:
                [$key, $patterns] = explode(':', $query['token']['literal']);
                $key = trim($key);
                $patterns = trim($patterns);
                if ($key == 'grafana_instance') {
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

    }

    public static function CheckMatchedAlerts( $alerts, $alertRules): array
    {

        $fireAlertsByRule = [];
        foreach ($alerts as $alert) {
            foreach ($alertRules as $alertRule) {
                $isMatch = true;

                if (empty($alertRule['queryType']) || $alertRule['queryType'] == AlertRule::DYNAMIC_QUERY_TYPE) {

                    if (in_array($alert['dataSourceId'], $alertRule['dataSourceIds'])) {

                        if (! empty($alertRule['dataSourceAlertName']) && $alert['labels']['alertname'] != $alertRule['dataSourceAlertName']) {
                            $isMatch = false;
                        }

                        if (! empty($alertRule->extraField)) {
                            foreach ($alertRule->extraField as $key => $patterns) {
                                $value = $alert['labels'][$key] ?? $alert['annotations'][$key] ?? null;

                                if (empty($value) || ! Utilities::CheckPatternsString($patterns, $value)) {
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

                    if (empty($fireAlertsByRule[$alertRule->_id])) {
                        $fireAlertsByRule[$alertRule->_id] = [];
                    }

                    $fireAlertsByRule[$alertRule->_id][] = [
                        'dataSourceId' => $alert['dataSourceId'],
                        'alertRuleName' => $alertRule->name,
                        'dataSourceAlertName' => $alert['labels']['alertname'],
                        'labels' => $alert['labels'],
                        'annotations' => $alert['annotations'],
                        'alertRuleId' => $alertRule->_id,
                        'status' => $alert['status'],
                        'startsAt' => $alert['startsAt'] ?? '',
                        'endsAt' => $alert['endsAt'] ?? '',
                        'generatorURL' => $alert['generatorURL'],
                        'orgId' => $alert['orgId'] ?? null,

                        //                        "state" => $status,
                    ];

                }

            }
        }

        return $fireAlertsByRule;

    }

    public static function SaveMatchedAlerts($dataSource, $webhook, $matchedAlerts)
    {
        $status = $webhook['status'];
        foreach ($matchedAlerts as $alertRuleId => $alerts) {
            $model = new GrafanaWebhookAlert;
            $model->alerts = $alerts;
            $model->dataSourceId = $dataSource->id;
            $model->dataSourceName = $dataSource->name;
            $model->alertRuleId = $alertRuleId;
            $model->status = $status;

            $model->groupLabels = $webhook['groupLabels'] ?? '';
            $model->commonLabels = $webhook['commonLabels'] ?? '';
            $model->commonAnnotations = $webhook['commonAnnotations'] ?? '';
            $model->externalURL = $webhook['externalURL'] ?? '';
            $model->groupKey = $webhook['groupKey'] ?? '';
            $model->truncatedAlerts = $webhook['truncatedAlerts'] ?? '';
            $model->orgId = $webhook['orgId'] ?? '';
            $model->title = $webhook['title'] ?? '';
            $model->message = $webhook['message'] ?? '';
            $grafanaAlertnames = collect($alerts)->map(function ($gAlert) {
                return $gAlert['dataSourceAlertName'];
            })->unique()->toArray();
            $alertRule = $model->alertRule;

            self::updateAlertRuleStatus($alertRule, $alerts,$grafanaAlertnames);
            SendNotifyService::CreateNotify(SendNotifyJob::GRAFANA_WEBHOOK, $model, $alertRule->_id);

        }

    }

    public static function updateAlertRuleStatus($alertRule, $alerts,$grafanaAlertnames)
    {

        $webhookAlerts = collect($alerts)->filter(function ($alert) {
           return $alert['status'] == GrafanaWebhookAlert::FIRING;
        });

        $check = GrafanaCheck::firstOrCreate([
            'alertRuleId' => $alertRule->_id,
        ],[
            'alertRuleId' => $alertRule->_id,
            'alerts' => [],
            'state' => GrafanaWebhookAlert::RESOLVED,
        ]);

        $checkAlerts = collect($check->alerts)->reject(function ($alert) use ($grafanaAlertnames) {
            return in_array($alert['dataSourceAlertName'], $grafanaAlertnames);
        });

        if ($webhookAlerts->isNotEmpty()) {
            foreach ($webhookAlerts as $webhookAlert) {
                $checkAlerts[] = $webhookAlert;
            }
        }

        $fireCount = $checkAlerts->count();

        $check->alerts = $checkAlerts->toArray();
        $check->state = $fireCount == 0 ? GrafanaWebhookAlert::RESOLVED : GrafanaWebhookAlert::FIRING;
        $check->save();

        $alertRuleState = $fireCount == 0 ? AlertRule::RESOlVED : AlertRule::CRITICAL;

        if ($alertRule) {
            $alertRule->state = $alertRuleState;
            $alertRule->fireCount = $fireCount;
            $alertRule->save();
            if ($alertRule->state == AlertRule::RESOlVED) {
                $alertRule->removeAcknowledge();
            }
        }

    }
}
