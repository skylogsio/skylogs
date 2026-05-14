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
    /**
     * Stable id for one Grafana/Alertmanager alert series (same alertname, different labels).
     */
    public static function legacyGrafanaAlertInstanceKey(array $alert): string
    {
        $labels = $alert['labels'] ?? [];
        if (is_array($labels)) {
            ksort($labels);
        }

        $labelsEncoded = is_array($labels) ? json_encode($labels) : '';

        return 'legacy:'.sha1($labelsEncoded.'|'.($alert['generatorURL'] ?? '').'|'.($alert['startsAt'] ?? ''));
    }

    public static function grafanaAlertInstanceKey(array $alert): string
    {
        if (! empty($alert['fingerprint'])) {
            return (string) $alert['fingerprint'];
        }

        return self::legacyGrafanaAlertInstanceKey($alert);
    }

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

    public static function CheckMatchedAlerts($alerts, $alertRules): array
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
                        'dataSourceAlertName' => $alert['labels']['alertname'] ?? '',
                        'labels' => $alert['labels'] ?? [],
                        'annotations' => $alert['annotations'] ?? [],
                        'alertRuleId' => $alertRule->_id,
                        'status' => $alert['status'],
                        'startsAt' => $alert['startsAt'] ?? '',
                        'endsAt' => $alert['endsAt'] ?? '',
                        'generatorURL' => $alert['generatorURL'] ?? '',
                        'orgId' => $alert['orgId'] ?? null,
                        'fingerprint' => $alert['fingerprint'] ?? null,
                        'instanceKey' => self::grafanaAlertInstanceKey($alert),

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
            $alertRule = $model->alertRule;
            if (! $alertRule) {
                continue;
            }
            $model->save();
            self::updateAlertRuleStatus($alertRule, $alerts);
            SendNotifyService::CreateNotify(SendNotifyJob::GRAFANA_WEBHOOK, $model, $alertRule->_id);

        }

    }

    public static function updateAlertRuleStatus($alertRule, array $alerts): void
    {
        if (! $alertRule || empty($alertRule->_id)) {
            return;
        }

        $check = GrafanaCheck::firstOrCreate([
            'alertRuleId' => $alertRule->_id,
        ], [
            'alertRuleId' => $alertRule->_id,
            'alerts' => [],
            'state' => GrafanaWebhookAlert::RESOLVED,
        ]);

        $byKey = collect($check->alerts ?? [])
            ->map(fn ($stored) => is_array($stored) ? $stored : (array) $stored)
            ->keyBy(fn (array $stored) => self::grafanaAlertInstanceKey($stored));

        foreach ($alerts as $incoming) {
            $incoming = is_array($incoming) ? $incoming : (array) $incoming;
            $incoming = self::normalizeStoredGrafanaAlert($incoming);
            $key = self::grafanaAlertInstanceKey($incoming);
            $status = $incoming['status'] ?? '';

            if ($status === GrafanaWebhookAlert::RESOLVED) {
                $byKey->forget($key);
                if (! empty($incoming['fingerprint'])) {
                    $legacyKey = self::legacyGrafanaAlertInstanceKey($incoming);
                    if ($legacyKey !== $key) {
                        $byKey->forget($legacyKey);
                    }
                }

                continue;
            }

            if ($status !== GrafanaWebhookAlert::FIRING) {
                continue;
            }

            if (! empty($incoming['fingerprint'])) {
                $incomingLegacy = self::legacyGrafanaAlertInstanceKey($incoming);
                foreach ($byKey->keys() as $existingKey) {
                    $stored = $byKey->get($existingKey);
                    if ($existingKey === $key) {
                        continue;
                    }
                    if (! empty($stored['fingerprint'] ?? null)) {
                        continue;
                    }
                    if (self::legacyGrafanaAlertInstanceKey($stored) === $incomingLegacy) {
                        $byKey->forget($existingKey);
                    }
                }
            }

            $byKey->put($key, $incoming);
        }

        $checkAlerts = $byKey->values();

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

    /**
     * Ensure stored alerts always carry fingerprint + instanceKey for consistent merging.
     */
    private static function normalizeStoredGrafanaAlert(array $alert): array
    {
        $alert['fingerprint'] = $alert['fingerprint'] ?? null;
        $alert['instanceKey'] = $alert['instanceKey'] ?? self::grafanaAlertInstanceKey($alert);

        return $alert;
    }
}
