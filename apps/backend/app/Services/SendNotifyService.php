<?php

namespace App\Services;

use App\Enums\EndpointType;
use App\Enums\FlowEndpointStepType;
use App\Helpers\Call;
use App\Helpers\Email;
use App\Helpers\MatterMost;
use App\Helpers\SMS;
use App\Helpers\Teams;
use App\Helpers\Telegram;
use App\Jobs\NotifyFlowEndpointJob;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\Notify;

class SendNotifyService
{
    public static function CreateNotify($type, $alert, $alertRuleId = 0)
    {
        $notify = new Notify();
        $notify->type = $type;
        $notify->alertRuleId = $alertRuleId;

        try {
            $notify->alert = $alert->toArray();
        } catch (\Exception $exception) {
            $notify->alert = $alert;
        }

        if ($type == SendNotifyJob::ALERT_RULE_TEST) {
            $messages = [
                "matterMostMessage" => $notify->alertRule->testMessage(),
                "telegram" => $notify->alertRule->testMessage(),
                "teamsMessage" => $notify->alertRule->testMessage(),
                "emailMessage" => $notify->alertRule->testMessage(),
                "smsMessage" => $notify->alertRule->testMessage(),
                "callMessage" => $notify->alertRule->testMessage(),
                "defaultMessage" => $notify->alertRule->testMessage(),
            ];

        } elseif ($type == SendNotifyJob::ALERT_RULE_ACKNOWLEDGED) {
            $messages = [
                "matterMostMessage" => $notify->alertRule->acknowledgedMessage(),
                "telegram" => $notify->alertRule->acknowledgedMessage(),
                "teamsMessage" => $notify->alertRule->acknowledgedMessage(),
                "emailMessage" => $notify->alertRule->acknowledgedMessage(),
                "smsMessage" => $notify->alertRule->acknowledgedMessage(),
                "callMessage" => $notify->alertRule->acknowledgedMessage(),
                "defaultMessage" => $notify->alertRule->acknowledgedMessage(),
            ];

        } else {


            $messages = [
                "matterMostMessage" => $alert->matterMostMessage(),
                "telegram" => $alert->telegram(),
                "teamsMessage" => $alert->teamsMessage(),
                "emailMessage" => $alert->emailMessage(),
                "smsMessage" => $alert->smsMessage(),
                "callMessage" => $alert->callMessage(),
                "defaultMessage" => $alert->defaultMessage(),
            ];

        }
        $notify->messages = $messages;

        $notify->status = Notify::STATUS_CREATED;

        $notify->save();
        SendNotifyJob::dispatch($notify);

        return $notify;
    }

