<?php

namespace App\Http\Requests\AlertRule;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexHistoryFailureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->route('id'),
            'historyId' => $this->route('historyId'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'regex:/^[0-9a-fA-F]{24}$/'],
            'historyId' => ['required', 'string', 'regex:/^[0-9a-fA-F]{24}$/'],
        ];
    }
}
