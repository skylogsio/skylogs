<?php

namespace App\Http\Requests\IncidentTimeline;

use App\Enums\IncidentTimelineEntrySource;
use App\Enums\IncidentTimelineEntryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexIncidentTimelineRequest extends FormRequest
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
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'type' => ['sometimes', Rule::enum(IncidentTimelineEntryType::class)],
            'source' => ['sometimes', Rule::enum(IncidentTimelineEntrySource::class)],
            'isPublic' => ['sometimes', 'boolean'],
        ];
    }
}
