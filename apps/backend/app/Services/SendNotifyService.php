<?php

namespace App\Services;

use App\Enums\EndpointType;
use App\Enums\FlowEndpointStepType;
use App\Helpers\Call;
use App\Helpers\Discord;
use App\Helpers\Email;
use App\Helpers\MatterMost;
use App\Helpers\SMS;
use App\Helpers\Teams;
use App\Helpers\Telegram;
use App\Interfaces\Messageable;
use App\Jobs\NotifyFlowEndpointJob;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\Notify;
use Illuminate\Support\Collection;

class SendNotifyService
{
    public function createNotify($type, $alert, $alertRuleId = 0)
    {
        $notify = new Notify;
        $notify->type = $type;
        $notify->alertRuleId = $alertRuleId;

        try {
            $notify->alert = $alert->toArray();
        } catch (\Exception $exception) {
            $notify->alert = $alert;
        }

        if ($type == SendNotifyJob::ALERT_RULE_TEST) {
            $messages = [
                'matterMostMessage' => $notify->alertRule->testMessage(),
                'telegram' => $notify->alertRule->testMessage(),
                'teamsMessage' => $notify->alertRule->testMessage(),
                'emailMessage' => $notify->alertRule->testMessage(),
                'smsMessage' => $notify->alertRule->testMessage(),
                'discordMessage' => $notify->alertRule->testMessage(),
                'callMessage' => $notify->alertRule->testMessage(),
                'defaultMessage' => $notify->alertRule->testMessage(),
            ];

        } elseif ($type == SendNotifyJob::ALERT_RULE_ACKNOWLEDGED) {
            $messages = [
                'matterMostMessage' => $notify->alertRule->acknowledgedMessage(),
                'telegram' => $notify->alertRule->acknowledgedMessage(),
                'teamsMessage' => $notify->alertRule->acknowledgedMessage(),
                'emailMessage' => $notify->alertRule->acknowledgedMessage(),
                'smsMessage' => $notify->alertRule->acknowledgedMessage(),
                'discordMessage' => $notify->alertRule->acknowledgedMessage(),
                'callMessage' => $notify->alertRule->acknowledgedMessage(),
                'defaultMessage' => $notify->alertRule->acknowledgedMessage(),
            ];

        } else {
            $alertRule = $alertRuleId
                ? AlertRule::where('_id', $alertRuleId)->first()
                    ?? AlertRule::where('id', $alertRuleId)->first()
                : null;

            $messages = NotifyMessageComposer::buildMessages($alertRule, $alert);

        }
        $notify->messages = $messages;

        $notify->status = Notify::STATUS_CREATED;

        $notify->save();
        SendNotifyJob::dispatch($notify);

        return $notify;
    }

    public function SendMessage(Notify $notify, $isTest = false, $isAcknowledged = false)
    {

        if (empty($notify->alertRule) || ! ($notify->alertRule instanceof AlertRule)) {
            return;
        }

        $behaviorRuleService = app(AlertRuleBehaviorRuleService::class);

        if (! $isTest && $behaviorRuleService->resolveIsSilent($notify->alertRule)) {
            $notify->status = Notify::STATUS_SILENT;
            $notify->save();

            return;
        }

        $endpointIds = $behaviorRuleService->resolveEndpointIds(
            $notify->alertRule,
            is_array($notify->alert) ? $notify->alert : [],
        );
        $silentUserIds = $notify->alertRule->silentUserIds ?? [];

        if (! $isTest && (
            in_array($notify->alertRule->userId, $silentUserIds) ||
            in_array(app(UserService::class)->admin()->id, $silentUserIds)
            //            in_array($notify->alertRule->_id, SilentRuleService::getCurrentSilents())
        )) {
            $notify->status = Notify::STATUS_SILENT;
            $notify->save();

            return;
        }

        if ($notify->alertRule->isAcknowledged() && ! $isAcknowledged) {
            $notify->status = Notify::STATUS_ACKNOWLEDGED;
            $notify->save();

            return;
        }

        $notify->endpointIds = $endpointIds;
        $notify->silentUserIds = $silentUserIds;

        $endpointsQuery = Endpoint::whereIn('_id', $endpointIds);
        if (! $isTest) {
            $endpointsQuery = $endpointsQuery->whereNotIn('userId', $silentUserIds);
        }
        $endpoints = $endpointsQuery->get();

        $flows = $endpoints->where('type', EndpointType::FLOW->value);

        if (! $isAcknowledged && $flows->isNotEmpty()) {

            $resultFlows = $notify->resultFlows ?? [];

            if ($notify->alertRule->state == AlertRule::CRITICAL) {
                foreach ($flows as $flow) {
                    $runningAlertIds = $flow->runningAlertIds ?? [];
                    if (! in_array($flow->id, $runningAlertIds)) {
                        $flow->push('runningAlertIds', $notify->alertRuleId, true);
                        NotifyFlowEndpointJob::dispatch($notify, $flow->id);
                    } else {
                        $resultFlows[$flow->id] = 'Flow is already running';
                    }
                }
            } else {
                $resultFlows[] = 'Not Critical Alert';
            }

            $notify->resultFlows = $resultFlows;
        }

        $notify->resultSms = $this->sendSmsAlerts($endpoints->where('type', EndpointType::SMS->value), $notify);
        $notify->resultCall = $this->sendCallAlerts($endpoints->where('type', EndpointType::CALL->value), $notify);
        $notify->resultTeams = $this->sendTeamsAlerts($endpoints->where('type', EndpointType::TEAMS->value), $notify);
        $notify->resultDiscords = $this->sendDiscordAlerts($endpoints->where('type', EndpointType::DISCORD->value), $notify);
        $notify->resultMatterMost = $this->sendMatterMostAlerts($endpoints->where('type', EndpointType::MATTER_MOST->value), $notify);
        $notify->resultTelegram = $this->sendTelegramAlerts($endpoints->where('type', EndpointType::TELEGRAM->value), $notify);
        $notify->resultEmail = $this->sendEmailAlerts($endpoints->where('type', EndpointType::EMAIL->value), $notify);

        $notify->save();
    }

