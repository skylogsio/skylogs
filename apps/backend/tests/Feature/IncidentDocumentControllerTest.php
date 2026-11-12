<?php

use App\Enums\Constants;
use App\Models\IncidentDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IncidentTestData;
use Tests\Support\TeamTestData;

describe('Incident documents', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();
        Storage::fake(config('filesystems.default'));

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->outsider = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id, $this->member->id]);
        $this->incident = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);
        $this->disk = fn () => Storage::disk(config('filesystems.default'));
    });

    afterEach(function () {
        IncidentTestData::deleteIncident($this->incident);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->manager);
        TeamTestData::deleteUser($this->member);
        TeamTestData::deleteUser($this->outsider);
    });

    it('uploads a file and stores it on the configured disk', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->post("/api/v1/incident/{$this->incident->id}/document", [
                'file' => UploadedFile::fake()->image('checkout-errors.png'),
                'type' => 'screenshot',
                'description' => 'Error rate panel at the peak',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'screenshot')
            ->assertJsonPath('data.name', 'checkout-errors.png')
            ->assertJsonPath('data.fileName', 'checkout-errors.png')
            ->assertJsonPath('data.mimeType', 'image/png')
            ->assertJsonPath('data.attachableType', 'incident')
            ->assertJsonPath('data.attachableId', $this->incident->id)
            ->assertJsonPath('data.isExternalLink', false)
            ->assertJsonPath('data.externalUrl', null);

        $document = IncidentDocument::find($response->json('data.id'));
        expect($document->path)->toStartWith("incidents/{$this->incident->id}/documents/");
        ($this->disk)()->assertExists($document->path);
    });

    it('attaches an external link without touching the disk', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/document", [
                'externalUrl' => 'https://grafana.example.com/d/checkout',
                'name' => 'Checkout dashboard',
                'type' => 'metric',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.externalUrl', 'https://grafana.example.com/d/checkout')
            ->assertJsonPath('data.name', 'Checkout dashboard')
            ->assertJsonPath('data.isExternalLink', true)
            ->assertJsonPath('data.fileName', null);
    });

    it('requires either a file or an external url', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/document", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file', 'externalUrl']);
    });

    it('rejects a file type that is not accepted', function () {
        $this->actingAs($this->manager, 'api')
            ->post("/api/v1/incident/{$this->incident->id}/document", [
                'file' => UploadedFile::fake()->create('payload.exe', 8, 'application/x-msdownload'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    });

    it('rejects a file over the size limit', function () {
        $this->actingAs($this->manager, 'api')
            ->post("/api/v1/incident/{$this->incident->id}/document", [
                'file' => UploadedFile::fake()->create('huge.txt', 20481, 'text/plain'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    });

    it('refuses to attach to a postmortem that does not exist', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/document", [
                'externalUrl' => 'https://wiki.example.com/review',
                'attachableType' => 'postMortem',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachableType']);
    });

    it('attaches to the postmortem once one exists', function () {
        $postMortem = IncidentTestData::createPostMortem($this->incident, $this->manager->id);

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/document", [
                'externalUrl' => 'https://wiki.example.com/review',
                'attachableType' => 'postMortem',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.attachableType', 'postMortem')
            ->assertJsonPath('data.attachableId', $postMortem->id);
    });

    it('lists documents for team members and filters by type', function () {
        $this->actingAs($this->manager, 'api')
            ->post("/api/v1/incident/{$this->incident->id}/document", [
                'file' => UploadedFile::fake()->image('shot.png'),
                'type' => 'screenshot',
            ])
            ->assertStatus(201);

        $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/document", [
                'externalUrl' => 'https://grafana.example.com/d/checkout',
                'type' => 'metric',
            ])
            ->assertStatus(201);

        $all = $this->actingAs($this->member, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}/document")
            ->assertSuccessful()
            ->assertJsonStructure(laravelPaginatorStructure())
            ->json('data');

        expect($all)->toHaveCount(2);
        expect($all[0]['canDelete'])->toBeFalse();

        $screenshots = $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}/document?type=screenshot")
            ->json('data');

        expect($screenshots)->toHaveCount(1)
            ->and($screenshots[0]['type'])->toBe('screenshot');
    });

    it('hands out a signed download url that serves the file', function () {
        $documentId = $this->actingAs($this->manager, 'api')
            ->post("/api/v1/incident/{$this->incident->id}/document", [
                'file' => UploadedFile::fake()->create('gateway.txt', 4, 'text/plain'),
            ])
            ->json('data.id');

        $response = $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}/document/{$documentId}/download-url")
            ->assertSuccessful();

        $url = $response->json('url');
        expect($url)->toContain('signature=')
            ->and($response->json('expiresAt'))->not->toBeNull();

        $this->get($url)->assertSuccessful();
    });

    it('rejects a download url with a broken signature', function () {
        $documentId = $this->actingAs($this->manager, 'api')
            ->post("/api/v1/incident/{$this->incident->id}/document", [
                'file' => UploadedFile::fake()->create('gateway.txt', 4, 'text/plain'),
            ])
            ->json('data.id');

        $this->get("/api/v1/incident-document/{$documentId}/download?signature=tampered&expires=9999999999")
            ->assertForbidden();
    });

    it('returns the external url as the download target', function () {
        $documentId = $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/document", [
                'externalUrl' => 'https://grafana.example.com/d/checkout',
            ])
            ->json('data.id');

        $this->actingAs($this->manager, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}/document/{$documentId}/download-url")
            ->assertSuccessful()
            ->assertJsonPath('url', 'https://grafana.example.com/d/checkout')
            ->assertJsonPath('expiresAt', null);
    });

    it('deletes a document and its stored file', function () {
        $documentId = $this->actingAs($this->manager, 'api')
            ->post("/api/v1/incident/{$this->incident->id}/document", [
                'file' => UploadedFile::fake()->image('shot.png'),
            ])
            ->json('data.id');

        $path = IncidentDocument::find($documentId)->path;

        $this->actingAs($this->manager, 'api')
            ->deleteJson("/api/v1/incident/{$this->incident->id}/document/{$documentId}")
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        expect(IncidentDocument::find($documentId))->toBeNull();
        ($this->disk)()->assertMissing($path);
    });

    it('does not find a document that belongs to another incident', function () {
        $other = IncidentTestData::createIncident($this->manager->id, [$this->team->id]);

        $documentId = $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$other->id}/document", [
                'externalUrl' => 'https://grafana.example.com/d/other',
            ])
            ->json('data.id');

        $this->actingAs($this->manager, 'api')
            ->deleteJson("/api/v1/incident/{$this->incident->id}/document/{$documentId}")
            ->assertNotFound();

        IncidentTestData::deleteIncident($other);
    });

    it('lets a team member read but not upload or delete', function () {
        $documentId = $this->actingAs($this->manager, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/document", [
                'externalUrl' => 'https://grafana.example.com/d/checkout',
            ])
            ->json('data.id');

        $this->actingAs($this->member, 'api')
            ->postJson("/api/v1/incident/{$this->incident->id}/document", [
                'externalUrl' => 'https://grafana.example.com/d/second',
            ])
            ->assertForbidden();

        $this->actingAs($this->member, 'api')
            ->deleteJson("/api/v1/incident/{$this->incident->id}/document/{$documentId}")
            ->assertForbidden();
    });

    it('forbids outsiders from listing documents', function () {
        $this->actingAs($this->outsider, 'api')
            ->getJson("/api/v1/incident/{$this->incident->id}/document")
            ->assertForbidden();
    });
});
