<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentTimelineEntryType;
use App\Models\IncidentTimelineEntry;
use App\Services\IncidentPolicy\AlertMatchContext;
use App\Services\IncidentPolicy\PolicyIncidentOpener;
use Tests\Support\IncidentPolicyTestData;
use Tests\Support\IncidentTestData;
use Tests\Support\TeamTestData;

describe('PolicyIncidentOpener', function () {
    beforeEach(function () {
        $this->user = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->user);
        $this->tag = 'tag-'.uniqid();
        $this->policies = [];
        $this->incidents = [];
        $this->alertRules = [];
        $this->opener = app(PolicyIncidentOpener::class);
    });

    afterEach(function () {
        foreach ($this->incidents as $incident) {
            IncidentTestData::deleteIncident($incident);
        }
        foreach ($this->policies as $policy) {
            IncidentPolicyTestData::deletePolicy($policy);
        }
        foreach ($this->alertRules as $alertRule) {
            IncidentPolicyTestData::deleteAlertRule($alertRule);
        }
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->user);
    });

    it('opens a policy-sourced incident from a matching alert', function () {
        $alertRule = IncidentPolicyTestData::createAlertRule($this->user);
        $alertRule->update([
            'name' => 'payments-5xx',
            'type' => AlertRuleType::API,
            'state' => 'critical',
            'tags' => [$this->tag],
        ]);
        $this->alertRules[] = $alertRule->fresh();

        $policy = IncidentPolicyTestData::createPolicy([
            'name' => 'payments-policy-'.uniqid(),
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'incident' => [
                'autoCreate' => true,
                'titleTemplate' => '{{name}} [{{severity}}]',
                'defaultSeverity' => IncidentSeverity::Sev3->value,
                'severityMap' => ['critical' => IncidentSeverity::Sev1->value],
            ],
        ]);
        $this->policies[] = $policy;

        $incidents = $this->opener->open(AlertMatchContext::fromAlertRule($alertRule->fresh()));
        $this->incidents = $incidents->all();

        expect($incidents)->toHaveCount(1);

        $incident = $incidents->first();

        expect($incident->title)->toBe('payments-5xx [SEV1]')
            ->and($incident->source)->toBe(IncidentSource::Policy)
            ->and($incident->status)->toBe(IncidentStatus::Open)
            ->and($incident->severity)->toBe(IncidentSeverity::Sev1)
            ->and($incident->policyId)->toBe($policy->id)
            ->and($incident->groupingKey)->not->toBeNull()
            ->and($incident->createdBy)->toBeNull()
            ->and($incident->teamIds)->toBe([$this->team->id])
            ->and($incident->alertRuleIds)->toBe([$alertRule->id])
            ->and($incident->tags)->toBe([$this->tag]);

        $entry = IncidentTimelineEntry::query()->where('incidentId', $incident->id)->first();

        expect($entry)->not->toBeNull()
            ->and($entry->type)->toBe(IncidentTimelineEntryType::Created)
            ->and($entry->message)->toContain($policy->name);
    });

    it('falls back to default severity and the alert name when there is no map or template', function () {
        $policy = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'incident' => [
                'autoCreate' => true,
                'defaultSeverity' => IncidentSeverity::Sev2->value,
                'severityMap' => [],
            ],
        ]);
        $this->policies[] = $policy;

        $incidents = $this->opener->open(new AlertMatchContext(
            tags: [$this->tag],
            alertName: 'disk-full',
            alertState: 'warning',
        ));
        $this->incidents = $incidents->all();

        expect($incidents)->toHaveCount(1)
            ->and($incidents->first()->title)->toBe('disk-full')
            ->and($incidents->first()->severity)->toBe(IncidentSeverity::Sev2);
    });

    it('opens one incident per matching policy', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
        ]);
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
        ]);

        $incidents = $this->opener->open(new AlertMatchContext(
            tags: [$this->tag],
            alertName: 'shared-alert',
        ));
        $this->incidents = $incidents->all();

        expect($incidents)->toHaveCount(2)
            ->and($incidents->pluck('policyId')->unique()->count())->toBe(2);
    });

    it('opens nothing when no policy matches', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => ['other-'.uniqid()]],
        ]);

        $incidents = $this->opener->open(new AlertMatchContext(tags: [$this->tag]));

        expect($incidents)->toHaveCount(0);
    });

    it('joins a second fire into the same incident inside the grouping window', function () {
        $policy = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'grouping' => ['key' => ['tag'], 'windowMinutes' => 15],
        ]);
        $this->policies[] = $policy;

        $first = $this->opener->open(new AlertMatchContext(
            alertRuleId: bin2hex(random_bytes(12)),
            tags: [$this->tag],
            alertName: 'first-fire',
        ));
        $second = $this->opener->open(new AlertMatchContext(
            alertRuleId: bin2hex(random_bytes(12)),
            tags: [$this->tag],
            alertName: 'second-fire',
        ));
        $this->incidents = $first->concat($second)->unique('id')->values()->all();

        expect($first)->toHaveCount(1)
            ->and($second)->toHaveCount(1)
            ->and($second->first()->id)->toBe($first->first()->id)
            ->and($second->first()->alertRuleIds)->toHaveCount(2);

        $detections = IncidentTimelineEntry::query()
            ->where('incidentId', $first->first()->id)
            ->where('type', IncidentTimelineEntryType::Detection)
            ->get();

        expect($detections)->toHaveCount(1)
            ->and($detections->first()->message)->toContain('second-fire');
    });

    it('opens a new incident when grouping key values differ', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag, 'other-'.$this->tag]],
            'grouping' => ['key' => ['alertRuleId'], 'windowMinutes' => 15],
        ]);

        $ruleA = bin2hex(random_bytes(12));
        $ruleB = bin2hex(random_bytes(12));

        $first = $this->opener->open(new AlertMatchContext(
            alertRuleId: $ruleA,
            tags: [$this->tag],
            alertName: 'rule-a',
        ));
        $second = $this->opener->open(new AlertMatchContext(
            alertRuleId: $ruleB,
            tags: [$this->tag],
            alertName: 'rule-b',
        ));
        $this->incidents = $first->concat($second)->all();

        expect($this->incidents)->toHaveCount(2)
            ->and($first->first()->id)->not->toBe($second->first()->id);
    });

    it('opens a new incident after the grouping window has passed', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'grouping' => ['key' => ['tag'], 'windowMinutes' => 15],
        ]);

        $first = $this->opener->open(new AlertMatchContext(
            tags: [$this->tag],
            alertName: 'inside-window',
        ));

        $this->travel(16)->minutes();

        $second = $this->opener->open(new AlertMatchContext(
            tags: [$this->tag],
            alertName: 'after-window',
        ));
        $this->incidents = $first->concat($second)->all();
        $this->travelBack();

        expect($this->incidents)->toHaveCount(2)
            ->and($first->first()->id)->not->toBe($second->first()->id);
    });

    it('does not join a resolved incident', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'grouping' => ['key' => ['tag'], 'windowMinutes' => 15],
        ]);

        $first = $this->opener->open(new AlertMatchContext(
            tags: [$this->tag],
            alertName: 'original',
        ));
        $first->first()->update(['status' => IncidentStatus::Resolved]);

        $second = $this->opener->open(new AlertMatchContext(
            tags: [$this->tag],
            alertName: 'after-resolve',
        ));
        $this->incidents = $first->concat($second)->all();

        expect($this->incidents)->toHaveCount(2)
            ->and($second->first()->status)->toBe(IncidentStatus::Open);
    });

    it('raises severity when a grouped fire maps higher', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'teamIds' => [$this->team->id],
            'match' => ['tags' => [$this->tag]],
            'grouping' => ['key' => ['tag'], 'windowMinutes' => 15],
            'incident' => [
                'defaultSeverity' => IncidentSeverity::Sev3->value,
                'severityMap' => [
                    'warning' => IncidentSeverity::Sev3->value,
                    'critical' => IncidentSeverity::Sev1->value,
                ],
            ],
        ]);

        $first = $this->opener->open(new AlertMatchContext(
            tags: [$this->tag],
            alertName: 'warn',
            alertState: 'warning',
        ));
        $second = $this->opener->open(new AlertMatchContext(
            tags: [$this->tag],
            alertName: 'crit',
            alertState: 'critical',
        ));
        $this->incidents = $first->concat($second)->unique('id')->values()->all();

        expect($second->first()->id)->toBe($first->first()->id)
            ->and($second->first()->severity)->toBe(IncidentSeverity::Sev1);
    });
});