    public function SendFlowEndpointsNotify(Notify $notify, $mainEndpointId, $stepEndpointIds)
    {

        $silentUserIds = $notify->alertRule->silentUserIds ?? [];

        $endpointsQuery = Endpoint::whereIn('_id', $stepEndpointIds);

        $endpointsQuery = $endpointsQuery->whereNotIn('userId', $silentUserIds);

        $endpoints = $endpointsQuery->get();

        $resultStep = [];

        if ($smsResult = $this->sendSmsAlerts($endpoints->where('type', EndpointType::SMS->value), $notify)) {
            $resultStep['resultSms'] = $smsResult;
        }

        if ($callResult = $this->sendCallAlerts($endpoints->where('type', EndpointType::CALL->value), $notify)) {
            $resultStep['resultCall'] = $callResult;
        }

        if ($teamsResult = $this->sendTeamsAlerts($endpoints->where('type', EndpointType::TEAMS->value), $notify)) {
            $resultStep['resultTeams'] = $teamsResult;
        }

        if ($discordResult = $this->sendDiscordAlerts($endpoints->where('type', EndpointType::DISCORD->value), $notify)) {
            $resultStep['resultDiscords'] = $discordResult;
        }

        if ($matterMostResult = $this->sendMatterMostAlerts($endpoints->where('type', EndpointType::MATTER_MOST->value), $notify)) {
            $resultStep['resultMatterMost'] = $matterMostResult;
        }

        if ($telegramResult = $this->sendTelegramAlerts($endpoints->where('type', EndpointType::TELEGRAM->value), $notify)) {
            $resultStep['resultTelegram'] = $telegramResult;
        }

        if ($emailResult = $this->sendEmailAlerts($endpoints->where('type', EndpointType::EMAIL->value), $notify)) {
            $resultStep['resultEmail'] = $emailResult;
        }

        $resultFlows = $notify->resultFlows ?? [];
        if (empty($resultFlows[$mainEndpointId])) {
            $resultFlows[$mainEndpointId] = [];
        }
        $resultFlows[$mainEndpointId][] = $resultStep;

        $notify->resultFlows = $resultFlows;

        $notify->save();
    }

    /**
     * @param  Collection<int, Endpoint>  $endpoints
     */
    private function sendSmsAlerts(Collection $endpoints, Notify $notify): mixed
    {
        return $this->sendChannelAlerts(
            $endpoints,
            $notify,
            fn (Collection $group, Messageable $messageable) => SMS::sendAlert($group->pluck('value'), $messageable),
        );
    }

    /**
     * @param  Collection<int, Endpoint>  $endpoints
     */
    private function sendCallAlerts(Collection $endpoints, Notify $notify): mixed
    {
        return $this->sendChannelAlerts(
            $endpoints,
            $notify,
            fn (Collection $group, Messageable $messageable) => Call::sendAlert($group->pluck('value'), $messageable),
        );
    }

    /**
     * @param  Collection<int, Endpoint>  $endpoints
     */
    private function sendTeamsAlerts(Collection $endpoints, Notify $notify): mixed
    {
        return $this->sendChannelAlerts(
            $endpoints,
            $notify,
            fn (Collection $group, Messageable $messageable) => Teams::sendMessageAlert($group->pluck('value'), $messageable),
        );
    }

    /**
     * @param  Collection<int, Endpoint>  $endpoints
     */
    private function sendDiscordAlerts(Collection $endpoints, Notify $notify): mixed
    {
        return $this->sendChannelAlerts(
            $endpoints,
            $notify,
            fn (Collection $group, Messageable $messageable) => Discord::sendMessageAlert($group->pluck('value'), $messageable),
        );
    }

    /**
     * @param  Collection<int, Endpoint>  $endpoints
     */
    private function sendMatterMostAlerts(Collection $endpoints, Notify $notify): mixed
    {
        return $this->sendChannelAlerts(
            $endpoints,
            $notify,
            fn (Collection $group, Messageable $messageable) => MatterMost::sendMessageAlert($group->pluck('value'), $messageable),
        );
    }

