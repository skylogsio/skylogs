<?php

namespace App\Http\Requests\Runbook;

use App\Enums\RunbookStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRunbookRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(RunbookStatus::class)],
            'teamId' => ['sometimes', 'string'],
            'tag' => ['sometimes', 'string'],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
