<?php

namespace App\Http\Requests\Ha;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HaConfigSyncRequest extends FormRequest
{
    /**
     * Authorisation is the HaNodeAuth middleware's shared secret; there is no
     * user behind this request.
     */
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
            'since' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * The configuration version the caller already holds. Zero means "send me
     * everything", which is what a node that has never synced reports.
     */
    public function since(): int
    {
        return (int) ($this->validated('since') ?? 0);
    }
}
