<?php

namespace App\Http\Requests\IncidentPolicy;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexIncidentPolicyRequest extends FormRequest
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
            'enabled' => ['sometimes', 'boolean'],
            'teamId' => ['sometimes', 'string'],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
