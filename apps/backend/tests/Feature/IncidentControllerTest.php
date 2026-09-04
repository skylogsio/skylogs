<?php

use App\Enums\Constants;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentActionItem;
use App\Models\IncidentDocument;
use App\Models\IncidentTimelineEntry;
use App\Models\PostMortem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IncidentTestData;
use Tests\Support\TeamTestData;

describe('IncidentController', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->outsider = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id, $this->member->id]);
        $this->incidents = [];
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

    it('creates an incident with required fields', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'Server outage',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV1',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Server outage')
            ->assertJsonPath('data.severity', 'SEV1')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.canEdit', true)
            ->assertJsonPath('data.canAcknowledge', true);

        $this->incidents[] = Incident::find($response->json('data.id'));
    });

    it('defaults startedAt and detectedAt to now when not provided', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'Default start',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV2',
            ])
            ->assertStatus(201);

        expect($response->json('data.startedAt'))->not->toBeNull()
            ->and($response->json('data.detectedAt'))->not->toBeNull()
            ->and($response->json('data.resolvedAt'))->toBeNull()
            ->and($response->json('data.acknowledgements'))->toBe([]);

        $this->incidents[] = Incident::find($response->json('data.id'));
    });

    it('creates a resolved incident when resolvedAt is provided', function () {
        $resolvedAt = now()->subHour()->toISOString();

        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'Already resolved',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV3',
                'startedAt' => now()->subHours(2)->toISOString(),
                'detectedAt' => now()->subHours(2)->toISOString(),
                'resolvedAt' => $resolvedAt,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolvedBy', $this->manager->id);

        expect($response->json('data.resolvedAt'))->not->toBeNull();

        $this->incidents[] = Incident::find($response->json('data.id'));
    });

    it('creates an open incident when swagger-style identical timestamps include resolvedAt', function () {
        $now = now()->toISOString();

        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'Swagger timestamps',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV1',
                'startedAt' => $now,
                'detectedAt' => $now,
                'resolvedAt' => $now,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.resolvedAt', null)
            ->assertJsonPath('data.canAcknowledge', true)
            ->assertJsonPath('data.canResolve', true);

        $this->incidents[] = Incident::find($response->json('data.id'));
    });

    it('acknowledges then resolves an incident created with identical timestamps', function () {
        $now = now()->toISOString();

        $create = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'Ack then resolve',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV2',
                'startedAt' => $now,
                'detectedAt' => $now,
                'resolvedAt' => $now,
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');
        $this->incidents[] = Incident::find($id);

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$id}/acknowledge")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'investigating');

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$id}/resolve")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'resolved');
    });

    it('rejects creation with missing required fields', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'teamIds', 'severity']);
    });

    it('rejects creation with invalid severity', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'Bad severity',
                'teamIds' => [$this->team->id],
                'severity' => 'INVALID',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['severity']);
    });

    it('forbids outsiders from creating incidents for a team they do not belong to', function () {
        $this->actingAs($this->outsider, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'Unauthorized',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV3',
            ])
            ->assertForbidden();
    });

    it('lists incidents for team members', function () {
        $title = 'list-member-'.uniqid();
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id], [
            'title' => $title,
        ]);
        $this->incidents[] = $incident;

        $response = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/incident?search='.urlencode($title))
            ->assertSuccessful()
            ->assertJsonStructure(laravelPaginatorStructure());

        $ids = collect($response->json('data'))->pluck('id')->all();
        expect($ids)->toContain($incident->id);
    });

    it('filters incidents by status', function () {
        $prefix = 'status-filter-'.uniqid();
        $open = IncidentTestData::createIncident($this->manager->id, [$this->team->id], [
            'title' => $prefix.'-open',
        ]);
        $resolved = IncidentTestData::createIncident($this->manager->id, [$this->team->id], [
            'title' => $prefix.'-resolved',
            'status' => IncidentStatus::Resolved,
        ]);
        $this->incidents[] = $open;
        $this->incidents[] = $resolved;

        $response = $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/incident?status=open&search='.urlencode($prefix))
            ->assertSuccessful();

        $ids = collect($response->json('data'))->pluck('id')->all();
        expect($ids)->toContain($open->id)
            ->and($ids)->not->toContain($resolved->id);
    });

    it('shows an incident to a team member', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->member, 'api')
            ->getJson("/api/v1/incident/{$incident->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.id', $incident->id);
    });

    it('forbids outsiders from viewing an incident', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->outsider, 'api')
            ->getJson("/api/v1/incident/{$incident->id}")
            ->assertForbidden();
    });

    it('updates an incident', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$incident->id}", [
                'title' => 'Updated title',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV4',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.severity', 'SEV4');
    });

    it('forbids outsiders from updating an incident', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->outsider, 'api')
            ->putJson("/api/v1/incident/{$incident->id}", [
                'title' => 'Hack',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV1',
            ])
            ->assertForbidden();
    });

    it('deletes an incident', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->deleteJson("/api/v1/incident/{$incident->id}")
            ->assertSuccessful()
            ->assertJsonPath('status', true);
    });

    it('forbids outsiders from deleting an incident', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->outsider, 'api')
            ->deleteJson("/api/v1/incident/{$incident->id}")
            ->assertForbidden();
    });
});

