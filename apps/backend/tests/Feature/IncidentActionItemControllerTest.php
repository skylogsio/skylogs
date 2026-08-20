<?php

use App\Enums\Constants;
use App\Models\IncidentActionItem;
use Illuminate\Support\Facades\Cache;
use Tests\Support\IncidentTestData;
use Tests\Support\TeamTestData;

describe('Incident action items', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->outsider = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id, $this->member->id]);
        $this->incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
    });

    afterEach(function () {
        IncidentTestData::deleteIncident($this->incident);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->manager);
        TeamTestData::deleteUser($this->member);
        TeamTestData::deleteUser($this->outsider);
    });

    it('creates an action item with defaults filled in', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/action-item", [
                'title' => 'Add a circuit breaker to the payment client',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Add a circuit breaker to the payment client')
            ->assertJsonPath('data.incidentId', $this->incident->id)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'medium')
            ->assertJsonPath('data.category', 'other')
            ->assertJsonPath('data.dueAt', null)
            ->assertJsonPath('data.completedAt', null)
            ->assertJsonPath('data.createdBy', $this->manager->id)
            ->assertJsonPath('data.canEdit', true);
    });

    it('creates an action item with an owner, team, due date and category', function () {
        $dueAt = now()->addWeek();

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/action-item", [
                'title' => 'Raise the connection pool ceiling',
                'description' => 'The pool saturated at 200 connections during the peak.',
                'ownerId' => $this->member->id,
                'teamId' => $this->team->id,
                'priority' => 'high',
                'category' => 'prevention',
                'dueAt' => $dueAt->toISOString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.ownerId', $this->member->id)
            ->assertJsonPath('data.ownerUser.id', $this->member->id)
            ->assertJsonPath('data.teamId', $this->team->id)
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.category', 'prevention');
    });

    it('stamps completedAt when an item is created as done', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/action-item", [
                'title' => 'Already handled during the incident',
                'status' => 'done',
            ])
            ->assertStatus(201);

        expect($response->json('data.completedAt'))->not->toBeNull();
    });

    it('requires a title and validates the enums', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/action-item", [
                'priority' => 'urgent-ish',
                'status' => 'pending',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'priority', 'status']);
    });

    it('rejects an owner or team that does not exist', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/action-item", [
                'title' => 'Unassignable',
                'ownerId' => '000000000000000000000000',
                'teamId' => '000000000000000000000000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ownerId', 'teamId']);
    });

    it('rejects a postmortem that belongs to another incident', function () {
        $other = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $postMortem = IncidentTestData::createPostMortem($other, $this->manager->id);

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/action-item", [
                'title' => 'Belongs elsewhere',
                'postMortemId' => $postMortem->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['postMortemId']);

        IncidentTestData::deleteIncident($other);
    });

    it('links an action item to the postmortem of its own incident', function () {
        $postMortem = IncidentTestData::createPostMortem($this->incident, $this->manager->id);

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/action-item", [
                'title' => 'Follow-up from the review',
                'postMortemId' => $postMortem->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.postMortemId', $postMortem->id);
    });

    it('updates an item and stamps completion once, then clears it on reopen', function () {
        $actionItem = IncidentTestData::createActionItem($this->incident, [
            'title' => 'Tune the alert threshold',
            'createdBy' => $this->manager->id,
        ]);

        $done = $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/action-item/{$actionItem->id}", [
                'title' => 'Tune the alert threshold',
                'status' => 'done',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'done');

        $completedAt = $done->json('data.completedAt');
        expect($completedAt)->not->toBeNull();

        $stillDone = $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/action-item/{$actionItem->id}", [
                'title' => 'Tune the alert threshold again',
                'status' => 'done',
            ])
            ->assertSuccessful();

        expect($stillDone->json('data.completedAt'))->toBe($completedAt);

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/action-item/{$actionItem->id}", [
                'title' => 'Tune the alert threshold again',
                'status' => 'inProgress',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.completedAt', null);
    });

    it('deletes an action item', function () {
        $actionItem = IncidentTestData::createActionItem($this->incident, ['createdBy' => $this->manager->id]);

        $this->actingAs($this->manager, 'api')
            ->deleteJson("/api/v1/incident/{$this->incident->id}/action-item/{$actionItem->id}")
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        expect(IncidentActionItem::find($actionItem->id))->toBeNull();
    });

    it('does not touch an item that belongs to another incident', function () {
        $other = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $actionItem = IncidentTestData::createActionItem($other, ['createdBy' => $this->manager->id]);

        $this->actingAs($this->manager, 'api')
            ->deleteJson("/api/v1/incident/{$this->incident->id}/action-item/{$actionItem->id}")
            ->assertNotFound();

        IncidentTestData::deleteIncident($other);
    });

    it('lists items for the incident and filters by status', function () {
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Open work',
            'createdBy' => $this->manager->id,
        ]);
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Finished work',
            'status' => 'done',
            'createdBy' => $this->manager->id,
        ]);

        $all = $this->actingAs($this->member, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}/action-item")
            ->assertSuccessful()
            ->json('data');

        expect($all)->toHaveCount(2)
            ->and($all[0]['canEdit'])->toBeFalse();

        $open = $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}/action-item?status=open")
            ->json('data');

        expect(collect($open)->pluck('title')->all())->toBe(['Open work']);
    });

    it('lets a team member read but not write', function () {
        $actionItem = IncidentTestData::createActionItem($this->incident, ['createdBy' => $this->manager->id]);

        $this->actingAs($this->member, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/action-item", ['title' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($this->member, 'api')
            ->deleteJson("/api/v1/incident/{$this->incident->id}/action-item/{$actionItem->id}")
            ->assertForbidden();
    });

    it('forbids outsiders from listing incident action items', function () {
        $this->actingAs($this->outsider, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}/action-item")
            ->assertForbidden();
    });
});

