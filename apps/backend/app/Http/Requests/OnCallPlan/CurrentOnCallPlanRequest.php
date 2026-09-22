<?php

namespace App\Http\Requests\OnCallPlan;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CurrentOnCallPlanRequest extends FormRequest
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
            'at' => ['nullable', 'date'],
            'teamIds' => ['nullable', 'array'],
            'teamIds.*' => ['required', 'string', 'size:24'],
        ];
    }
}