    public static function SendMessage(Notify $notify, $isTest = false, $isAcknowledged = false)
    {

        if (empty($notify->alertRule) || !($notify->alertRule instanceof AlertRule)) {
            return;
        }

        $endpointIds = $notify->alertRule->endpointIds ?? [];
        $silentUserIds = $notify->alertRule->silentUserIds ?? [];

        if (!$isTest && (
                in_array($notify->alertRule->userId, $silentUserIds) ||
                in_array(app(UserService::class)->admin()->id, $silentUserIds)
//            in_array($notify->alertRule->_id, SilentRuleService::getCurrentSilents())
            )) {
            $notify->status = Notify::STATUS_SILENT;
            $notify->save();
            return;
        }

        if ($notify->alertRule->isAcknowledged() && !$isAcknowledged) {
            $notify->status = Notify::STATUS_ACKNOWLEDGED;
            $notify->save();
            return;
        }

        $notify->endpointIds = $endpointIds;
        $notify->silentUserIds = $silentUserIds;

        $endpointsQuery = Endpoint::whereIn("_id", $endpointIds);
        if (!$isTest) {
            $endpointsQuery = $endpointsQuery->whereNotIn("userId", $silentUserIds);
        }
        $endpoints = $endpointsQuery->get();


        $phones = $endpoints->where("type", EndpointType::SMS->value)->pluck("value");
        $phonesCalls = $endpoints->where("type", EndpointType::CALL->value)->pluck("value");
        $teamsUrls = $endpoints->where("type", EndpointType::TEAMS->value)->pluck("value");
        $matterMostUrls = $endpoints->where("type", EndpointType::MATTER_MOST->value)->pluck("value");
        $emails = $endpoints->where("type", EndpointType::EMAIL->value)->pluck("value")->toArray();
        $telegrams = $endpoints->where("type", EndpointType::TELEGRAM->value)->toArray();
        $flows = $endpoints->where("type", EndpointType::FLOW->value);

        if (!$isAcknowledged && $flows->isNotEmpty()) {

            $resultFlows = $notify->resultFlows ?? [];

            if ($notify->alertRule->state == AlertRule::CRITICAL) {
                foreach ($flows as $flow) {
                    $runningAlertIds = $flow->runningAlertIds ?? [];
                    if(!in_array($flow->id, $runningAlertIds)) {
                        $flow->push("runningAlertIds", $notify->alertRuleId);
                        NotifyFlowEndpointJob::dispatch($notify, $flow->id);

                    } else {
                        $resultFlows[$flow->id] = "Flow is already running";
                    }
                }
            }else{
                $resultFlows[] =  "Not Critical Alert";
            }

            $notify->resultFlows = $resultFlows;
        }

        if ($phones->isNotEmpty()) {
            $result = SMS::sendAlert($phones,
                $notify);
            $notify->resultSms = $result;
        }

        if ($phonesCalls->isNotEmpty()) {
            $result = Call::sendAlert($phonesCalls,
                $notify,
            );
            $notify->resultCall = $result;
        }

        if ($teamsUrls->isNotEmpty()) {
            $result = Teams::sendMessageAlert($teamsUrls,
                $notify,
            );
            $notify->resultTeams = $result;
        }

        if ($matterMostUrls->isNotEmpty()) {
            $result = MatterMost::sendMessageAlert($matterMostUrls,
                $notify,
            );
            $notify->resultMatterMost = $result;
        }

        if (!empty($telegrams)) {
            $result = Telegram::sendMessageAlert($telegrams,
                $notify
            );
            $notify->resultTelegram = $result;
        }

        if (!empty($emails)) {
            $result = Email::sendMessageAlert($emails,
                $notify
            );
            $notify->resultEmail = $result;
        }

        $notify->save();
    }

    public function SendFlowEndpointsNotify(Notify $notify, $mainEndpointId, $stepEndpointIds)
    {

        $silentUserIds = $notify->alertRule->silentUserIds ?? [];

        $endpointsQuery = Endpoint::whereIn("_id", $stepEndpointIds);

        $endpointsQuery = $endpointsQuery->whereNotIn("userId", $silentUserIds);

        $endpoints = $endpointsQuery->get();


        $phones = $endpoints->where("type", EndpointType::SMS->value)->pluck("value");
        $phonesCalls = $endpoints->where("type", EndpointType::CALL->value)->pluck("value");
        $teamsUrls = $endpoints->where("type", EndpointType::TEAMS->value)->pluck("value");
        $matterMostUrls = $endpoints->where("type", EndpointType::MATTER_MOST->value)->pluck("value");
        $emails = $endpoints->where("type", EndpointType::EMAIL->value)->pluck("value")->toArray();
        $telegrams = $endpoints->where("type", EndpointType::TELEGRAM->value)->toArray();

        $resultStep = [];

        if ($phones->isNotEmpty()) {
            $result = SMS::sendAlert($phones,
                $notify);
            $resultStep['resultSms'] = $result;
        }

        if ($phonesCalls->isNotEmpty()) {
            $result = Call::sendAlert($phonesCalls,
                $notify,
            );
            $resultStep['resultCall'] = $result;
        }

        if ($teamsUrls->isNotEmpty()) {
            $result = Teams::sendMessageAlert($teamsUrls,
                $notify,
            );
            $resultStep['resultTeams'] = $result;
        }

        if ($matterMostUrls->isNotEmpty()) {
            $result = MatterMost::sendMessageAlert($matterMostUrls,
                $notify,
            );
            $resultStep['resultMatterMost'] = $result;
        }

        if (!empty($telegrams)) {
            $result = Telegram::sendMessageAlert($telegrams,
                $notify
            );
            $resultStep['resultTelegram'] = $result;
        }

        if (!empty($emails)) {
            $result = Email::sendMessageAlert($emails,
                $notify
            );
            $resultStep['resultEmail'] = $result;
        }

        $resultFlows = $notify->resultFlows ?? [];
        if (empty($resultFlows[$mainEndpointId])) {
            $resultFlows[$mainEndpointId] = [];
        }
        $resultFlows[$mainEndpointId][] = $resultStep;

        $notify->resultFlows = $resultFlows;

        $notify->save();
    }

