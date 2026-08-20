<?php

use App\Enums\Constants;
use App\Models\IncidentPolicy;
use Illuminate\Support\Facades\Cache;
use Tests\Support\IncidentPolicyTestData;
use Tests\Support\RunbookTestData;
use Tests\Support\TeamTestData;

describe('IncidentPolicy JSON write API', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->team = TeamTestData::createTeam($this->manager, [$this->manager->id, $this->member->id]);
        $this->alertRule = IncidentPolicyTestData::createAlertRule($this->manager);
        $this->endpoint = IncidentPolicyTestData::createEndpoint($this->manager);
        $this->onCallPlan = IncidentPolicyTestData::createOnCallPlan($this->team);
        $this->policyNames = [];
        $this->runbooks = [];

        $this->payload = function (array $overrides = []) {
            return array_replace([
                'name' => 'json-policy-'.uniqid(),
                'description' => 'Created through the JSON API',
                'enabled' => true,
                'teamIds' => [$this->team->id],
                'match' => [
                    'alertRuleIds' => [$this->alertRule->id],
                    'tags' => ['payments'],
                ],
                'grouping' => ['key' => ['serviceId'], 'windowMinutes' => 20],
                'incident' => [
                    'autoCreate' => true,
                    'defaultSeverity' => 'SEV3',
                    'severityMap' => ['critical' => 'SEV1'],
                ],
                'rules' => [
                    'SEV1' => [
                        'ackWithinMinutes' => 5,
                        'resolveWithinMinutes' => 60,
                        'requireCommander' => true,
                        'notifyEndpointIds' => [$this->endpoint->id],
                        'escalation' => ['onCallPlanId' => $this->onCallPlan->id],
                        'postmortem' => ['required' => true],
                        'runbookNames' => ['payments-api-5xx-triage'],
                    ],
                ],
            ], $overrides);
        };
    });

    afterEach(function () {
        foreach ($this->policyNames as $name) {
            IncidentPolicyTestData::deletePolicyByName($name);
        }
        foreach ($this->runbooks as $runbook) {
            RunbookTestData::deleteRunbook($runbook);
        }
        IncidentPolicyTestData::deleteAlertRule($this->alertRule);
        IncidentPolicyTestData::deleteEndpoint($this->endpoint);
        IncidentPolicyTestData::deleteOnCallPlan($this->onCallPlan);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->manager);
        TeamTestData::deleteUser($this->member);
    });

    it('creates a policy from JSON and marks the source as api', function () {
        $payload = ($this->payload)();
        $this->policyNames[] = $payload['name'];

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.name', $payload['name'])
            ->assertJsonPath('data.source', 'api')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.match.alertRuleIds.0', $this->alertRule->id)
            ->assertJsonPath('data.rules.SEV1.ackWithinMinutes', 5)
            ->assertJsonPath('data.rules.SEV1.escalation.onCallPlanId', $this->onCallPlan->id)
            ->assertJsonPath('data.canEdit', true);
    });

    it('applies the same defaults as the YAML parser', function () {
        $payload = ($this->payload)([
            'grouping' => [],
            'incident' => [],
            'rules' => ['SEV2' => ['postmortem' => ['required' => true]]],
        ]);
        $this->policyNames[] = $payload['name'];

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.grouping.windowMinutes', 15)
            ->assertJsonPath('data.incident.autoCreate', true)
            ->assertJsonPath('data.incident.defaultSeverity', 'SEV3')
            ->assertJsonPath('data.rules.SEV2.postmortem.dueDays', 5)
            ->assertJsonPath('data.rules.SEV2.escalation.useLayers', true);
    });

    it('mirrors runbook names onto ids when the runbook exists', function () {
        $runbook = RunbookTestData::createRunbook($this->manager, $this->team, [
            'name' => 'payments-api-5xx-triage',
        ]);
        $this->runbooks[] = $runbook;

        $payload = ($this->payload)();
        $this->policyNames[] = $payload['name'];

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.rules.SEV1.runbookNames.0', 'payments-api-5xx-triage')
            ->assertJsonPath('data.rules.SEV1.runbookIds.0', $runbook->id);
    });

    it('keeps an unresolvable runbook name without failing', function () {
        $payload = ($this->payload)();
        $this->policyNames[] = $payload['name'];

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.rules.SEV1.runbookNames.0', 'payments-api-5xx-triage')
            ->assertJsonPath('data.rules.SEV1.runbookIds', []);
    });

    it('updates a policy and bumps the version', function () {
        $payload = ($this->payload)();
        $this->policyNames[] = $payload['name'];

        $id = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', $payload)
            ->json('data.id');

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident-policy/{$id}", array_replace($payload, [
                'description' => 'Updated through the JSON API',
                'enabled' => false,
            ]))
            ->assertSuccessful()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.description', 'Updated through the JSON API');
    });

    it('rejects a duplicate policy name', function () {
        $payload = ($this->payload)();
        $this->policyNames[] = $payload['name'];

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', $payload)
            ->assertStatus(201);

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows an update to keep its own name', function () {
        $payload = ($this->payload)();
        $this->policyNames[] = $payload['name'];

        $id = $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', $payload)
            ->json('data.id');

        $this->actingAs($this->manager, 'api')
            ->putJson("/api/v1/incident-policy/{$id}", $payload)
            ->assertSuccessful()
            ->assertJsonPath('data.name', $payload['name']);
    });

    it('rejects a policy without any matcher', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', ($this->payload)(['match' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['match']);
    });

    it('rejects an unknown severity key', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', ($this->payload)([
                'rules' => ['SEV9' => ['ackWithinMinutes' => 5]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules.SEV9']);
    });

    it('rejects resolveWithinMinutes below ackWithinMinutes', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', ($this->payload)([
                'rules' => ['SEV1' => ['ackWithinMinutes' => 60, 'resolveWithinMinutes' => 30]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules.SEV1.resolveWithinMinutes']);
    });

    it('reports references that do not exist', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', ($this->payload)([
                'match' => ['alertRuleIds' => ['0123456789abcdef01234567']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['match.alertRuleIds.0']);
    });

    it('rejects a name that is not slug shaped', function () {
        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', ($this->payload)(['name' => 'Not A Slug']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('forbids members from creating a policy', function () {
        $this->actingAs($this->member, 'api')
            ->postJson('/api/v1/incident-policy', ($this->payload)())
            ->assertForbidden();
    });

    it('stores nothing when the definition is invalid', function () {
        $payload = ($this->payload)(['match' => []]);

        $this->actingAs($this->manager, 'api')
            ->postJson('/api/v1/incident-policy', $payload)
            ->assertUnprocessable();

        expect(IncidentPolicy::query()->where('name', $payload['name'])->exists())->toBeFalse();
    });
});
