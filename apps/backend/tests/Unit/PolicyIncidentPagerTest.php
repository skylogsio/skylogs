<?php

use App\Enums\Constants;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentTimelineEntryType;
use App\Jobs\PageIncidentLayerJob;
use App\Jobs\SendNotifyJob;
use App\Models\Incident;
use App\Models\IncidentTimelineEntry;
use App\Models\Notify;
use App\Services\IncidentPolicy\AlertMatchContext;
use App\Services\IncidentPolicy\PolicyIncidentOpener;
use App\Services\IncidentPolicy\PolicyIncidentPager;
use App\Services\SendNotifyService;
use Illuminate\Support\Facades\Queue;
use Tests\Support\IncidentPolicyTestData;
use Tests\Support\IncidentTestData;
use Tests\Support\OnCallPlanTestData;
use Tests\Support\TeamTestData;

describe('PolicyIncidentPager', function () {
    beforeEach(function () {
        Queue::fake();

        $this->user = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->backup = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->user, [$this->user->id, $this->backup->id]);
        $this->tag = 'page-'.uniqid();
        $this->alertRule = IncidentPolicyTestData::createAlertRule($this->user);
        $this->alertRule->update([
            'state' => 'critical',
            'tags' => [$this->tag],
        ]);
        $this->notifyEndpoint = IncidentPolicyTestData::createEndpoint($this->user, 'policy-notify');
        $this->primaryEndpoint = IncidentPolicyTestData::createEndpoint($this->user, 'primary-oncall');
        $this->primaryEndpoint->update(['onCall' => true]);
        $this->backupEndpoint = IncidentPolicyTestData::createEndpoint($this->backup, 'backup-oncall');
        $this->backupEndpoint->update(['onCall' => true]);
        $this->plan = IncidentPolicyTestData::createOnCallPlan($this->team, 'page-plan');
        $this->plan->update([
            'timezone' => 'UTC',
            'layers' => [
                [
                    'level' => 1,
                    'escalateAfterMinutes' => 5,
                    'entries' => [[
                        'userId' => $this->user->id,
                        'windows' => [[
                            'daysOfWeek' => [1, 2, 3, 4, 5, 6, 7],
                            'startTime' => '00:00',
                            'endTime' => '24:00',
                        ]],
                    ]],
                ],
                [
                    'level' => 2,
                    'escalateAfterMinutes' => 15,
                    'entries' => [[
                        'userId' => $this->backup->id,
                        'windows' => [[
                            'daysOfWeek' => [1, 2, 3, 4, 5, 6, 7],
                            'startTime' => '00:00',
                            'endTime' => '24:00',
                        ]],
                    ]],
                ],
            ],
        ]);
        $this->incidents = [];
        $this->policies = [];
        $this->notifies = [];
    });

    afterEach(function () {
        foreach ($this->notifies as $notify) {
            Notify::query()->where('_id', $notify->id)->delete();
        }
        foreach ($this->incidents as $incident) {
            if ($incident !== null) {
                Notify::query()->where('incidentId', $incident->id)->delete();
                IncidentTestData::deleteIncident($incident);
            }
        }
        foreach ($this->policies as $policy) {
            IncidentPolicyTestData::deletePolicy($policy);
        }
        OnCallPlanTestData::deletePlan($this->plan);
        IncidentPolicyTestData::deleteEndpoint($this->notifyEndpoint);
        IncidentPolicyTestData::deleteEndpoint($this->primaryEndpoint);
        IncidentPolicyTestData::deleteEndpoint($this->backupEndpoint);
        IncidentPolicyTestData::deleteAlertRule($this->alertRule);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->user);
        TeamTestData::deleteUser($this->backup);
    });

    it('pages the SEV notify endpoints and current on-call when an incident opens', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'rules' => [
                'SEV3' => [
                    'notifyEndpointIds' => [$this->notifyEndpoint->id],
                    'escalation' => ['useLayers' => false],
                ],
            ],
        ]);

        $incidents = app(PolicyIncidentOpener::class)->open(
            AlertMatchContext::fromAlertRule($this->alertRule->fresh()),
        );
        $this->incidents = $incidents->all();

        $notify = Notify::query()
            ->where('type', SendNotifyJob::INCIDENT_POLICY_PAGE)
            ->where('incidentId', $incidents->first()->id)
            ->first();
        $this->notifies[] = $notify;

        expect($notify)->not->toBeNull()
            ->and($notify->endpointIds)->toContain($this->notifyEndpoint->id)
            ->and($notify->endpointIds)->toContain($this->primaryEndpoint->id)
            ->and($notify->endpointIds)->not->toContain($this->backupEndpoint->id);

        Queue::assertPushed(SendNotifyJob::class, fn (SendNotifyJob $job): bool => $job->notify->id === $notify->id);
        Queue::assertNotPushed(PageIncidentLayerJob::class);

        $entry = IncidentTimelineEntry::query()
            ->where('incidentId', $incidents->first()->id)
            ->where('type', IncidentTimelineEntryType::Escalation)
            ->first();

        expect($entry)->not->toBeNull()
            ->and($entry->message)->toContain('Paged from policy');
    });

    it('queues later on-call layers when useLayers is true', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'rules' => [
                'SEV3' => [
                    'notifyEndpointIds' => [$this->notifyEndpoint->id],
                    'escalation' => ['useLayers' => true],
                ],
            ],
        ]);

        $incidents = app(PolicyIncidentOpener::class)->open(
            AlertMatchContext::fromAlertRule($this->alertRule->fresh()),
        );
        $this->incidents = $incidents->all();

        Queue::assertPushed(PageIncidentLayerJob::class, function (PageIncidentLayerJob $job): bool {
            return $job->incidentId === $this->incidents[0]->id
                && $job->teamId === $this->team->id
                && $job->layerLevel === 2;
        });
    });

    it('does not page again when a later fire joins the same incident', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'grouping' => ['key' => ['tag'], 'windowMinutes' => 15],
            'rules' => [
                'SEV3' => [
                    'notifyEndpointIds' => [$this->notifyEndpoint->id],
                    'escalation' => ['useLayers' => false],
                ],
            ],
        ]);

        $opener = app(PolicyIncidentOpener::class);
        $first = $opener->open(AlertMatchContext::fromAlertRule($this->alertRule->fresh()));
        $other = IncidentPolicyTestData::createAlertRule($this->user);
        $other->update(['state' => 'critical', 'tags' => [$this->tag]]);
        $second = $opener->open(AlertMatchContext::fromAlertRule($other->fresh()));
        $this->incidents = $first->concat($second)->unique('id')->values()->all();

        $pages = Notify::query()
            ->where('type', SendNotifyJob::INCIDENT_POLICY_PAGE)
            ->where('incidentId', $first->first()->id)
            ->get();

        expect($second->first()->id)->toBe($first->first()->id)
            ->and($pages)->toHaveCount(1);

        IncidentPolicyTestData::deleteAlertRule($other);
    });

    it('does not page when the opened severity has no rule', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'incident' => [
                'autoCreate' => true,
                'defaultSeverity' => IncidentSeverity::Sev1->value,
            ],
            'rules' => [
                'SEV3' => [
                    'notifyEndpointIds' => [$this->notifyEndpoint->id],
                    'escalation' => ['useLayers' => true],
                ],
            ],
        ]);

        $incidents = app(PolicyIncidentOpener::class)->open(
            AlertMatchContext::fromAlertRule($this->alertRule->fresh()),
        );
        $this->incidents = $incidents->all();

        expect(Notify::query()->where('type', SendNotifyJob::INCIDENT_POLICY_PAGE)->where('incidentId', $incidents->first()->id)->exists())
            ->toBeFalse();
        Queue::assertNotPushed(PageIncidentLayerJob::class);
    });

    it('pages from createNotify when a matching alert fires', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['alertRuleIds' => [$this->alertRule->id]],
            'rules' => [
                'SEV3' => [
                    'notifyEndpointIds' => [$this->notifyEndpoint->id],
                    'escalation' => ['useLayers' => false],
                ],
            ],
        ]);

        $fire = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $fire;

        $incident = Incident::query()->where('policyId', $this->policies[0]->id)->first();
        $this->incidents[] = $incident;

        $page = Notify::query()
            ->where('type', SendNotifyJob::INCIDENT_POLICY_PAGE)
            ->where('incidentId', $incident->id)
            ->first();
        $this->notifies[] = $page;

        expect($page)->not->toBeNull()
            ->and($page->endpointIds)->toContain($this->notifyEndpoint->id);
    });

    it('skips a delayed layer when the incident is already resolved', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'rules' => [
                'SEV3' => [
                    'notifyEndpointIds' => [$this->notifyEndpoint->id],
                    'escalation' => ['useLayers' => true],
                ],
            ],
        ]);

        $incidents = app(PolicyIncidentOpener::class)->open(
            AlertMatchContext::fromAlertRule($this->alertRule->fresh()),
        );
        $incident = $incidents->first();
        $this->incidents[] = $incident;
        $incident->update(['status' => IncidentStatus::Resolved]);

        (new PageIncidentLayerJob(
            (string) $incident->id,
            (string) $this->policies[0]->id,
            (string) $this->team->id,
            2,
        ))->handle(app(PolicyIncidentPager::class));

        expect(
            Notify::query()
                ->where('type', SendNotifyJob::INCIDENT_POLICY_PAGE)
                ->where('incidentId', $incident->id)
                ->get(),
        )->toHaveCount(1);
    });

    it('skips a delayed layer after the team acknowledges', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'rules' => [
                'SEV3' => [
                    'notifyEndpointIds' => [$this->notifyEndpoint->id],
                    'escalation' => ['useLayers' => true],
                ],
            ],
        ]);

        $incidents = app(PolicyIncidentOpener::class)->open(
            AlertMatchContext::fromAlertRule($this->alertRule->fresh()),
        );
        $incident = $incidents->first();
        $this->incidents[] = $incident;
        $incident->update([
            'status' => IncidentStatus::Investigating,
            'acknowledgements' => [[
                'teamId' => $this->team->id,
                'acknowledgedBy' => $this->user->id,
                'acknowledgedAt' => now(),
            ]],
        ]);

        (new PageIncidentLayerJob(
            (string) $incident->id,
            (string) $this->policies[0]->id,
            (string) $this->team->id,
            2,
        ))->handle(app(PolicyIncidentPager::class));

        expect(
            Notify::query()
                ->where('type', SendNotifyJob::INCIDENT_POLICY_PAGE)
                ->where('incidentId', $incident->id)
                ->get(),
        )->toHaveCount(1);
    });

    it('pages the current on-call of a later layer while the incident is still open', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'rules' => [
                'SEV3' => [
                    'notifyEndpointIds' => [$this->notifyEndpoint->id],
                    'escalation' => ['useLayers' => true],
                ],
            ],
        ]);

        $incidents = app(PolicyIncidentOpener::class)->open(
            AlertMatchContext::fromAlertRule($this->alertRule->fresh()),
        );
        $incident = $incidents->first();
        $this->incidents[] = $incident;

        (new PageIncidentLayerJob(
            (string) $incident->id,
            (string) $this->policies[0]->id,
            (string) $this->team->id,
            2,
        ))->handle(app(PolicyIncidentPager::class));

        $pages = Notify::query()
            ->where('type', SendNotifyJob::INCIDENT_POLICY_PAGE)
            ->where('incidentId', $incident->id)
            ->orderBy('createdAt')
            ->get();

        expect($pages)->toHaveCount(2)
            ->and($pages->last()->endpointIds)->toBe([$this->backupEndpoint->id]);
    });
});