describe('Incident status transitions', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id]);
        $this->incidents = [];
    });

    afterEach(function () {
        foreach ($this->incidents as $incident) {
            IncidentTestData::deleteIncident($incident);
        }
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->manager);
    });

    it('acknowledges an open incident for the caller team and sets status to investigating', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $response = $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/acknowledge")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'investigating');

        $acknowledgements = $response->json('data.acknowledgements');
        expect($acknowledgements)->toHaveCount(1)
            ->and($acknowledgements[0]['teamId'])->toBe($this->team->id)
            ->and($acknowledgements[0]['acknowledgedBy'])->toBe($this->manager->id)
            ->and($acknowledgements[0]['acknowledgedAt'])->not->toBeNull();

        $teamPayload = collect($response->json('data.teams'))->firstWhere('id', $this->team->id);
        expect($teamPayload['acknowledgement']['acknowledgedBy'])->toBe($this->manager->id);
    });

    it('stores acknowledgements per team independently', function () {
        $secondMember = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $secondTeam = TeamTestData::createTeam($secondMember, [$secondMember->id]);

        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id, $secondTeam->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/acknowledge", [
                'teamId' => $this->team->id,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'investigating');

        $response = $this->actingAs($secondMember, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/acknowledge", [
                'teamId' => $secondTeam->id,
            ])
            ->assertSuccessful();

        expect($response->json('data.acknowledgements'))->toHaveCount(2);

        TeamTestData::deleteTeam($secondTeam);
        TeamTestData::deleteUser($secondMember);
    });

    it('resolves an open incident directly', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/resolve")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolvedBy', $this->manager->id);

        expect($this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$incident->id}")
            ->json('data.resolvedAt'))->not->toBeNull();
    });

    it('resolves with an explicit resolvedAt', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;
        $resolvedAt = now()->subMinutes(5)->toISOString();

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/resolve", [
                'resolvedAt' => $resolvedAt,
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'resolved');

        expect($this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$incident->id}")
            ->json('data.resolvedAt'))->not->toBeNull();
    });

    it('resolves an investigating incident', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id], [
            'status' => IncidentStatus::Investigating,
        ]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/resolve")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'resolved');
    });

    it('forbids acknowledging a resolved incident', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id], [
            'status' => IncidentStatus::Resolved,
        ]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/acknowledge")
            ->assertForbidden();
    });

    it('forbids resolving an already resolved incident', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id], [
            'status' => IncidentStatus::Resolved,
        ]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/resolve")
            ->assertForbidden();
    });
});

