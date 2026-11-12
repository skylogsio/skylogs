<?php

namespace App\Http\Requests\Incident;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexIncidentRequest extends FormRequest
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
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::enum(IncidentStatus::class)],
            'severity' => ['sometimes', Rule::enum(IncidentSeverity::class)],
            'teamId' => ['sometimes', 'string'],
            'tag' => ['sometimes', 'string'],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
