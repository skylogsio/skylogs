<?php

namespace App\Http\Requests\AlertRule;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlertRuleIndexRequest extends FormRequest
{
    /**
     * Fields that may be used for `$sort` when a sort query param is present.
     *
     * @var list<string>
     */
    private const SORTABLE_FIELDS = ['name'];

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
            'sortBy' => ['sometimes', 'string', Rule::in(self::SORTABLE_FIELDS)],
            'sortDir' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * MongoDB `$sort` document. Omitting sort params keeps pinned-first, then `_id`.
     *
     * @return array<string, int>
     */
    public function mongoSort(): array
    {
        $sortBy = $this->validated('sortBy');

        if (! filled($sortBy)) {
            return ['isPinned' => -1, '_id' => 1];
        }

        $direction = ($this->validated('sortDir') ?? 'asc') === 'desc' ? -1 : 1;

        return [
            $sortBy => $direction,
            '_id' => 1,
        ];
    }
}
