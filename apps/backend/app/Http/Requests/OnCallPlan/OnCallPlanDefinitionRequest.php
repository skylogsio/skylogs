<?php

namespace App\Http\Requests\OnCallPlan;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class OnCallPlanDefinitionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'file' => ['required', File::types(['xlsx'])->max(2048)],
            'layerDelays' => ['nullable', 'array'],
            'layerDelays.*' => ['required', 'integer', 'min:1', 'max:10080'],
        ];
    }
}
