<?php

namespace App\Http\Requests\Incident;

use App\Enums\IncidentSeverity;
use App\Http\Requests\Concerns\ValidatesIncidentNestedDocumentation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentRequest extends FormRequest
{
    use ValidatesIncidentNestedDocumentation;

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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'teamIds' => ['required', 'array', 'min:1'],
            'teamIds.*' => ['required', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'startedAt' => ['nullable', 'date'],
            'detectedAt' => ['nullable', 'date'],
            'resolvedAt' => [
                'nullable',
                'date',
                Rule::when($this->filled('startedAt'), ['after_or_equal:startedAt']),
            ],
            'alertRuleIds' => ['nullable', 'array'],
            'alertRuleIds.*' => ['string'],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'commanderId' => ['nullable', 'string', 'size:24'],
            ...$this->nestedDocumentationRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->nestedDocumentationMessages();
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return $this->nestedDocumentationAfter();
    }
}