describe('Incident documentation surface', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id]);
        $this->incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
    });

    afterEach(function () {
        IncidentTestData::deleteIncident($this->incident);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->manager);
    });

    it('reports no postmortem and zeroed counts on a fresh incident', function () {
        $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.postMortem', null)
            ->assertJsonPath('data.counts.timelineEntries', 0)
            ->assertJsonPath('data.counts.documents', 0)
            ->assertJsonPath('data.counts.actionItems', 0)
            ->assertJsonPath('data.counts.openActionItems', 0);
    });

    it('summarises the postmortem and counts documentation on show', function () {
        $postMortem = IncidentTestData::createPostMortem($this->incident, $this->manager->id, [
            'dueAt' => now()->addWeek(),
        ]);
        IncidentTestData::createTimelineEntry($this->incident);
        IncidentTestData::createTimelineEntry($this->incident);
        IncidentTestData::createActionItem($this->incident, ['createdBy' => $this->manager->id]);
        IncidentTestData::createActionItem($this->incident, [
            'createdBy' => $this->manager->id,
            'status' => 'done',
        ]);

        $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.postMortem.id', $postMortem->id)
            ->assertJsonPath('data.postMortem.status', 'draft')
            ->assertJsonPath('data.postMortem.authorId', $this->manager->id)
            ->assertJsonPath('data.postMortem.publishedAt', null)
            ->assertJsonPath('data.counts.timelineEntries', 2)
            ->assertJsonPath('data.counts.actionItems', 2)
            ->assertJsonPath('data.counts.openActionItems', 1);
    });

    it('omits the counts from the list endpoint', function () {
        $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/incident?perPage=5')
            ->assertSuccessful()
            ->assertJsonStructure(laravelPaginatorStructure())
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 5)
            ->assertJsonPath('data.0.counts', null);
    });

    it('removes the documentation when the incident is deleted', function () {
        IncidentTestData::createPostMortem($this->incident, $this->manager->id);
        IncidentTestData::createTimelineEntry($this->incident);
        IncidentTestData::createActionItem($this->incident, ['createdBy' => $this->manager->id]);

        $this->actingAs($this->manager, 'api')
            ->deleteJson("/api/v1/incident/{$this->incident->id}")
            ->assertSuccessful();

        expect(PostMortem::query()->where('incidentId', $this->incident->id)->count())->toBe(0)
            ->and(IncidentTimelineEntry::query()->where('incidentId', $this->incident->id)->count())->toBe(0)
            ->and(IncidentActionItem::query()->where('incidentId', $this->incident->id)->count())->toBe(0);
    });
});

