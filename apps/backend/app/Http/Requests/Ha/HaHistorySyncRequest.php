<?php

namespace App\Http\Requests\Ha;

use App\Services\Ha\HaHistoryCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HaHistorySyncRequest extends FormRequest
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
        $maxLimit = max(1, (int) config('ha.history_sync.page_size', 200));

        return [
            'collection' => ['required', 'string', Rule::in(HaHistoryCatalog::aliases())],
            'afterUpdatedAt' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'afterId' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9a-fA-F]{24}$/'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.$maxLimit],
        ];
    }

    public function collectionAlias(): string
    {
        return (string) $this->validated('collection');
    }

    public function afterUpdatedAt(): ?int
    {
        $value = $this->validated('afterUpdatedAt') ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }

    public function afterId(): ?string
    {
        $value = $this->validated('afterId') ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? config('ha.history_sync.page_size', 200));
    }
}
