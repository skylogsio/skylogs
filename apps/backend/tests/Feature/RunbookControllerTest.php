<?php

use App\Enums\Constants;
use App\Models\Runbook;
use Illuminate\Support\Facades\Cache;
use Tests\Support\RunbookTestData;
use Tests\Support\TeamTestData;

describe('RunbookController', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->outsider = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id, $this->member->id]);
        $this->runbooks = [];

        $this->payload = function (array $overrides = []) {
            return array_replace([
                'name' => 'Payments API 5xx triage '.uniqid(),
                'description' => 'What to do when the payments API returns 5xx',
                'teamIds' => [$this->team->id],
                'tags' => ['payments'],
                'status' => 'published',
                'sourceType' => 'steps',
                'steps' => [
                    ['title' => 'Check the error dashboard', 'command' => 'open https://grafana/payments'],
                    ['title' => 'Roll back the last deploy', 'expectedResult' => 'Error rate drops below 1%'],
                ],
                'appliesTo' => ['tags' => ['payments'], 'severities' => ['SEV1', 'SEV2']],
                'reviewIntervalDays' => 90,
            ], $overrides);
        };
    });

    afterEach(function () {
        foreach ($this->runbooks as $runbook) {
            RunbookTestData::deleteRunbook($runbook);
        }
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->manager);
        TeamTestData::deleteUser($this->member);
        TeamTestData::deleteUser($this->outsider);
    });

    it('creates a runbook with ordered steps and a derived slug', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/runbook', ($this->payload)(['name' => 'Payments API 5xx triage']))
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Payments API 5xx triage')
            ->assertJsonPath('data.slug', 'payments-api-5xx-triage')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.sourceType', 'steps')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.steps.0.title', 'Check the error dashboard')
            ->assertJsonPath('data.steps.1.expectedResult', 'Error rate drops below 1%')
            ->assertJsonPath('data.appliesTo.severities.1', 'SEV2')
            ->assertJsonPath('data.reviewIntervalDays', 90)
            ->assertJsonPath('data.canEdit', true);

        $this->runbooks[] = Runbook::find($response->json('data.id'));
    });

    it('appends a suffix when the derived slug is taken', function () {
        $first = RunbookTestData::createRunbook($this->manager, $this->team, ['name' => 'Duplicate name']);
        $this->runbooks[] = $first;

        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/runbook', ($this->payload)(['name' => 'Duplicate name']))
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'duplicate-name-2');

        $this->runbooks[] = Runbook::find($response->json('data.id'));
    });

    it('creates a markdown runbook and drops the step body', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/runbook', ($this->payload)([
                'sourceType' => 'markdown',
                'content' => '## Triage\n1. Check the dashboard',
                'steps' => null,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.sourceType', 'markdown')
            ->assertJsonPath('data.content', '## Triage\n1. Check the dashboard')
            ->assertJsonPath('data.steps', []);

        $this->runbooks[] = Runbook::find($response->json('data.id'));
    });

    it('creates an external runbook', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/runbook', ($this->payload)([
                'sourceType' => 'externalUrl',
                'externalUrl' => 'https://wiki.example.com/runbooks/payments',
                'steps' => null,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.externalUrl', 'https://wiki.example.com/runbooks/payments')
            ->assertJsonPath('data.content', null);

        $this->runbooks[] = Runbook::find($response->json('data.id'));
    });

    it('requires the body that matches sourceType', function (string $sourceType, string $missingField) {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/runbook', ($this->payload)([
                'sourceType' => $sourceType,
                'steps' => null,
                'content' => null,
                'externalUrl' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$missingField]);
    })->with([
        'steps' => ['steps', 'steps'],
        'markdown' => ['markdown', 'content'],
        'externalUrl' => ['externalUrl', 'externalUrl'],
    ]);

    it('rejects creation with missing required fields', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/runbook', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'teamIds', 'sourceType']);
    });

    it('rejects a team that does not exist', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/runbook', ($this->payload)([
                'teamIds' => ['0123456789abcdef01234567'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['teamIds.0']);
    });

    it('lists runbooks for team members and hides them from outsiders', function () {
        $runbook = RunbookTestData::createRunbook($this->manager, $this->team);
        $this->runbooks[] = $runbook;

        $visible = $this->actingAs($this->member, 'api')
            ->getJson('/api/v1/runbook?search='.urlencode($runbook->name))
            ->assertSuccessful();

        expect(collect($visible->json('data'))->pluck('id')->all())->toContain($runbook->id);

        $hidden = $this->actingAs($this->outsider, 'api')
            ->getJson('/api/v1/runbook?search='.urlencode($runbook->name))
            ->assertSuccessful();

        expect(collect($hidden->json('data'))->pluck('id')->all())->not->toContain($runbook->id);
    });

    it('filters runbooks by status', function () {
        $prefix = 'status-filter-'.uniqid();
        $published = RunbookTestData::createRunbook($this->manager, $this->team, ['name' => $prefix.'-published']);
        $draft = RunbookTestData::createRunbook($this->manager, $this->team, [
            'name' => $prefix.'-draft',
            'status' => 'draft',
        ]);
        $this->runbooks[] = $published;
        $this->runbooks[] = $draft;

        $response = $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/runbook?status=published&search='.urlencode($prefix))
            ->assertSuccessful();

        $ids = collect($response->json('data'))->pluck('id')->all();
        expect($ids)->toContain($published->id)
            ->and($ids)->not->toContain($draft->id);
    });

    it('shows a runbook to a team member', function () {
        $runbook = RunbookTestData::createRunbook($this->manager, $this->team);
        $this->runbooks[] = $runbook;

        $this->actingAs($this->member, 'api')
            ->getJson("/api/v1/runbook/{$runbook->id}")
            ->assertSuccessful()
            ->assertJsonPath('data.id', $runbook->id)
            ->assertJsonPath('data.canEdit', false);
    });

    it('forbids outsiders from viewing a runbook', function () {
        $runbook = RunbookTestData::createRunbook($this->manager, $this->team);
        $this->runbooks[] = $runbook;

        $this->actingAs($this->outsider, 'api')
            ->getJson("/api/v1/runbook/{$runbook->id}")
            ->assertForbidden();
    });

    it('updates a runbook and bumps the version', function () {
        $runbook = RunbookTestData::createRunbook($this->manager, $this->team, ['name' => 'Before rename']);
        $this->runbooks[] = $runbook;

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/runbook/{$runbook->id}", ($this->payload)([
                'name' => 'After rename',
                'status' => 'archived',
            ]))
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'After rename')
            ->assertJsonPath('data.slug', 'after-rename')
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.version', 2);
    });

    it('deletes a runbook', function () {
        $runbook = RunbookTestData::createRunbook($this->manager, $this->team);
        $this->runbooks[] = $runbook;

        $this->actingAs($this->manager, 'api')
            ->deleteJson("/api/v1/runbook/{$runbook->id}")
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        expect(Runbook::find($runbook->id))->toBeNull();
    });

    it('forbids members from writing runbooks', function () {
        $runbook = RunbookTestData::createRunbook($this->manager, $this->team);
        $this->runbooks[] = $runbook;

        $this->actingAs($this->member, 'api')
            ->postJson('/api/v1/runbook', ($this->payload)())
            ->assertForbidden();

        $this->actingAs($this->member, 'api')
            ->deleteJson("/api/v1/runbook/{$runbook->id}")
            ->assertForbidden();
    });
});
