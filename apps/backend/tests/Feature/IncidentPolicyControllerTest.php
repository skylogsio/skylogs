<?php

use App\Enums\Constants;
use App\Models\IncidentPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\Support\IncidentPolicyTestData;
use Tests\Support\TeamTestData;

describe('IncidentPolicyController', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id, $this->member->id]);
        $this->alertRule = IncidentPolicyTestData::createAlertRule($this->manager);
        $this->endpoint = IncidentPolicyTestData::createEndpoint($this->manager);
        $this->profileServices = [];

        $this->policyName = 'test-policy-'.substr(uniqid(), -8);
        $this->yaml = fn (array $overrides = []) => IncidentPolicyTestData::policyYaml(
            $this->policyName,
            $this->team->name,
            [
                'alertRule' => $this->alertRule->name,
                'channel' => $this->endpoint->name,
                ...$overrides,
            ],
        );
    });

    afterEach(function () {
        IncidentPolicyTestData::deletePolicyByName($this->policyName);
        IncidentPolicyTestData::deleteAlertRule($this->alertRule);
        IncidentPolicyTestData::deleteEndpoint($this->endpoint);
        foreach ($this->profileServices as $profileService) {
            IncidentPolicyTestData::deleteProfileService($profileService);
        }
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->manager);
        TeamTestData::deleteUser($this->member);
    });

    it('imports a policy from a yaml string and resolves references to ids', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', ['yaml' => ($this->yaml)()])
            ->assertSuccessful()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('dryRun', false)
            ->assertJsonPath('created.0.name', $this->policyName)
            ->assertJsonPath('created.0.version', 1)
            ->assertJsonCount(0, 'updated');

        $policy = IncidentPolicy::query()->where('name', $this->policyName)->first();

        expect($policy)->not->toBeNull()
            ->and($response->json('created.0.id'))->toBe($policy->id)
            ->and($policy->teamIds)->toBe([$this->team->id])
            ->and($policy->enabled)->toBeTrue()
            ->and($policy->source->value)->toBe('yaml')
            ->and($policy->createdBy)->toBe($this->manager->id)
            ->and($policy->match['alertRuleIds'])->toBe([$this->alertRule->id])
            ->and($policy->match['tags'])->toBe(['payments'])
            ->and($policy->rules['SEV1']['notifyEndpointIds'])->toBe([$this->endpoint->id])
            ->and($policy->rules['SEV1']['escalation']['useLayers'])->toBeTrue()
            ->and($policy->rules['SEV1']['postmortem'])->toBe([
                'required' => true,
                'dueDays' => 5,
                'reviewRequired' => false,
            ])
            ->and($policy->rules['SEV1']['runbookNames'])->toBe(['payments-api-5xx-triage']);
    });

    it('resolves profile service names onto match.serviceIds', function () {
        $profileService = IncidentPolicyTestData::createProfileService($this->manager, 'payments-api-'.uniqid());
        $this->profileServices[] = $profileService;

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', [
                'yaml' => ($this->yaml)(['services' => $profileService->name]),
            ])
            ->assertSuccessful();

        $policy = IncidentPolicy::query()->where('name', $this->policyName)->first();

        expect($policy)->not->toBeNull()
            ->and($policy->match['serviceIds'])->toBe([$profileService->id]);
    });

    it('imports a policy from an uploaded yaml file', function () {
        $this->actingAs($this->manager, 'api')
            ->post('/api/v1/incident-policy/import', [
                'file' => UploadedFile::fake()->createWithContent('policy.yaml', ($this->yaml)()),
            ])
            ->assertSuccessful()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('created.0.name', $this->policyName);

        expect(IncidentPolicy::query()->where('name', $this->policyName)->exists())->toBeTrue();
    });

    it('rejects an import without a file or yaml body', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file', 'yaml']);
    });

    it('treats re-importing the same definition as unchanged', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', ['yaml' => ($this->yaml)()])
            ->assertSuccessful();

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', ['yaml' => ($this->yaml)()])
            ->assertSuccessful()
            ->assertJsonCount(0, 'created')
            ->assertJsonCount(0, 'updated')
            ->assertJsonPath('unchanged.0.name', $this->policyName)
            ->assertJsonPath('unchanged.0.version', 1);

        expect(IncidentPolicy::query()->where('name', $this->policyName)->first()->version)->toBe(1);
    });

    it('bumps the version when the definition changes', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', ['yaml' => ($this->yaml)()])
            ->assertSuccessful();

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', [
                'yaml' => ($this->yaml)(['ackWithinMinutes' => '10']),
            ])
            ->assertSuccessful()
            ->assertJsonPath('updated.0.name', $this->policyName)
            ->assertJsonPath('updated.0.version', 2);

        $policy = IncidentPolicy::query()->where('name', $this->policyName)->first();

        expect($policy->version)->toBe(2)
            ->and($policy->rules['SEV1']['ackWithinMinutes'])->toBe(10)
            ->and($policy->updatedBy)->toBe($this->manager->id);
    });

    it('writes nothing on a dry run', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', [
                'yaml' => ($this->yaml)(),
                'dryRun' => true,
            ])
            ->assertSuccessful()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('dryRun', true)
            ->assertJsonPath('created.0.name', $this->policyName)
            ->assertJsonPath('created.0.id', null);

        expect(IncidentPolicy::query()->where('name', $this->policyName)->exists())->toBeFalse();
    });

    it('validates without writing', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/validate', ['yaml' => ($this->yaml)()])
            ->assertSuccessful()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('dryRun', true);

        expect(IncidentPolicy::query()->where('name', $this->policyName)->exists())->toBeFalse();
    });

    it('reports unresolved references by document path', function () {
        $yaml = IncidentPolicyTestData::policyYaml($this->policyName, 'no-such-team', [
            'alertRule' => $this->alertRule->name,
        ]);

        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', ['yaml' => $yaml])
            ->assertUnprocessable()
            ->assertJsonPath('valid', false);

        expect(collect($response->json('errors'))->pluck('path'))->toContain('metadata.teams[0]')
            ->and($response->json('errors.0.message'))->toContain("Team 'no-such-team' not found")
            ->and(IncidentPolicy::query()->where('name', $this->policyName)->exists())->toBeFalse();
    });

    it('reports structural errors by document path', function () {
        $response = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', [
                'yaml' => "apiVersion: skylogs.io/v1\nkind: IncidentPolicy\nmetadata: {}\nspec: {}\n",
            ])
            ->assertUnprocessable()
            ->assertJsonPath('valid', false);

        expect(collect($response->json('errors'))->pluck('path'))
            ->toContain('metadata.name', 'metadata.teams', 'spec.match', 'spec.rules');
    });

    it('forbids members from importing or deleting policies', function () {
        $this->actingAs($this->member, 'api')
            ->postJson('/api/v1/incident-policy/import', ['yaml' => ($this->yaml)()])
            ->assertForbidden();

        expect(IncidentPolicy::query()->where('name', $this->policyName)->exists())->toBeFalse();
    });

    it('requires authentication', function () {
        $this->postJson('/api/v1/incident-policy/import', ['yaml' => ($this->yaml)()])
            ->assertUnauthorized();
    });

    it('lists and shows imported policies', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', ['yaml' => ($this->yaml)()])
            ->assertSuccessful();

        $id = IncidentPolicy::query()->where('name', $this->policyName)->first()->id;

        $listed = $this->actingAs($this->manager, 'api')
            ->getJson('/api/v1/incident-policy?search='.urlencode($this->policyName))
            ->assertSuccessful()
            ->assertJsonStructure(laravelPaginatorStructure());

        expect(collect($listed->json('data'))->pluck('id'))->toContain($id);

        $this->actingAs($this->member, 'api')
            ->getJson("/api/v1/incident-policy/{$id}")
            ->assertSuccessful()
            ->assertJsonPath('data.name', $this->policyName)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.canEdit', false)
            ->assertJsonPath('data.teams.0.name', $this->team->name);
    });

    it('exports a stored policy back to the DSL with names instead of ids', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', ['yaml' => ($this->yaml)()])
            ->assertSuccessful();

        $id = IncidentPolicy::query()->where('name', $this->policyName)->first()->id;

        $exported = $this->actingAs($this->manager, 'api')
            ->get("/api/v1/incident-policy/{$id}/export")
            ->assertSuccessful()
            ->getContent();

        expect($exported)->toContain('apiVersion: skylogs.io/v1')
            ->and($exported)->toContain('kind: IncidentPolicy')
            ->and($exported)->toContain('name: '.$this->policyName)
            ->and($exported)->toContain($this->team->name)
            ->and($exported)->toContain('endpoint:'.$this->endpoint->name)
            ->and($exported)->not->toContain('onCallPlan')
            ->and($exported)->not->toContain($this->alertRule->id);
    });

    it('re-imports its own export as unchanged', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', ['yaml' => ($this->yaml)()])
            ->assertSuccessful();

        $id = IncidentPolicy::query()->where('name', $this->policyName)->first()->id;

        $exported = $this->actingAs($this->manager, 'api')
            ->get("/api/v1/incident-policy/{$id}/export")
            ->getContent();

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', ['yaml' => $exported])
            ->assertSuccessful()
            ->assertJsonPath('unchanged.0.name', $this->policyName)
            ->assertJsonCount(0, 'updated');
    });

    it('deletes a policy', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy/import', ['yaml' => ($this->yaml)()])
            ->assertSuccessful();

        $id = IncidentPolicy::query()->where('name', $this->policyName)->first()->id;

        $this->actingAs($this->manager, 'api')
            ->deleteJson("/api/v1/incident-policy/{$id}")
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        expect(IncidentPolicy::query()->where('name', $this->policyName)->exists())->toBeFalse();
    });
});
