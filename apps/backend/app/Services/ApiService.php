<?php

namespace App\Services;

use App\Enums\AlertRuleType;
use App\Jobs\SendNotifyJob;
use App\Models\AlertInstance;
use App\Models\AlertRule;

class ApiService
{
    public function __construct(protected AlertRuleService $alertRuleService) {}

    public function fireAlert($post): array
    {
        $alertRule = AlertRule::firstWhere('apiToken', $post['apiToken']);

        if (! $alertRule) {
            return [
                'status' => false,
                'message' => "Alertname Doesn't Exists.",
            ];
        }

        $alert = AlertInstance::where('alertRuleId', $alertRule->id)
            ->where('instance', $post['instance'])
            ->first();

        if ($alert) {
            $isCurrentStateResolved = $alert->state == AlertInstance::RESOLVED;

            $alert->state = AlertInstance::FIRE;
            $alert->description = $post['description'] ?? '';
            $alert->summary = $post['summary'] ?? '';

            $alert->touch();
            $alert->save();

            SendNotifyService::CreateNotify(SendNotifyJob::API_FIRE, $alert, $alertRule->_id);

            $this->refreshStatus($alert->alertRule);
            $apiHistory = $alert->createHistory();
            $alert->createStatusHistory($apiHistory);

            return [
                'status' => true,
                'message' => $isCurrentStateResolved ? 'Activated' : 'Already Active',
            ];

        } else {

            $model = new AlertInstance;
            $model->alertRuleId = $alertRule->_id;
            $model->alertRuleName = $alertRule->name;
            $model->instance = $post['instance'];
            $model->job = $post['job'] ?? '';

            $model->state = AlertInstance::FIRE;

            $model->description = $post['description'] ?? '';
            $model->summary = $post['summary'] ?? '';

            $model->save();

            SendNotifyService::CreateNotify(SendNotifyJob::API_FIRE, $model, $alertRule->_id);
            $this->refreshStatus($model->alertRule);
            $apiHistory = $model->createHistory();
            $model->createStatusHistory($apiHistory);

            return [
                'status' => true,
                'message' => 'Activated',
            ];

        }

    }

    public function resolveAlert(mixed $post)
    {
        $alertRule = AlertRule::firstWhere('apiToken', $post['apiToken']);

        $alert = AlertInstance::where('alertRuleId', $alertRule['id'])
            ->where('instance', $post['instance'])
            ->first();

        if ($alert) {
            if ($alert->state == AlertInstance::FIRE) {

                $alert->state = AlertInstance::RESOLVED;
                $alert->description = $post['description'] ?? '';
                $alert->summary = $post['summary'] ?? '';

                $alert->touch();

                $alert->save();
                SendNotifyService::CreateNotify(SendNotifyJob::API_RESOLVE, $alert, $alert->alertRule->_id);

                $this->refreshStatus($alert->alertRule);
                $apiHistory = $alert->createHistory();
                $alert->createStatusHistory($apiHistory);

                return [
                    'status' => true,
                    'message' => 'Stopped',
                ];
            } else {

                return [
                    'status' => true,
                    'message' => 'Already Stopped',
                ];
            }
        } else {
            return [
                'status' => false,
                'message' => 'Alert Instance Doesn\'t Exists',
            ];
        }
    }

    public function statusAlert(mixed $post)
    {
        $alertRule = AlertRule::firstWhere('apiToken', $post['apiToken']);

        if (! $alertRule) {
            return [
                'status' => false,
                'message' => "Alertname Doesn't Exists.",
            ];
        }

        $alert = AlertInstance::where('alertRuleId', $alertRule['id'])
            ->where('instance', $post['instance'])
            ->first();

        if ($alert) {
            return [
                'status' => true,
                'isFire' => $alert->state == AlertInstance::FIRE,
            ];
        } else {
            return [
                'status' => true,
                'isFire' => false,
            ];
        }
    }

    public function notificationAlert(mixed $post)
    {

        $alertRule = AlertRule::firstWhere('apiToken', $post['apiToken']);

        if (! $alertRule) {
            return [
                'status' => false,
                'message' => "Alertname Doesn't Exists.",
            ];
        }

        $alert = AlertInstance::where([
            'alertRuleId' => $alertRule->_id,
            'alertRuleName' => $alertRule['name'],
            'instance' => $post['instance'],
        ])->first();

        if ($alert) {
            $alert->description = $post['description'] ?? null;
            $alert->touch();
            $alert->save();
        } else {
            $alert = AlertInstance::create([
                'alertRuleId' => $alertRule->_id,
                'alertRuleName' => $alertRule['name'],
                'instance' => $post['instance'],
                'state' => AlertInstance::NOTIFICATION,
                'description' => $post['description'] ?? null,
            ]);

        }

        $alert->touch();
        $alert->save();

        SendNotifyService::CreateNotify(SendNotifyJob::API_NOTIFICATION, $alert, $alertRule->_id);

        $alert->createHistory();

        return [
            'status' => true,
            'message' => 'Done',
        ];

    }

    public function alertRuleByToken($token, ?AlertRuleType $type = null)
    {

        $alertRules = $this->alertRuleService->getAlerts($type);

        $alert = $alertRules->where('apiToken', $token)->first();

        if ($alert) {
            return $alert;
        }

        return null;
    }

    public function refreshStatus(AlertRule $alertRule)
    {
        $count = AlertInstance::where('alertRuleId', $alertRule->id)
            ->where('state', AlertInstance::FIRE)->count();
        $alertRule->state = $count == 0 ? AlertRule::RESOlVED : AlertRule::CRITICAL;
        $alertRule->fireCount = $count;
        $alertRule->save();
        if ($alertRule->state == AlertRule::RESOlVED) {
            $alertRule->removeAcknowledge();
        }
    }
}