    /**
     * @param  Collection<int, Endpoint>  $endpoints
     */
    private function sendTelegramAlerts(Collection $endpoints, Notify $notify): mixed
    {
        return $this->sendChannelAlerts(
            $endpoints,
            $notify,
            fn (Collection $group, Messageable $messageable) => Telegram::sendMessageAlert($group->values()->all(), $messageable),
        );
    }

    /**
     * @param  Collection<int, Endpoint>  $endpoints
     */
    private function sendEmailAlerts(Collection $endpoints, Notify $notify): mixed
    {
        return $this->sendChannelAlerts(
            $endpoints,
            $notify,
            fn (Collection $group, Messageable $messageable) => Email::sendMessageAlert($group->pluck('value')->toArray(), $messageable),
        );
    }

    /**
     * @param  Collection<int, Endpoint>  $endpoints
     * @param  callable(Collection<int, Endpoint>, Messageable): mixed  $sender
     */
    private function sendChannelAlerts(Collection $endpoints, Notify $notify, callable $sender): mixed
    {
        if ($endpoints->isEmpty()) {
            return null;
        }

        $endpointTemplates = app(AlertRuleBehaviorRuleService::class)
            ->resolveEndpointTemplates($notify->alertRule);

        $results = [];

        $endpoints
            ->groupBy(fn (Endpoint $endpoint) => $endpointTemplates[(string) ($endpoint->id ?? $endpoint->_id)] ?? '')
            ->each(function (Collection $group, string $template) use ($notify, $sender, &$results) {
                $messageable = $this->messageableForTemplate($notify, $template);
                $results[] = $sender($group, $messageable);
            });

        if ($results === []) {
            return null;
        }

        return count($results) === 1 ? $results[0] : $results;
    }

    private function messageableForTemplate(Notify $notify, string $template): Messageable
    {
        if ($template === '' || ! ($notify->alertRule instanceof AlertRule)) {
            return $notify;
        }

        return new NotifyMessagesAdapter(
            NotifyMessageComposer::composeFromSingleTemplate($notify->alertRule, $notify, $template)
        );
    }

    public function processStep(Notify $notify, $endpointId, int $currentStepIndex = 0)
    {
        $notify->refresh();
        $endpoint = Endpoint::where('_id', $endpointId)->first();

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
                'status' => Notify::STATUS_SILENT,
                'label' => 'silent',
            ];

            $notify->resultFlows = $resultFlows;
            $notify->save();
            $endpoint->pull('runningAlertIds', $notify->alertRuleId);

            return;
        }

        if ($notify->alertRule->isAcknowledged()) {
            $resultFlows = $notify->resultFlows ?? [];
            if (empty($resultFlows[$endpointId])) {
                $resultFlows[$endpointId] = [];
            }
            $resultFlows[$endpointId][] = [
                'status' => Notify::STATUS_ACKNOWLEDGED,
                'label' => 'acknowledged',
            ];
            $notify->resultFlows = $resultFlows;
            $notify->save();
            $endpoint->pull('runningAlertIds', $notify->alertRuleId);

            return;
        }

        if ($notify->alertRule->state != AlertRule::CRITICAL) {

            $resultFlows = $notify->resultFlows ?? [];
            if (empty($resultFlows[$endpointId])) {
                $resultFlows[$endpointId] = [];
            }
            $resultFlows[$endpointId][] = [
                'status' => -1,
                'label' => 'not critical alert',
                'description' => 'AlertRule state is '.$notify->alertRule->state,
            ];
            $notify->resultFlows = $resultFlows;
            $notify->save();
            $endpoint->pull('runningAlertIds', $notify->alertRuleId);

            return;
        }
        $steps = $endpoint->steps;

        if ($currentStepIndex >= count($steps)) {
            $endpoint->pull('runningAlertIds', $notify->alertRuleId);

            return;
        }

        $step = $steps[$currentStepIndex];

        if ($step['type'] === FlowEndpointStepType::WAIT->value) {
            $delay = 0;
            switch ($step['timeUnit']) {
                case 's':
                    $delay = $step['duration'];
                    break;
                case 'm':
                    $delay = $step['duration'] * 60;
                    break;
                case 'h':
                    $delay = $step['duration'] * 3600;
                    break;
            }
            $delay = intval($delay);
            NotifyFlowEndpointJob::dispatch($notify, $endpoint->_id, $currentStepIndex + 1)
                ->delay(now()->addSeconds($delay));
        } elseif ($step['type'] === FlowEndpointStepType::ENDPOINT->value) {

            $subEndpointIds = $step['endpointIds'] ?? [];
            if (! empty($subEndpointIds)) {

                $this->SendFlowEndpointsNotify($notify, $endpoint->id, $subEndpointIds);

                NotifyFlowEndpointJob::dispatch($notify, $endpoint->_id, $currentStepIndex + 1);
            }
        }

        $notify->save();
    }
}
