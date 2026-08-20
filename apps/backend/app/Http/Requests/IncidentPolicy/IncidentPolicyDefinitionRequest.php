<?php

namespace App\Http\Requests\IncidentPolicy;

use App\Enums\IncidentSeverity;
use App\Http\Requests\Concerns\ValidatesMongoReferences;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\OnCallPlan;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use App\Services\IncidentPolicy\IncidentPolicyDslParser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared validation for the JSON representation of a policy definition.
 *
 * The body mirrors the stored document rather than the YAML DSL, so references arrive as
 * ids and rules arrive as a map keyed by severity. Limits are kept identical to
 * `IncidentPolicyDslParser` so the same definition is accepted through either door.
 */
abstract class IncidentPolicyDefinitionRequest extends FormRequest
{
    use ValidatesMongoReferences;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9-]*$/', $this->nameUniqueRule()],
            'description' => ['nullable', 'string', 'max:1000'],
            'enabled' => ['nullable', 'boolean'],
            'ownerId' => ['nullable', 'string', 'size:24'],
            'teamIds' => ['required', 'array', 'min:1'],
            'teamIds.*' => ['required', 'string', 'size:24'],

            'match' => ['required', 'array'],
            'match.alertRuleIds' => ['nullable', 'array'],
            'match.alertRuleIds.*' => ['required', 'string', 'size:24'],
            'match.tags' => ['nullable', 'array'],
            'match.tags.*' => ['required', 'string', 'max:120'],
            'match.serviceIds' => ['nullable', 'array'],
            'match.serviceIds.*' => ['required', 'string', 'size:24'],
            'match.dataSourceTypes' => ['nullable', 'array'],
            'match.dataSourceTypes.*' => ['required', 'string', Rule::in(array_keys(Service::$types))],

            'grouping' => ['nullable', 'array'],
            'grouping.key' => ['nullable', 'array'],
            'grouping.key.*' => ['required', 'string', Rule::in(IncidentPolicyDslParser::GROUPING_KEYS)],
            'grouping.windowMinutes' => ['nullable', 'integer', 'min:1', 'max:1440'],

            'incident' => ['nullable', 'array'],
            'incident.autoCreate' => ['nullable', 'boolean'],
            'incident.autoResolveOnAlertClear' => ['nullable', 'boolean'],
            'incident.titleTemplate' => ['nullable', 'string', 'max:255'],
            'incident.defaultSeverity' => ['nullable', Rule::enum(IncidentSeverity::class)],
            'incident.severityMap' => ['nullable', 'array'],
            'incident.severityMap.*' => ['required', Rule::enum(IncidentSeverity::class)],

            'rules' => ['required', 'array', 'min:1'],
            'rules.*' => ['required', 'array'],
            'rules.*.ackWithinMinutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'rules.*.resolveWithinMinutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'rules.*.requireCommander' => ['nullable', 'boolean'],
            'rules.*.notifyEndpointIds' => ['nullable', 'array'],
            'rules.*.notifyEndpointIds.*' => ['required', 'string', 'size:24'],
            'rules.*.escalation' => ['nullable', 'array'],
            'rules.*.escalation.onCallPlanId' => ['nullable', 'string', 'size:24'],
            'rules.*.escalation.useLayers' => ['nullable', 'boolean'],
            'rules.*.communication' => ['nullable', 'array'],
            'rules.*.communication.stakeholderUpdateEveryMinutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'rules.*.communication.statusPageUpdateRequired' => ['nullable', 'boolean'],
            'rules.*.postmortem' => ['nullable', 'array'],
            'rules.*.postmortem.required' => ['nullable', 'boolean'],
            'rules.*.postmortem.dueDays' => ['nullable', 'integer', 'min:1', 'max:365'],
            'rules.*.postmortem.reviewRequired' => ['nullable', 'boolean'],
            'rules.*.runbookNames' => ['nullable', 'array'],
            'rules.*.runbookNames.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'The name must be lowercase and may only contain letters, digits and dashes.',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            $this->assertMatcherPresent(...),
            $this->assertSeveritiesAreKnown(...),
            $this->assertResolveNotBeforeAck(...),
            $this->assertPolicyReferencesExist(...),
        ];
    }

    abstract protected function nameUniqueRule(): Unique;

    /**
     * A policy without a matcher would cover every alert, which is never what was meant.
     */
    private function assertMatcherPresent(Validator $validator): void
    {
        $match = $this->input('match');

        if (! is_array($match)) {
            return;
        }

        foreach (['alertRuleIds', 'tags', 'serviceIds', 'dataSourceTypes'] as $matcher) {
            if (! empty($match[$matcher])) {
                return;
            }
        }

        $validator->errors()->add(
            'match',
            'At least one of alertRuleIds, tags, serviceIds or dataSourceTypes is required, otherwise the policy would match every alert.',
        );
    }

    private function assertSeveritiesAreKnown(Validator $validator): void
    {
        foreach (array_keys($this->ruleMap()) as $severity) {
            if (IncidentSeverity::tryFrom((string) $severity) === null) {
                $validator->errors()->add("rules.{$severity}", "Unknown severity '{$severity}'.");
            }
        }
    }

    private function assertResolveNotBeforeAck(Validator $validator): void
    {
        foreach ($this->ruleMap() as $severity => $rule) {
            $ackWithin = $rule['ackWithinMinutes'] ?? null;
            $resolveWithin = $rule['resolveWithinMinutes'] ?? null;

            if (is_int($ackWithin) && is_int($resolveWithin) && $resolveWithin < $ackWithin) {
                $validator->errors()->add(
                    "rules.{$severity}.resolveWithinMinutes",
                    'Must be greater than or equal to ackWithinMinutes.',
                );
            }
        }
    }

    private function assertPolicyReferencesExist(Validator $validator): void
    {
        $this->assertReferencesExist($validator, 'teamIds', Team::class, 'Team', $this->input('teamIds'));
        $this->assertReferencesExist($validator, 'ownerId', User::class, 'User', $this->input('ownerId'));
        $this->assertReferencesExist($validator, 'match.alertRuleIds', AlertRule::class, 'Alert rule', $this->input('match.alertRuleIds'));
        $this->assertReferencesExist($validator, 'match.serviceIds', Service::class, 'Service', $this->input('match.serviceIds'));

        foreach ($this->ruleMap() as $severity => $rule) {
            $this->assertReferencesExist(
                $validator,
                "rules.{$severity}.notifyEndpointIds",
                Endpoint::class,
                'Endpoint',
                $rule['notifyEndpointIds'] ?? null,
            );
            $this->assertReferencesExist(
                $validator,
                "rules.{$severity}.escalation.onCallPlanId",
                OnCallPlan::class,
                'On-call plan',
                $rule['escalation']['onCallPlanId'] ?? null,
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function ruleMap(): array
    {
        $rules = $this->input('rules');

        if (! is_array($rules)) {
            return [];
        }

        return array_filter($rules, 'is_array');
    }
}