describe('Cross-incident action items', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->outsider = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id, $this->member->id]);
        $this->incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
    });

    afterEach(function () {
        IncidentTestData::deleteIncident($this->incident);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->manager);
        TeamTestData::deleteUser($this->member);
        TeamTestData::deleteUser($this->outsider);
    });

    it('returns items the user owns, created, or that belong to their team', function () {
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Owned by the member',
            'ownerId' => $this->member->id,
        ]);
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Assigned to the team',
            'teamId' => $this->team->id,
        ]);
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Somebody else entirely',
            'ownerId' => $this->outsider->id,
        ]);

        $titles = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/incident-action-item')
            ->assertSuccessful()
            ->json('data.*.title');

        expect($titles)->toContain('Owned by the member')
            ->and($titles)->toContain('Assigned to the team')
            ->and($titles)->not->toContain('Somebody else entirely');
    });

    it('includes a summary of the parent incident', function () {
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'With incident context',
            'ownerId' => $this->member->id,
        ]);

        $item = collect(
            $this->actingAs($this->member, 'api')
                ->getJson('/api/v1/incident-action-item')
                ->json('data')
        )->firstWhere('title', 'With incident context');

        expect($item['incident']['id'])->toBe($this->incident->id)
            ->and($item['incident']['title'])->toBe($this->incident->title);
    });

    it('filters down to open items only', function () {
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Still open',
            'ownerId' => $this->member->id,
        ]);
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Closed out',
            'status' => 'done',
            'ownerId' => $this->member->id,
        ]);

        $titles = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/incident-action-item?open=1')
            ->assertSuccessful()
            ->json('data.*.title');

        expect($titles)->toContain('Still open')
            ->and($titles)->not->toContain('Closed out');
    });

    it('filters down to overdue items only', function () {
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Overdue work',
            'ownerId' => $this->member->id,
            'dueAt' => now()->subDays(3),
        ]);
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Due next week',
            'ownerId' => $this->member->id,
            'dueAt' => now()->addWeek(),
        ]);

        $titles = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/incident-action-item?overdue=1')
            ->assertSuccessful()
            ->json('data.*.title');

        expect($titles)->toContain('Overdue work')
            ->and($titles)->not->toContain('Due next week');
    });

    it('searches by title and filters by incident', function () {
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Rotate the database credentials',
            'ownerId' => $this->member->id,
        ]);
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Write the customer update',
            'ownerId' => $this->member->id,
        ]);

        $titles = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/incident-action-item?search=credentials&incidentId='.$this->incident->id)
            ->assertSuccessful()
            ->json('data.*.title');

        expect($titles)->toBe(['Rotate the database credentials']);
    });

    it('returns nothing for a user with no items of their own', function () {
        IncidentTestData::createActionItem($this->incident, [
            'title' => 'Not theirs',
            'ownerId' => $this->member->id,
        ]);

        $titles = $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/incident-action-item')
            ->assertSuccessful()
            ->json('data.*.title');

        expect($titles)->not->toContain('Not theirs');
    });
});