    public function processStep(Notify $notify, $endpointId, int $currentStepIndex = 0)
    {
        $notify->refresh();
        $endpoint = Endpoint::where("_id", $endpointId)->first();

        $silentUserIds = $notify->alertRule->silentUserIds ?? [];

        if (
            in_array($notify->alertRule->userId, $silentUserIds) ||
            in_array(app(UserService::class)->admin()->id, $silentUserIds)
        ) {
            $resultFlows = $notify->resultFlows ?? [];
            if (empty($resultFlows[$endpointId])) {
                $resultFlows[$endpointId] = [];
            }
            $resultFlows[$endpointId][] = [
                "status" => Notify::STATUS_SILENT,
                "label" => "silent"
            ];

            $notify->resultFlows = $resultFlows;
            $notify->save();
            $endpoint->pull("runningAlertIds",$notify->alertRuleId);
            return;
        }

        if ($notify->alertRule->isAcknowledged()) {
            $resultFlows = $notify->resultFlows ?? [];
            if (empty($resultFlows[$endpointId])) {
                $resultFlows[$endpointId] = [];
            }
            $resultFlows[$endpointId][] = [
                "status" => Notify::STATUS_ACKNOWLEDGED,
                "label" => "acknowledged"
            ];
            $notify->resultFlows = $resultFlows;
            $notify->save();
            $endpoint->pull("runningAlertIds",$notify->alertRuleId);
            return;
        }

        if ($notify->alertRule->state != AlertRule::CRITICAL) {

            $resultFlows = $notify->resultFlows ?? [];
            if (empty($resultFlows[$endpointId])) {
                $resultFlows[$endpointId] = [];
            }
            $resultFlows[$endpointId][] = [
                "status" => -1,
                "label" => "not critical alert",
                "description" => "AlertRule state is ".$notify->alertRule->state,
            ];
            $notify->resultFlows = $resultFlows;
            $notify->save();
            $endpoint->pull("runningAlertIds",$notify->alertRuleId);
            return;
        }
        $steps = $endpoint->steps;

        if ($currentStepIndex >= count($steps)) {
            $endpoint->pull("runningAlertIds",$notify->alertRuleId);
            return;
        }

        $step = $steps[$currentStepIndex];

        if ($step['type'] === FlowEndpointStepType::WAIT->value) {
            $delay = 0;
            switch ($step['timeUnit']) {
                case "s":
                    $delay = $step['duration'];
                    break;
                case "m":
                    $delay = $step['duration'] * 60;
                    break;
                case "h":
                    $delay = $step['duration'] * 3600;
                    break;
            }
            $delay = intval($delay);
            NotifyFlowEndpointJob::dispatch($notify, $endpoint->_id, $currentStepIndex + 1)
                ->delay(now()->addSeconds($delay));
        } elseif ($step['type'] === FlowEndpointStepType::ENDPOINT->value) {

            $subEndpointIds = $step['endpointIds'] ?? [];
            if (!empty($subEndpointIds)) {

                $this->SendFlowEndpointsNotify($notify, $endpoint->id, $subEndpointIds);

                NotifyFlowEndpointJob::dispatch($notify, $endpoint->_id, $currentStepIndex + 1);
            }
        }

        $notify->save();
    }
}
