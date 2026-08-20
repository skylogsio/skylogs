<?php

use App\Enums\Constants;
use App\Enums\IncidentTimelineEntryType;
use App\Models\Incident;
use Illuminate\Support\Facades\Cache;
use Tests\Support\IncidentTestData;
use Tests\Support\TeamTestData;

describe('Incident timeline', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->outsider = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id, $this->member->id]);
        $this->incidents = [];

        $this->types = fn (array $data) => collect($data)->pluck('type')->all();
    });

    afterEach(function () {
        foreach ($this->incidents as $incident) {
            IncidentTestData::deleteIncident($incident);
        }
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->manager);
        TeamTestData::deleteUser($this->member);
        TeamTestData::deleteUser($this->outsider);
    });

    it('records a system entry when an incident is reported', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'Timeline on create',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV2',
            ])
            ->assertStatus(201);

        $incident = Incident::find($response->json('data.id'));
        $this->incidents[] = $incident;

        $entries = $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$incident->id}/timeline")
            ->assertSuccessful()
            ->json('data');

        expect($entries)->toHaveCount(1)
            ->and($entries[0]['type'])->toBe('created')
            ->and($entries[0]['source'])->toBe('system')
            ->and($entries[0]['userId'])->toBe($this->manager->id)
            ->and($entries[0]['meta']['severity'])->toBe('SEV2');
    });

    it('records acknowledgement and the status change it caused', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/acknowledge")
            ->assertSuccessful();

        $entries = $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$incident->id}/timeline")
            ->assertSuccessful()
            ->json('data');

        expect(($this->types)($entries))->toContain('acknowledged')
            ->and(($this->types)($entries))->toContain('statusChanged');

        $statusChange = collect($entries)->firstWhere('type', 'statusChanged');
        expect($statusChange['meta']['from'])->toBe('open')
            ->and($statusChange['meta']['to'])->toBe('investigating');
    });

    it('records resolution', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/resolve")
            ->assertSuccessful();

        $entries = $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$incident->id}/timeline")
            ->json('data');

        expect(($this->types)($entries))->toContain('resolved');
    });

    it('lets any team member post a note', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->member, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/timeline", [
                'type' => 'note',
                'message' => 'Traffic is being drained to the secondary region.',
                'isPublic' => true,
                'meta' => ['region' => 'eu-west-1'],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'note')
            ->assertJsonPath('data.source', 'user')
            ->assertJsonPath('data.isPublic', true)
            ->assertJsonPath('data.userId', $this->member->id)
            ->assertJsonPath('data.user.id', $this->member->id)
            ->assertJsonPath('data.meta.region', 'eu-west-1');
    });

    it('accepts a backdated entry', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $occurredAt = now()->subHours(3);

        $response = $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/timeline", [
                'type' => 'detection',
                'message' => 'The first customer report arrived.',
                'occurredAt' => $occurredAt->toISOString(),
            ])
            ->assertStatus(201);

        expect($response->json('data.occurredAt'))->not->toBeNull();
    });

    it('rejects a type that only the system writes', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/timeline", [
                'type' => IncidentTimelineEntryType::Resolved->value,
                'message' => 'Pretending to resolve.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });

    it('requires a type and a message', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/timeline", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'message']);
    });

    it('filters entries by type', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        IncidentTestData::createTimelineEntry($incident, ['type' => IncidentTimelineEntryType::Note]);
        IncidentTestData::createTimelineEntry($incident, ['type' => IncidentTimelineEntryType::Mitigation]);

        $entries = $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$incident->id}/timeline?type=note")
            ->assertSuccessful()
            ->json('data');

        expect(($this->types)($entries))->toBe(['note']);
    });

    it('orders entries by when they happened, not when they were written', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        IncidentTestData::createTimelineEntry($incident, [
            'message' => 'later',
            'occurredAt' => now()->subMinutes(10),
        ]);
        IncidentTestData::createTimelineEntry($incident, [
            'message' => 'earlier',
            'occurredAt' => now()->subHours(2),
        ]);

        $entries = $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$incident->id}/timeline")
            ->json('data');

        expect(collect($entries)->pluck('message')->all())->toBe(['earlier', 'later']);
    });

    it('forbids outsiders from reading or writing the timeline', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->outsider, 'api')
            ->getJson("/api/v1/incident/{$incident->id}/timeline")
            ->assertForbidden();

        $this->actingAs($this->outsider, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/timeline", [
                'type' => 'note',
                'message' => 'Should not land.',
            ])
            ->assertForbidden();
    });
});
