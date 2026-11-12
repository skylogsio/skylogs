<?php

namespace App\Http\Requests\IncidentTimeline;

use App\Enums\IncidentTimelineEntryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentTimelineEntryRequest extends FormRequest
{
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
            'type' => ['required', Rule::in(IncidentTimelineEntryType::userWritable())],
            'message' => ['required', 'string', 'max:5000'],
            'occurredAt' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
            'isPublic' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'The type must be one of: '.implode(', ', IncidentTimelineEntryType::userWritable()).'. The remaining types are written by the system.',
        ];
    }
}
