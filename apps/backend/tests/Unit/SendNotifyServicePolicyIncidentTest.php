<?php

use App\Enums\Constants;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Jobs\SendNotifyJob;
use App\Models\Incident;
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
    });

    afterEach(function () {
        foreach ($this->incidents as $incident) {
            IncidentTestData::deleteIncident($incident);
        }
        foreach ($this->notifies as $notify) {
            Notify::query()->where('_id', $notify->id)->delete();
        }
        IncidentPolicyTestData::deletePolicy($this->policy);
        IncidentPolicyTestData::deleteAlertRule($this->alertRule);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->user);
    });

    it('does not open an incident when a matching alert fires', function (string $type) {
        $notify = app(SendNotifyService::class)->createNotify(
            $type,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );
        $this->notifies[] = $notify;

        expect($notify)->not->toBeNull()
            ->and(Incident::query()->where('policyId', $this->policy->id)->exists())->toBeFalse();

        Queue::assertPushed(SendNotifyJob::class);
    })->with([
        'api fire' => SendNotifyJob::API_FIRE,
        'prometheus fire' => SendNotifyJob::PROMETHEUS_FIRE,
        'grafana webhook' => SendNotifyJob::GRAFANA_WEBHOOK,
    ]);

    it('does not resolve an open policy incident when the alert clears', function () {
        $incident = IncidentTestData::createIncident((string) $this->user->id, [$this->team->id], [
            'alertRuleIds' => [$this->alertRule->id],
            'source' => IncidentSource::Policy,
        ]);
        $incident->update(['policyId' => (string) $this->policy->id]);
        $this->incidents[] = $incident;

        $this->alertRule->update(['state' => 'resolved']);

        $this->notifies[] = app(SendNotifyService::class)->createNotify(
            SendNotifyJob::API_RESOLVE,
            $this->alertRule->fresh(),
            $this->alertRule->id,
        );

        expect($incident->fresh()->status)->toBe(IncidentStatus::Open)
            ->and($incident->fresh()->resolvedAt)->toBeNull();
    });

    it('does not notify while applying replicated ha state', function () {
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