describe('Incident nested documentation on create and update', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();
        Storage::fake(config('filesystems.default'));

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id]);
        $this->incidents = [];
    });

    afterEach(function () {
        foreach ($this->incidents as $incident) {
            IncidentTestData::deleteIncident($incident);
        }
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->manager);
    });

    it('creates an incident with a postmortem', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'With postmortem',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV2',
                'postMortem' => [
                    'summary' => 'Pool saturation caused checkout 5xx.',
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.postMortem.status', 'draft')
            ->assertJsonPath('data.postMortem.authorId', $this->manager->id);

        $incident = Incident::find($response->json('data.id'));
        $this->incidents[] = $incident;

        expect(PostMortem::query()->where('incidentId', $incident->id)->count())->toBe(1)
            ->and($response->json('data.counts.documents'))->toBe(0);
    });

    it('creates an incident with an external document link', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'With link',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV3',
                'documents' => [
                    [
                        'externalUrl' => 'https://grafana.example.com/d/checkout',
                        'name' => 'Checkout dashboard',
                        'type' => 'metric',
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.counts.documents', 1);

        $incident = Incident::find($response->json('data.id'));
        $this->incidents[] = $incident;

        $document = IncidentDocument::query()->where('incidentId', $incident->id)->first();
        expect($document->externalUrl)->toBe('https://grafana.example.com/d/checkout')
            ->and($document->name)->toBe('Checkout dashboard');
    });

    it('creates an incident with an uploaded document file', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->post('/api/v1/incident', [
                'title' => 'With file',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV3',
                'documents' => [
                    [
                        'file' => UploadedFile::fake()->image('checkout-errors.png'),
                        'type' => 'screenshot',
                    ],
                ],
            ], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.counts.documents', 1);

        $incident = Incident::find($response->json('data.id'));
        $this->incidents[] = $incident;

        $document = IncidentDocument::query()->where('incidentId', $incident->id)->first();
        expect($document->fileName)->toBe('checkout-errors.png')
            ->and($document->path)->toStartWith("incidents/{$incident->id}/documents/");
        Storage::disk(config('filesystems.default'))->assertExists($document->path);
    });

    it('creates a postmortem then attaches a document to it in the same request', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'Review pack',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV2',
                'postMortem' => [
                    'summary' => 'Written during create.',
                ],
                'documents' => [
                    [
                        'externalUrl' => 'https://wiki.example.com/review',
                        'attachableType' => 'postMortem',
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.counts.documents', 1);

        $incident = Incident::find($response->json('data.id'));
        $this->incidents[] = $incident;

        $postMortem = PostMortem::query()->where('incidentId', $incident->id)->first();
        $document = IncidentDocument::query()->where('incidentId', $incident->id)->first();

        expect($document->attachableType->value)->toBe('postMortem')
            ->and($document->attachableId)->toBe($postMortem->id);
    });

    it('adds documents on update without replacing existing ones', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->incidents[] = $incident;
        $postMortem = IncidentTestData::createPostMortem($incident, $this->manager->id, [
            'summary' => 'Keep this summary',
        ]);

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$incident->id}/document", [
                'externalUrl' => 'https://grafana.example.com/d/one',
                'name' => 'First',
            ])
            ->assertStatus(201);

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$incident->id}", [
                'title' => $incident->title,
                'teamIds' => [$this->team->id],
                'severity' => $incident->severity->value,
                'documents' => [
                    [
                        'externalUrl' => 'https://grafana.example.com/d/two',
                        'name' => 'Second',
                    ],
                ],
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.postMortem.id', $postMortem->id)
            ->assertJsonPath('data.counts.documents', 2);

        expect(PostMortem::query()->where('incidentId', $incident->id)->first()->summary)->toBe('Keep this summary')
            ->and(IncidentDocument::query()->where('incidentId', $incident->id)->count())->toBe(2);
    });

    it('rejects nested documentation on a policy incident', function () {
        $incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id], [
            'source' => IncidentSource::Policy,
        ]);
        $this->incidents[] = $incident;

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$incident->id}", [
                'title' => $incident->title,
                'teamIds' => [$this->team->id],
                'severity' => $incident->severity->value,
                'postMortem' => [
                    'summary' => 'Not allowed on policy incidents.',
                ],
                'documents' => [
                    ['externalUrl' => 'https://example.com/evidence'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['postMortem', 'documents']);
    });

    it('rejects a nested document that has neither a file nor an external url', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'Invalid docs',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV3',
                'documents' => [
                    ['name' => 'Missing both'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['documents.0.file', 'documents.0.externalUrl']);
    });

    it('rejects a nested postmortem without a summary', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'Invalid postmortem',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV3',
                'postMortem' => [
                    'impact' => 'Checkout down',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['postMortem.summary']);
    });

    it('rejects attaching to a postmortem when none exists and none is sent', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident', [
                'title' => 'No postmortem',
                'teamIds' => [$this->team->id],
                'severity' => 'SEV3',
                'documents' => [
                    [
                        'externalUrl' => 'https://wiki.example.com/review',
                        'attachableType' => 'postMortem',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['documents.0.attachableType']);
    });
});
