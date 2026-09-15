<?php

use App\Enums\Constants;
use App\Models\OnCallPlan;
use Illuminate\Support\Facades\Cache;
use Tests\Support\IncidentPolicyTestData;
use Tests\Support\OnCallPlanTestData;
use Tests\Support\TeamTestData;

describe('OnCallPlanController', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();
        $this->withHeader('Accept', 'application/json');

        $this->owner = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->outsider = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->owner->update(['name' => 'Alice-'.uniqid()]);
        $this->member->update(['name' => 'Bob-'.uniqid()]);
        $this->team = TeamTestData::createTeam($this->owner, [$this->owner->id, $this->member->id]);
        $this->ownerEndpoint = IncidentPolicyTestData::createEndpoint($this->owner, 'alice-sms');
        $this->memberEndpoint = IncidentPolicyTestData::createEndpoint($this->member, 'bob-sms');
        $this->ownerEndpoint->update(['onCall' => true]);
        $this->memberEndpoint->update(['onCall' => true]);
        $this->files = [];
        $this->weekdayRoster = [
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['Mon 00:00–08:00', $this->owner->name],
                    ['Mon 08:00–16:00', $this->member->name],
                ],
            ],
            [
                'title' => 'Layer 2',
                'rows' => [
                    ['Mon 00:00–24:00', $this->member->name],
                ],
            ],
        ];
    });

    afterEach(function () {
        foreach ($this->files as $payload) {
            OnCallPlanTestData::unlinkFile($payload);
        }
        OnCallPlanTestData::deleteForTeam($this->team);
        IncidentPolicyTestData::deleteEndpoint($this->ownerEndpoint);
        IncidentPolicyTestData::deleteEndpoint($this->memberEndpoint);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->owner);
        TeamTestData::deleteUser($this->member);
        TeamTestData::deleteUser($this->outsider);
    });

    it('creates a plan from excel plus the other fields in one request', function () {
        $payload = OnCallPlanTestData::request($this->weekdayRoster, [
            'name' => 'payments-oncall',
            'timezone' => 'Asia/Tehran',
            'layerDelays' => [5, 15],
        ]);
        $this->files[] = $payload;

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $payload)
            ->assertCreated()
            ->assertJsonPath('data.teamId', $this->team->id)
            ->assertJsonPath('data.name', 'payments-oncall')
            ->assertJsonPath('data.timezone', 'Asia/Tehran')
            ->assertJsonPath('data.layers.0.escalateAfterMinutes', 5)
            ->assertJsonPath('data.layers.1.escalateAfterMinutes', 15)
            ->assertJsonPath('data.isComplete', true)
            ->assertJsonPath('data.roster.0.endpoint.id', $this->ownerEndpoint->id);
    });

    it('rejects create without the excel file', function () {
        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", [
                'name' => 'missing-file',
                'timezone' => 'UTC',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    });

    it('rejects a second plan for the same team', function () {
        $first = OnCallPlanTestData::request($this->weekdayRoster);
        $second = OnCallPlanTestData::request($this->weekdayRoster);
        $this->files[] = $first;
        $this->files[] = $second;

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $first)
            ->assertCreated();

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $second)
            ->assertUnprocessable()
            ->assertJsonPath('errors.teamId.0', 'This team already has an on-call plan.');
    });

    it('rejects an excel user who is not on the team', function () {
        $payload = OnCallPlanTestData::request([
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['Mon 00:00–08:00', 'Nobody-Here'],
                ],
            ],
        ]);
        $this->files[] = $payload;

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.row', 2);
    });

    it('rejects overlapping windows in a layer', function () {
        $payload = OnCallPlanTestData::request([
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['Mon 08:00–16:00', $this->owner->name],
                    ['Mon 12:00–20:00', $this->member->name],
                ],
            ],
        ]);
        $this->files[] = $payload;

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['layers.0.entries']);
    });

    it('forbids a member from creating the plan', function () {
        $payload = OnCallPlanTestData::request($this->weekdayRoster);
        $this->files[] = $payload;

        $this->actingAs($this->member, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $payload)
            ->assertForbidden();
    });

    it('forbids an outsider from viewing the plan', function () {
        $payload = OnCallPlanTestData::request($this->weekdayRoster);
        $this->files[] = $payload;

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $payload)
            ->assertCreated();

        $this->actingAs($this->outsider, 'api')
            ->getJson("/api/v1/team/{$this->team->id}/on-call-plan")
            ->assertForbidden();
    });

    it('marks the plan incomplete until roster users have an on-call endpoint', function () {
        $this->memberEndpoint->update(['onCall' => false]);

        $payload = OnCallPlanTestData::request([
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['Mon 00:00–24:00', $this->member->name],
                ],
            ],
        ]);
        $this->files[] = $payload;

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $payload)
            ->assertCreated()
            ->assertJsonPath('data.isComplete', false)
            ->assertJsonPath('data.roster.0.endpoint', null);
    });

    it('resolves who is on call using the user on-call endpoint', function () {
        $payload = OnCallPlanTestData::request($this->weekdayRoster, [
            'timezone' => 'Asia/Tehran',
            'layerDelays' => [5, 15],
        ]);
        $this->files[] = $payload;

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $payload)
            ->assertCreated();

        $this->actingAs($this->member, 'api')
            ->getJson("/api/v1/team/{$this->team->id}/on-call-plan/at?at=2026-08-31T04:30:00Z")
            ->assertSuccessful()
            ->assertJsonPath('layers.0.onCall.userId', $this->member->id)
            ->assertJsonPath('layers.0.onCall.endpoint.id', $this->memberEndpoint->id)
            ->assertJsonPath('timezone', 'Asia/Tehran');

        $this->actingAs($this->member, 'api')
            ->getJson("/api/v1/team/{$this->team->id}/on-call-plan/at?at=2026-08-31T03:30:00Z")
            ->assertSuccessful()
            ->assertJsonPath('layers.0.onCall.userId', $this->owner->id);
    });

    it('returns a gap when nobody is on call', function () {
        $payload = OnCallPlanTestData::request([
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['Mon 00:00–08:00', $this->owner->name],
                ],
            ],
        ]);
        $this->files[] = $payload;

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $payload)
            ->assertCreated();

        $this->actingAs($this->owner, 'api')
            ->getJson("/api/v1/team/{$this->team->id}/on-call-plan/at?at=2026-08-30T12:00:00Z")
            ->assertSuccessful()
            ->assertJsonPath('layers.0.onCall', null);
    });

    it('lists current on-call for visible teams', function () {
        $payload = OnCallPlanTestData::request([
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['Mon 00:00–24:00', $this->owner->name],
                    ['Tue 00:00–24:00', $this->owner->name],
                    ['Wed 00:00–24:00', $this->owner->name],
                    ['Thu 00:00–24:00', $this->owner->name],
                    ['Fri 00:00–24:00', $this->owner->name],
                    ['Sat 00:00–24:00', $this->owner->name],
                    ['Sun 00:00–24:00', $this->owner->name],
                ],
            ],
        ]);
        $this->files[] = $payload;

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $payload)
            ->assertCreated();

        $this->actingAs($this->owner, 'api')
            ->getJson('/api/v1/on-call-plan/current?teamIds[]='.$this->team->id)
            ->assertSuccessful()
            ->assertJsonPath('data.0.teamId', $this->team->id)
            ->assertJsonPath('data.0.layers.0.onCall.userId', $this->owner->id);
    });

    it('replaces the roster on update with excel and fields together', function () {
        $create = OnCallPlanTestData::request($this->weekdayRoster, ['layerDelays' => [5]]);
        $this->files[] = $create;

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $create)
            ->assertCreated();

        $update = OnCallPlanTestData::request([
            [
                'title' => 'Layer 1',
                'rows' => [
                    ['Tue 00:00–24:00', $this->owner->name],
                ],
            ],
        ], [
            'name' => 'updated-plan',
            'timezone' => 'Asia/Tehran',
            'layerDelays' => [10],
        ]);
        $this->files[] = $update;

        $this->actingAs($this->owner, 'api')
            ->put("/api/v1/team/{$this->team->id}/on-call-plan", $update)
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'updated-plan')
            ->assertJsonPath('data.layers.0.escalateAfterMinutes', 10)
            ->assertJsonPath('data.roster.0.endpoint.id', $this->ownerEndpoint->id);
    });

    it('deletes the plan', function () {
        $payload = OnCallPlanTestData::request($this->weekdayRoster);
        $this->files[] = $payload;

        $this->actingAs($this->owner, 'api')
            ->post("/api/v1/team/{$this->team->id}/on-call-plan", $payload)
            ->assertCreated();

        $this->actingAs($this->owner, 'api')
            ->deleteJson("/api/v1/team/{$this->team->id}/on-call-plan")
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        expect(OnCallPlan::query()->where('teamId', $this->team->id)->exists())->toBeFalse();
    });
});
