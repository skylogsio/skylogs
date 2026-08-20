<?php

use App\Enums\Constants;
use App\Enums\IncidentTimelineEntryType;
use App\Models\IncidentTimelineEntry;
use Illuminate\Support\Facades\Cache;
use Tests\Support\IncidentTestData;
use Tests\Support\TeamTestData;

describe('Incident postmortem', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->outsider = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id, $this->member->id]);
        $this->incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);

        $this->payload = function (array $overrides = []) {
            return array_replace([
                'status' => 'draft',
                'summary' => 'Checkout was unavailable for 22 minutes.',
                'impact' => '4% of checkout attempts failed.',
                'detection' => 'A Prometheus alert fired two minutes in.',
                'resolution' => 'Rolled back the payment gateway release.',
                'rootCause' => [
                    'method' => 'fiveWhys',
                    'whys' => ['The gateway returned 500', 'A required header was dropped'],
                    'contributingFactors' => ['No contract test on the header'],
                    'statement' => 'A release dropped a required header.',
                ],
                'whatWentWell' => ['Alerting was fast'],
                'whatWentWrong' => ['The rollback took too long'],
                'lessonsLearned' => ['Add a contract test'],
                'reviewerIds' => [$this->member->id],
                'dueAt' => now()->addDays(5)->toISOString(),
            ], $overrides);
        };
    });

    afterEach(function () {
        IncidentTestData::deleteIncident($this->incident);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->manager);
        TeamTestData::deleteUser($this->member);
        TeamTestData::deleteUser($this->outsider);
    });

    it('returns null while the incident has no postmortem', function () {
        $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}/postmortem")
            ->assertSuccessful()
            ->assertJsonPath('data', null);
    });

    it('creates the postmortem on the first write', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)())
            ->assertSuccessful()
            ->assertJsonPath('data.incidentId', $this->incident->id)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.summary', 'Checkout was unavailable for 22 minutes.')
            ->assertJsonPath('data.rootCause.method', 'fiveWhys')
            ->assertJsonPath('data.rootCause.whys.1', 'A required header was dropped')
            ->assertJsonPath('data.lessonsLearned.0', 'Add a contract test')
            ->assertJsonPath('data.reviewerIds.0', $this->member->id)
            ->assertJsonPath('data.authorId', $this->manager->id)
            ->assertJsonPath('data.publishedAt', null)
            ->assertJsonPath('data.canEdit', true);
    });

    it('keeps one postmortem per incident across repeated writes', function () {
        $first = $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)())
            ->assertSuccessful()
            ->json('data.id');

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)([
                'status' => 'inReview',
                'summary' => 'Revised summary.',
            ]))
            ->assertSuccessful()
            ->assertJsonPath('data.id', $first)
            ->assertJsonPath('data.status', 'inReview')
            ->assertJsonPath('data.summary', 'Revised summary.');
    });

    it('clears fields that are omitted from the replacement', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)())
            ->assertSuccessful();

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", [
                'summary' => 'Only a summary this time.',
            ])
            ->assertSuccessful()
            ->assertJsonPath('data.impact', null)
            ->assertJsonPath('data.lessonsLearned', [])
            ->assertJsonPath('data.rootCause.whys', [])
            ->assertJsonPath('data.reviewerIds', []);
    });

    it('publishes a postmortem and records it on the timeline', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)())
            ->assertSuccessful();

        $response = $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/postmortem/publish")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'published');

        expect($response->json('data.publishedAt'))->not->toBeNull();

        $published = IncidentTimelineEntry::query()
            ->where('incidentId', $this->incident->id)
            ->where('type', IncidentTimelineEntryType::PostMortemPublished->value)
            ->get();

        expect($published)->toHaveCount(1);
    });

    it('keeps the original publication date when published twice', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)())
            ->assertSuccessful();

        $publishedAt = $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/postmortem/publish")
            ->json('data.publishedAt');

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/postmortem/publish")
            ->assertSuccessful()
            ->assertJsonPath('data.publishedAt', $publishedAt);

        expect(IncidentTimelineEntry::query()
            ->where('incidentId', $this->incident->id)
            ->where('type', IncidentTimelineEntryType::PostMortemPublished->value)
            ->count())->toBe(1);
    });

    it('publishes through a status of published on the write endpoint', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)([
                'status' => 'published',
            ]))
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'published');

        expect($response->json('data.publishedAt'))->not->toBeNull();
    });

    it('returns 404 when publishing before anything was written', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/postmortem/publish")
            ->assertNotFound();
    });

    it('requires a summary', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['summary']);
    });

    it('rejects a reviewer that does not exist', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)([
                'reviewerIds' => ['0123456789abcdef01234567'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reviewerIds.0']);
    });

    it('lets a team member read but not write the postmortem', function () {
        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)())
            ->assertSuccessful();

        $this->actingAs($this->member, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}/postmortem")
            ->assertSuccessful()
            ->assertJsonPath('data.canEdit', false);

        $this->actingAs($this->member, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)())
            ->assertForbidden();
    });

    it('forbids outsiders entirely', function () {
        $this->actingAs($this->outsider, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}/postmortem")
            ->assertForbidden();

        $this->actingAs($this->outsider, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)())
            ->assertForbidden();
    });

    it('exposes the postmortem summary on the incident itself', function () {
        $postMortemId = $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident/{$this->incident->id}/postmortem", ($this->payload)())
            ->json('data.id');

        $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.postMortem.id', $postMortemId)
            ->assertJsonPath('data.postMortem.status', 'draft');
    });
});
