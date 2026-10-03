<?php

namespace App\Services;

use App\Enums\EndpointType;
use App\Enums\FlowEndpointStepType;
use App\Jobs\NotifyFlowEndpointJob;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\NotificationDelivery;
use App\Models\Notify;
use App\Services\Ha\HaReplicationContext;
use App\Services\Notification\DeliveryTarget;
use App\Services\Notification\NotificationDispatcher;
use App\Support\NotifyMessagePayload;
use Illuminate\Support\Collection;

class SendNotifyService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * A follower applying the leader's state must never notify: the leader has
     * already paged whoever needed paging, and a second message for the same
     * alert reaches the same on-call phone. Guarded here rather than at the
     * fourteen call sites so a new caller cannot forget.
     */
    public function createNotify($type, $alert, $alertRuleId = 0)
    {
        if (HaReplicationContext::isApplying()) {
            return null;
        }

        $notify = new Notify;
        $notify->type = $type;
        $notify->alertRuleId = $alertRuleId;

        try {
            $notify->alert = $alert->toArray();
        } catch (\Exception $exception) {
            $notify->alert = $alert;
        }

        if ($type == SendNotifyJob::ALERT_RULE_TEST) {
            $messages = NotifyMessagePayload::fromBody($notify->alertRule->testMessage())->toArray();

        } elseif ($type == SendNotifyJob::ALERT_RULE_ACKNOWLEDGED) {
            $messages = NotifyMessagePayload::fromBody($notify->alertRule->acknowledgedMessage())->toArray();

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
        if ($notify->type === SendNotifyJob::INCIDENT_POLICY_PAGE) {
            $this->sendToNotifyEndpoints($notify);

            return;
        }

        if (empty($notify->alertRule) || ! ($notify->alertRule instanceof AlertRule)) {
            return;
        }

        $behaviorRuleService = app(AlertRuleBehaviorRuleService::class);

        if (! $isTest && $behaviorRuleService->resolveIsSilent(
            $notify->alertRule,
            is_array($notify->alert) ? $notify->alert : [],
        )) {
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
            // in_array($notify->alertRule->_id, SilentRuleService::getCurrentSilents())
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
        $flowsToStart = collect();

        if (! $isAcknowledged && $flows->isNotEmpty()) {
            $resultFlows = $notify->resultFlows ?? [];

            if ($notify->alertRule->state == AlertRule::CRITICAL) {
                foreach ($flows as $flow) {
                    $runningAlertIds = $flow->runningAlertIds ?? [];
                    if (! in_array($flow->id, $runningAlertIds)) {
                        $flow->push('runningAlertIds', $notify->alertRuleId, true);
                        $flowsToStart->push($flow);
                    } else {
                        $resultFlows[$flow->id] = 'Flow is already running';
                    }
                }
            } else {
                $resultFlows[] = 'Not Critical Alert';
            }

            $notify->resultFlows = $resultFlows;
        }

        // Persist before the flow job runs. A sync worker (and a fast queue
        // worker) appends delivery ids to resultFlows, and a later save of
        // this in-memory copy would wipe them.
        $notify->save();

        foreach ($flowsToStart as $flow) {
            NotifyFlowEndpointJob::dispatch($notify, $flow->id);
        }

        $this->dispatcher->dispatch($this->buildTargets($notify, $endpoints), [
            'source' => NotificationDelivery::SOURCE_ALERT,
            'notifyId' => (string) $notify->id,
            'sourceId' => (string) $notify->alertRuleId,
        ]);
    }

    public function SendFlowEndpointsNotify(Notify $notify, $mainEndpointId, $stepEndpointIds, ?int $stepIndex = null)
    {
        $silentUserIds = $notify->alertRule->silentUserIds ?? [];

        $endpoints = Endpoint::whereIn('_id', $stepEndpointIds)
            ->whereNotIn('userId', $silentUserIds)
            ->get();

        $deliveries = $this->dispatcher->dispatch($this->buildTargets($notify, $endpoints), [
            'source' => NotificationDelivery::SOURCE_FLOW,
            'notifyId' => (string) $notify->id,
            'sourceId' => (string) $notify->alertRuleId,
            'flowEndpointId' => (string) $mainEndpointId,
            'flowStepIndex' => $stepIndex,
        ]);

        $resultFlows = $notify->resultFlows ?? [];
        if (empty($resultFlows[$mainEndpointId])) {
            $resultFlows[$mainEndpointId] = [];
        }
        $resultFlows[$mainEndpointId][] = [
            'stepIndex' => $stepIndex,
            'deliveryIds' => $deliveries->map(fn (NotificationDelivery $delivery): string => (string) $delivery->id)->all(),
        ];

        $notify->resultFlows = $resultFlows;

        $notify->save();
    }

    /**
     * Pairs every endpoint with the message it gets. Endpoints covered by a
     * template behavior rule get that template rendered (once per template);
     * the rest, and every fixed system message, get the stored notify message.
     *
     * @param  Collection<int, Endpoint>  $endpoints
     * @return list<DeliveryTarget>
     */
    private function buildTargets(Notify $notify, Collection $endpoints): array
    {
        if ($endpoints->isEmpty()) {
            return [];
        }

        $stored = $notify->messagePayload();

        if ($this->usesFixedSystemMessage($notify) || ! ($notify->alertRule instanceof AlertRule)) {
            return $endpoints
                ->map(fn (Endpoint $endpoint): DeliveryTarget => new DeliveryTarget($endpoint, $stored))
                ->values()
                ->all();
        }

        $endpointTemplates = app(AlertRuleBehaviorRuleService::class)
            ->resolveEndpointTemplates($notify->alertRule);

        $targets = [];

        $endpoints
            ->groupBy(fn (Endpoint $endpoint): string => $endpointTemplates[(string) $endpoint->id] ?? '')
            ->each(function (Collection $group, int|string $template) use ($notify, $stored, &$targets) {
                $template = (string) $template;
                $message = $template === ''
                    ? $stored
                    : NotifyMessageComposer::composeFromSingleTemplate($notify->alertRule, $notify, $template);

                foreach ($group as $endpoint) {
                    $targets[] = new DeliveryTarget($endpoint, $message, templateApplied: $template !== '');
                }
            });

        return $targets;
    }

    private function usesFixedSystemMessage(Notify $notify): bool
    {
        return in_array($notify->type, [
            SendNotifyJob::ALERT_RULE_TEST,
            SendNotifyJob::ALERT_RULE_ACKNOWLEDGED,
            SendNotifyJob::INCIDENT_POLICY_PAGE,
        ], true);
    }

    private function sendToNotifyEndpoints(Notify $notify): void
    {
        $endpointIds = array_values(array_filter(array_map('strval', $notify->endpointIds ?? [])));

        if ($endpointIds === []) {
            return;
        }

        $endpoints = Endpoint::query()->whereIn('_id', $endpointIds)->get();

        foreach ($endpoints->where('type', EndpointType::FLOW->value) as $flow) {
            NotifyFlowEndpointJob::dispatch($notify, $flow->id);
        }

        $this->dispatcher->dispatch($this->buildTargets($notify, $endpoints), [
            'source' => NotificationDelivery::SOURCE_INCIDENT_POLICY,
            'notifyId' => (string) $notify->id,
            'sourceId' => $notify->incidentId === null ? null : (string) $notify->incidentId,
        ]);

        $notify->save();
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

                $this->SendFlowEndpointsNotify($notify, $endpoint->id, $subEndpointIds, $currentStepIndex);

                NotifyFlowEndpointJob::dispatch($notify, $endpoint->_id, $currentStepIndex + 1);
            }
        }

        $notify->save();
    }
}
