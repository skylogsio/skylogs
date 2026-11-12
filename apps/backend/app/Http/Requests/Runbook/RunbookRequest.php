<?php

namespace App\Http\Requests\Runbook;

use App\Enums\IncidentSeverity;
use App\Enums\RunbookSourceType;
use App\Enums\RunbookStatus;
use App\Http\Requests\Concerns\ValidatesMongoReferences;
use App\Models\AlertRule;
use App\Models\Profile\ProfileService;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared validation for writing a runbook.
 *
 * `sourceType` decides which body field is mandatory, so the request never accepts a
 * runbook that claims to hold steps while carrying only a wiki link.
 */
abstract class RunbookRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9-]*$/', $this->slugUniqueRule()],
            'description' => ['nullable', 'string', 'max:2000'],
            'teamIds' => ['required', 'array', 'min:1'],
            'teamIds.*' => ['required', 'string', 'size:24'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['required', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(RunbookStatus::class)],
            'sourceType' => ['required', Rule::enum(RunbookSourceType::class)],

            'content' => ['nullable', 'string', 'max:100000', 'required_if:sourceType,'.RunbookSourceType::Markdown->value],
            'externalUrl' => ['nullable', 'url', 'max:2048', 'required_if:sourceType,'.RunbookSourceType::ExternalUrl->value],

            'steps' => ['nullable', 'array', 'max:100', 'required_if:sourceType,'.RunbookSourceType::Steps->value],
            'steps.*.title' => ['required', 'string', 'max:255'],
            'steps.*.description' => ['nullable', 'string', 'max:5000'],
            'steps.*.command' => ['nullable', 'string', 'max:2000'],
            'steps.*.expectedResult' => ['nullable', 'string', 'max:2000'],

            'appliesTo' => ['nullable', 'array'],
            'appliesTo.serviceIds' => ['nullable', 'array'],
            'appliesTo.serviceIds.*' => ['required', 'string', 'size:24'],
            'appliesTo.alertRuleIds' => ['nullable', 'array'],
            'appliesTo.alertRuleIds.*' => ['required', 'string', 'size:24'],
            'appliesTo.tags' => ['nullable', 'array'],
            'appliesTo.tags.*' => ['required', 'string', 'max:120'],
            'appliesTo.severities' => ['nullable', 'array'],
            'appliesTo.severities.*' => ['required', Rule::enum(IncidentSeverity::class)],

            'reviewIntervalDays' => ['nullable', 'integer', 'min:1', 'max:1095'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug must be lowercase and may only contain letters, digits and dashes.',
            'steps.required_if' => 'At least one step is required when sourceType is steps.',
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->assertReferencesExist($validator, 'teamIds', Team::class, 'Team', $this->input('teamIds'));
                $this->assertReferencesExist($validator, 'appliesTo.serviceIds', ProfileService::class, 'Service', $this->input('appliesTo.serviceIds'));
                $this->assertReferencesExist($validator, 'appliesTo.alertRuleIds', AlertRule::class, 'Alert rule', $this->input('appliesTo.alertRuleIds'));
            },
        ];
    }

    abstract protected function slugUniqueRule(): Unique;
}
