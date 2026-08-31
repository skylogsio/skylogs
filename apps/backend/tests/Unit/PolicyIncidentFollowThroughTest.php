<?php

use App\Enums\Constants;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentTimelineEntrySource;
use App\Enums\IncidentTimelineEntryType;
use App\Jobs\EnforceIncidentAckSlaJob;
use App\Jobs\EnforceIncidentResolveSlaJob;
use App\Jobs\RemindIncidentStakeholderUpdateJob;
use App\Jobs\SendNotifyJob;
use App\Models\IncidentTimelineEntry;
use App\Models\Notify;
use App\Models\PostMortem;
use App\Services\IncidentPolicy\AlertMatchContext;
use App\Services\IncidentPolicy\PolicyIncidentFollowThrough;
use App\Services\IncidentPolicy\PolicyIncidentOpener;
use App\Services\IncidentService;
use App\Services\PostMortemService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\IncidentPolicyTestData;
use Tests\Support\IncidentTestData;
use Tests\Support\OnCallPlanTestData;
use Tests\Support\TeamTestData;

describe('PolicyIncidentFollowThrough', function () {
    beforeEach(function () {
        Queue::fake();

        $this->user = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->user);
        $this->tag = 'sla-'.uniqid();
        $this->alertRule = IncidentPolicyTestData::createAlertRule($this->user);
        $this->alertRule->update(['state' => 'critical', 'tags' => [$this->tag]]);
        $this->notifyEndpoint = IncidentPolicyTestData::createEndpoint($this->user, 'sla-notify');
        $this->onCallEndpoint = IncidentPolicyTestData::createEndpoint($this->user, 'sla-oncall');
        $this->onCallEndpoint->update(['onCall' => true]);
        $this->plan = IncidentPolicyTestData::createOnCallPlan($this->team, 'sla-plan');
        $this->plan->update([
            'timezone' => 'UTC',
            'layers' => [[
                'level' => 1,
                'escalateAfterMinutes' => 15,
                'entries' => [[
                    'userId' => $this->user->id,
                    'windows' => [[
                        'daysOfWeek' => [1, 2, 3, 4, 5, 6, 7],
                        'startTime' => '00:00',
                        'endTime' => '24:00',
                    ]],
                ]],
            ]],
        ]);
        $this->incidents = [];
        $this->policies = [];
        $this->openIncident = function (array $rule) {
            $this->policies[] = IncidentPolicyTestData::createPolicy([
                'teamIds' => [$this->team->id],
                'match' => ['tags' => [$this->tag]],
                'rules' => ['SEV3' => $rule],
            ]);

            $incidents = app(PolicyIncidentOpener::class)->open(
                AlertMatchContext::fromAlertRule($this->alertRule->fresh()),
            );
            $this->incidents = $incidents->all();

            return $incidents->first();
        };
    });

    afterEach(function () {
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
        IncidentPolicyTestData::deleteEndpoint($this->onCallEndpoint);
        IncidentPolicyTestData::deleteAlertRule($this->alertRule);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->user);
    });

    it('snapshots the SEV rule and schedules ack, resolve, and stakeholder jobs', function () {
        $incident = ($this->openIncident)([
            'ackWithinMinutes' => 5,
            'resolveWithinMinutes' => 60,
            'communication' => ['stakeholderUpdateEveryMinutes' => 15],
        ]);

        expect($incident->source)->toBe(IncidentSource::Policy)
            ->and($incident->policySla['ackWithinMinutes'])->toBe(5)
            ->and($incident->policySla['resolveWithinMinutes'])->toBe(60)
            ->and($incident->policySla['stakeholderUpdateEveryMinutes'])->toBe(15);

        Queue::assertPushed(EnforceIncidentAckSlaJob::class, fn (EnforceIncidentAckSlaJob $job): bool => $job->incidentId === $incident->id);
        Queue::assertPushed(EnforceIncidentResolveSlaJob::class, fn (EnforceIncidentResolveSlaJob $job): bool => $job->incidentId === $incident->id);
        Queue::assertPushed(RemindIncidentStakeholderUpdateJob::class, fn (RemindIncidentStakeholderUpdateJob $job): bool => $job->incidentId === $incident->id);
    });

    it('assigns the current on-call user as commander when the rule requires one', function () {
        $incident = ($this->openIncident)(['requireCommander' => true]);

        expect($incident->commanderId)->toBe($this->user->id);

        $entry = IncidentTimelineEntry::query()
            ->where('incidentId', $incident->id)
            ->where('type', IncidentTimelineEntryType::Updated)
            ->first();

        expect($entry)->not->toBeNull()
            ->and($entry->message)->toBe('Commander set from on-call.');
    });

    it('records an ack SLA miss and nudges when teams have not acknowledged', function () {
        $incident = ($this->openIncident)([
            'ackWithinMinutes' => 5,
            'notifyEndpointIds' => [$this->notifyEndpoint->id],
        ]);

        Queue::fake();

        (new EnforceIncidentAckSlaJob((string) $incident->id))
            ->handle(app(PolicyIncidentFollowThrough::class));

        $entry = IncidentTimelineEntry::query()
            ->where('incidentId', $incident->id)
            ->where('type', IncidentTimelineEntryType::Escalation)
            ->get()
            ->first(fn (IncidentTimelineEntry $entry): bool => str_starts_with((string) $entry->message, 'Ack SLA missed'));

        expect($entry)->not->toBeNull();
        Queue::assertPushed(SendNotifyJob::class);
    });

    it('does not raise the ack SLA when every team has acknowledged', function () {
        $incident = ($this->openIncident)(['ackWithinMinutes' => 5]);
        $incident->update([
            'acknowledgements' => [[
                'teamId' => $this->team->id,
                'acknowledgedBy' => $this->user->id,
                'acknowledgedAt' => now(),
            ]],
        ]);

        Queue::fake();

        (new EnforceIncidentAckSlaJob((string) $incident->id))
            ->handle(app(PolicyIncidentFollowThrough::class));

        expect(
            IncidentTimelineEntry::query()
                ->where('incidentId', $incident->id)
                ->get()
                ->contains(fn (IncidentTimelineEntry $entry): bool => str_starts_with((string) $entry->message, 'Ack SLA missed')),
        )->toBeFalse();
        Queue::assertNotPushed(SendNotifyJob::class);
    });

    it('records a resolve SLA miss while the incident is still open', function () {
        $incident = ($this->openIncident)(['resolveWithinMinutes' => 30]);

        (new EnforceIncidentResolveSlaJob((string) $incident->id))
            ->handle(app(PolicyIncidentFollowThrough::class));

        expect(
            IncidentTimelineEntry::query()
                ->where('incidentId', $incident->id)
                ->get()
                ->contains(fn (IncidentTimelineEntry $entry): bool => str_starts_with((string) $entry->message, 'Resolve SLA missed')),
        )->toBeTrue();
    });

    it('skips SLA jobs after the incident is resolved', function () {
        $incident = ($this->openIncident)(['ackWithinMinutes' => 5, 'resolveWithinMinutes' => 30]);
        $incident->update(['status' => IncidentStatus::Resolved]);

        (new EnforceIncidentAckSlaJob((string) $incident->id))
            ->handle(app(PolicyIncidentFollowThrough::class));
        (new EnforceIncidentResolveSlaJob((string) $incident->id))
            ->handle(app(PolicyIncidentFollowThrough::class));

        expect(
            IncidentTimelineEntry::query()
                ->where('incidentId', $incident->id)
                ->where('type', IncidentTimelineEntryType::Escalation)
                ->get()
                ->contains(fn (IncidentTimelineEntry $entry): bool => str_contains((string) $entry->message, 'SLA missed')),
        )->toBeFalse();
    });

    it('reminds stakeholders and reschedules when no recent user update exists', function () {
        $incident = ($this->openIncident)(['communication' => ['stakeholderUpdateEveryMinutes' => 10]]);

        Queue::fake();

        (new RemindIncidentStakeholderUpdateJob((string) $incident->id))
            ->handle(app(PolicyIncidentFollowThrough::class));

        expect(
            IncidentTimelineEntry::query()
                ->where('incidentId', $incident->id)
                ->where('type', IncidentTimelineEntryType::Communication)
                ->where('message', 'Stakeholder update due.')
                ->exists(),
        )->toBeTrue();

        Queue::assertPushed(RemindIncidentStakeholderUpdateJob::class);
    });

    it('blocks resolve when a commander is required and missing', function () {
        $incident = ($this->openIncident)(['requireCommander' => true]);
        $incident->update(['commanderId' => null]);

        expect(fn () => app(IncidentService::class)->resolve($this->user, $incident->fresh()))
            ->toThrow(ValidationException::class);
    });

    it('blocks resolve when a public status-page update is required and missing', function () {
        $incident = ($this->openIncident)([
            'communication' => ['statusPageUpdateRequired' => true],
        ]);

        expect(fn () => app(IncidentService::class)->resolve($this->user, $incident->fresh()))
            ->toThrow(ValidationException::class);
    });

    it('resolves after a public status-page update is posted', function () {
        $incident = ($this->openIncident)([
            'communication' => ['statusPageUpdateRequired' => true],
        ]);

        IncidentTestData::createTimelineEntry($incident, [
            'type' => IncidentTimelineEntryType::Communication,
            'source' => IncidentTimelineEntrySource::User,
            'isPublic' => true,
            'message' => 'Public update',
        ]);

        $resolved = app(IncidentService::class)->resolve($this->user, $incident->fresh());

        expect($resolved->status)->toBe(IncidentStatus::Resolved);
    });

    it('opens a postmortem draft when the policy requires one', function () {
        $incident = ($this->openIncident)([
            'postmortem' => ['required' => true, 'dueDays' => 5],
        ]);

        $resolved = app(IncidentService::class)->resolve($this->user, $incident->fresh());
        $postMortem = PostMortem::query()->where('incidentId', $resolved->id)->first();

        expect($postMortem)->not->toBeNull()
            ->and($postMortem->dueAt)->not->toBeNull()
            ->and((int) round($postMortem->dueAt->diffInDays($resolved->resolvedAt, true)))->toBe(5);
    });

    it('blocks publishing a postmortem that still needs reviewers', function () {
        $incident = ($this->openIncident)([
            'postmortem' => ['required' => true, 'dueDays' => 5, 'reviewRequired' => true],
        ]);
        $resolved = app(IncidentService::class)->resolve($this->user, $incident->fresh());
        $postMortem = PostMortem::query()->where('incidentId', $resolved->id)->first();

        expect(fn () => app(PostMortemService::class)->publish($this->user, $resolved, $postMortem))
            ->toThrow(ValidationException::class);
    });

    it('sets the commander from the first acknowledgement when on-call did not', function () {
        $incident = ($this->openIncident)(['requireCommander' => true]);
        $incident->update(['commanderId' => null]);

        $updated = app(IncidentService::class)->acknowledge($this->user, $incident->fresh());

        expect($updated->commanderId)->toBe($this->user->id);
    });
});
