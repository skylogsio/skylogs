<?php

use App\Enums\Constants;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentTimelineEntryType;
use App\Jobs\SendNotifyJob;
use App\Models\Incident;
use App\Models\IncidentTimelineEntry;
use App\Models\Notify;
use App\Services\Ha\HaReplicationContext;
use App\Services\SendNotifyService;
use Illuminate\Support\Facades\Queue;
use Tests\Support\IncidentPolicyTestData;
use Tests\Support\IncidentTestData;
use Tests\Support\TeamTestData;

describe('SendNotifyService policy incidents', function () {
    beforeEach(function () {
        Queue::fake();

        $this->user = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->user);
        $this->alertRule = IncidentPolicyTestData::createAlertRule($this->user);
        $this->alertRule->update(['state' => 'critical']);
        $this->policy = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['alertRuleIds' => [$this->alertRule->id]],
            'grouping' => ['key' => ['alertRuleId'], 'windowMinutes' => 15],
            'incident' => ['autoCreate' => true, 'autoResolveOnAlertClear' => true],
        ]);
        $this->incidents = [];
        $this->notifies = [];
        $this->extraAlertRules = [];
    });

    afterEach(function () {
        foreach ($this->incidents as $incident) {
            if ($incident !== null) {
                IncidentTestData::deleteIncident($incident);
            }
        }
        foreach ($this->notifies as $notify) {
            Notify::query()->where('_id', $notify->id)->delete();
        }
        foreach ($this->extraAlertRules as $alertRule) {
            IncidentPolicyTestData::deleteAlertRule($alertRule);
        }
        IncidentPolicyTestData::deletePolicy($this->policy);
        IncidentPolicyTestData::deleteAlertRule($this->alertRule);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->user);
    });

    it('opens an incident when a matching critical alert fires', function () {
        $notify = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $notify;

        $incident = Incident::query()->where('policyId', $this->policy->id)->first();
        $this->incidents[] = $incident;

        expect($notify)->not->toBeNull()
            ->and($incident)->not->toBeNull()
            ->and($incident->source)->toBe(IncidentSource::Policy)
            ->and($incident->alertRuleIds)->toBe([$this->alertRule->id]);

        Queue::assertPushed(SendNotifyJob::class);
    });

    it('opens an incident when a matching warning alert fires', function () {
        $this->alertRule->update(['state' => 'warning']);

        $notify = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $notify;

        $incident = Incident::query()->where('policyId', $this->policy->id)->first();
        $this->incidents[] = $incident;

        expect($incident)->not->toBeNull()
            ->and($incident->status)->toBe(IncidentStatus::Open);
    });

    it('opens an incident when a matching triggered alert fires', function () {
        $this->alertRule->update(['state' => 'triggered']);

        $notify = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $notify;

        $incident = Incident::query()->where('policyId', $this->policy->id)->first();
        $this->incidents[] = $incident;

        expect($incident)->not->toBeNull()
            ->and($incident->status)->toBe(IncidentStatus::Open);
    });

    it('does not open an incident when a dual-purpose webhook reports a cleared alert', function () {
        $this->alertRule->update(['state' => 'resolved']);

        $notify = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::GRAFANA_WEBHOOK,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $notify;

        expect(Incident::query()->where('policyId', $this->policy->id)->exists())->toBeFalse();
    });

    it('opens an incident on an exclusive fire type before the rule state is refreshed', function () {
        $this->alertRule->update(['state' => 'resolved']);

        $notify = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $notify;

        $incident = Incident::query()->where('policyId', $this->policy->id)->first();
        $this->incidents[] = $incident;

        expect($incident)->not->toBeNull()
            ->and($incident->status)->toBe(IncidentStatus::Open);
    });

    it('does not open an incident for a test notify', function () {
        $this->alertRule->update(['testMessage' => 'test ping']);

        $notify = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::ALERT_RULE_TEST,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $notify;

        expect(Incident::query()->where('policyId', $this->policy->id)->exists())->toBeFalse();
    });

    it('auto-resolves the incident when the last grouped alert clears', function () {
        $fire = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $fire;

        $this->alertRule->update(['state' => 'resolved']);

        $resolve = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_RESOLVE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $resolve;

        $incident = Incident::query()->where('policyId', $this->policy->id)->first();
        $this->incidents[] = $incident;

        expect($incident)->not->toBeNull()
            ->and($incident->status)->toBe(IncidentStatus::Resolved)
            ->and($incident->resolvedAt)->not->toBeNull()
            ->and($incident->resolvedBy)->toBeNull();

        $entry = IncidentTimelineEntry::query()
            ->where('incidentId', $incident->id)
            ->where('type', IncidentTimelineEntryType::Resolved)
            ->first();

        expect($entry)->not->toBeNull()
            ->and($entry->message)->toBe('Incident auto-resolved because all grouped alerts cleared.')
            ->and($entry->userId)->toBeNull();
    });

    it('treats the current alert as cleared on resolve even if its state is still firing', function () {
        $fire = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $fire;

        $resolve = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_RESOLVE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $resolve;

        $incident = Incident::query()->where('policyId', $this->policy->id)->first();
        $this->incidents[] = $incident;

        expect($incident)->not->toBeNull()
            ->and($incident->status)->toBe(IncidentStatus::Resolved)
            ->and($this->alertRule->fresh()->state)->toBe('critical');
    });

    it('auto-resolves when a dual-purpose webhook reports the last grouped alert cleared', function () {
        $fire = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::GRAFANA_WEBHOOK,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $fire;

        $this->alertRule->update(['state' => 'resolved']);

        $resolve = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::GRAFANA_WEBHOOK,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $resolve;

        $incident = Incident::query()->where('policyId', $this->policy->id)->first();
        $this->incidents[] = $incident;

        expect($incident)->not->toBeNull()
            ->and($incident->status)->toBe(IncidentStatus::Resolved);
    });

    it('keeps the incident open while another grouped alert is still firing', function () {
        $tag = 'grp-'.uniqid();
        $other = IncidentPolicyTestData::createAlertRule($this->user);
        $this->extraAlertRules[] = $other;

        $this->alertRule->update(['state' => 'critical', 'tags' => [$tag]]);
        $other->update(['state' => 'critical', 'tags' => [$tag]]);
        $this->policy->update([
            'match' => [
                'alertRuleIds' => [],
                'tags' => [$tag],
                'serviceIds' => [],
                'dataSourceTypes' => [],
            ],
            'grouping' => ['key' => ['tag'], 'windowMinutes' => 15],
        ]);

        $this->notifies[] = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $other->fresh(),
            $other->id,
        );

        $this->alertRule->update(['state' => 'resolved']);

        $this->notifies[] = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_RESOLVE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );

        $incident = Incident::query()->where('policyId', $this->policy->id)->first();
        $this->incidents[] = $incident;

        expect($incident)->not->toBeNull()
            ->and($incident->status)->toBe(IncidentStatus::Open)
            ->and($incident->alertRuleIds)->toHaveCount(2);
    });

    it('does not auto-resolve when the policy flag is off', function () {
        $this->policy->update([
            'incident' => array_replace($this->policy->incident ?? [], [
                'autoResolveOnAlertClear' => false,
            ]),
        ]);

        $fire = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $fire;

        $this->alertRule->update(['state' => 'resolved']);

        $resolve = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_RESOLVE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $resolve;

        $incident = Incident::query()->where('policyId', $this->policy->id)->first();
        $this->incidents[] = $incident;

        expect($incident)->not->toBeNull()
            ->and($incident->status)->toBe(IncidentStatus::Open);
    });

    it('does not open an incident while applying replicated ha state', function () {
        $notify = HaReplicationContext::apply(fn () => app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_FIRE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        ));

        expect($notify)->toBeNull()
            ->and(Incident::query()->where('policyId', $this->policy->id)->exists())->toBeFalse();

        Queue::assertNothingPushed();
    });
});
